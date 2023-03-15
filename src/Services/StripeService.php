<?php

namespace Omconnect\Pay\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Foundation\Auth\User;

use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\PromotionCode;
use Stripe\Stripe;
use Stripe\Subscription as StripeSubscription;

use Omconnect\Pay\Models\Product;
use Omconnect\Pay\Models\Subscription;
use Omconnect\Pay\Models\Transaction;
use Omconnect\Pay\Models\TransactionItem;
use Omconnect\Pay\Events\AfterProcessSubscription;

class StripeService
{
    private $_payment_methods = [];
    private $_success_url = '';
    private $_cancel_url = '';
    private $_subscription_success_url = '';
    private $_subscription_cancel_url = '';

    public function __construct($api_key, $payment_methods, $success_url, $cancel_url, $subscription_success_url, $subscription_cancel_url)
    {
        Stripe::setApiKey($api_key);
        $this->_payment_methods = explode(',', $payment_methods);
        $this->_success_url = $success_url;
        $this->_cancel_url = $cancel_url;
        $this->_subscription_success_url = $subscription_success_url;
        $this->_subscription_cancel_url = $subscription_cancel_url;
    }

    public function createUnlockTransaction(User $user, $skus)
    {
        /** @var Transaction */
        $transaction = null;

        DB::transaction(function () use (&$transaction, $user, $skus) {
            $discount_code = null;

            $first_sku = $skus[0];

            // Create Transaction
            $transaction = new Transaction([
                'user_id' => $user->id,
                'currency' => $first_sku->currency,
                'total_value' => 0,
                'status' => Transaction::STATUS_PENDING,
            ]);
            $transaction->save();

            $items = [];
            foreach ($skus as $sku) {
                $transaction_item = new TransactionItem([
                    'transaction_id' => $transaction->id,
                    'sku_id' => $sku->id,
                    'quantity' => 1,
                    'currency' => $sku->currency,
                    'amount' => $sku->price,
                    'discount' => 0,
                    'total' => $sku->price,
                ]);
                $items[] = $transaction_item;
            }

            $activation_codes = $user->activationCodes()->wherePivot('discount_remaining', '>', 0)->lockForUpdate()->get();
            foreach ($activation_codes as $activation_code) {
                if ($activation_code->discount_percent || $activation_code->discount_value) {
                    $discount_code = $activation_code;
                    break;
                }
            }

            if ($discount_code) {
                if ($discount_code->discount_value) {
                    $total_discount = $discount_code->discount_value;

                    $transaction_item = new TransactionItem([
                        'transaction_id' => $transaction->id,
                        'description' => 'Discount',
                        'quantity' => 1,
                        'currency' => $first_sku->currency,
                        'amount' => 0,
                        'discount' => $total_discount,
                        'total' => 0 - $total_discount,
                    ]);
                    $items[] = $transaction_item;
                } else if ($discount_code->discount_percent) {
                    foreach ($items as $item) {
                        $discounted = ceil($item->amount * $discount_code->discount_percent / 100);
                        $item->discount = $discounted;
                        $item->total = $item->amount - $item->discount;
                    }
                }

                $transaction->activation_code_id = $discount_code->id;

                $discount_code->pivot->discount_remaining = $discount_code->pivot->discount_remaining - 1;
                $discount_code->pivot->save();
            }

            foreach ($items as $item) {
                $item->save();
                $transaction->total_value += $transaction_item->total;
            }
            $transaction->save();
        });

        $transaction->refresh();
        $session = $this->createStripeSession($transaction);

        return $session['id'];
    }

    public function createStripeSession(Transaction $transaction)
    {
        $customer_id = $transaction->user->stripe_customer_id;

        if (!$customer_id) {
            $customer_id = $this->createCustomer($transaction->user);
        }

        // Stripe Payload
        $payload = [
            'payment_method_types' => $this->_payment_methods,
            'success_url' => $this->_success_url,
            'cancel_url' => $this->_cancel_url,
            'customer' => $customer_id,
            'mode' => 'payment',
            'line_items' => [],
            'client_reference_id' => $transaction->id,
            'metadata' => [],
        ];

        if ($transaction->activationCode) {
            $payload['metadata']['discount_code'] = $transaction->activationCode->code;
        }

        foreach ($transaction->items as $item) {
            if ($item->total > 0) {
                $payload['line_items'][] = [
                    'currency' => strtolower($item->currency),
                    'amount' => $item->total,
                    'name' => ($item->sku) ? $item->sku->title : $item->description,
                    'quantity' => 1,
                    'description' => ($item->sku) ? $item->sku->description : null,
                ];
            }
        }

        $session = Session::create($payload);
        $stripe_session_id = $session['id'];
        $transaction->stripe_session_id = $stripe_session_id;
        $transaction->save();

        return $session;
    }

    public function createCustomer(User $user)
    {
        $payload = [
            'email' => $user->email,
            'name' => $user->name,
            'phone' => $user->contact_number,
        ];

        $customer = Customer::create($payload);
        $user->stripe_customer_id = $customer['id'];
        $user->save();

        return $user->stripe_customer_id;
    }

    public function createGuestSubscription($code, Product $product)
    {
        /** @var Subscription */
        $subscription = null;

        DB::transaction(function () use (&$subscription, $code, $product) {
            $payload = [];

            // Create Subscription
            $subscription = new Subscription([
                'platform' => Subscription::PLATFORM_STRIPE,
                'transaction_id' => Str::random(32), // Create a random transaction ID, will replace by Stripe
            ]);
            $subscription->receipt_type = Str::contains(Stripe::$apiKey, 'test') ? 'Sandbox' : 'Production';
            $subscription->product_id = $product->id;
            $subscription->save();

            $payload['activation_code'] = [
                'id' => $code->id,
                'code' => $code->code,
            ];
            $payload['item'] = [
                'product_id' => $product->id,
                'currency' => $product->currency,
                'price' => $product->price,
            ];

            // Stripe Payload
            $payload = [
                'payment_method_types' => $this->_payment_methods,
                'success_url' => $this->_subscription_success_url,
                'cancel_url' => $this->_subscription_cancel_url,
                'mode' => 'payment',
                'line_items' => [],
                'client_reference_id' => $subscription->id,
                'metadata' => [
                    'type' => 'subscription',
                ],
                'discounts' => [
                    ['promotion_code' => $code->id]
                ],
            ];

            $payload['metadata']['discount_code'] = $code->code;

            $payload['line_items'][] = [
                'price_data' => [
                    'currency' => strtolower($product->currency),
                    'unit_amount' => $product->price,
                    'product_data' => [
                        'name' => $product->title,
                    ],
                ],
                'quantity' => 1,
            ];

            $session = Session::create($payload);
            $stripe_session_id = $session['id'];
            $subscription->transaction_id = $stripe_session_id;
            $subscription->save();
        });

        $subscription->refresh();

        AfterProcessSubscription::dispatch($subscription);

        return $subscription->transaction_id;
    }

    public function findCouponCode($coupon_code)
    {
        $collection = PromotionCode::all(['active' => true, 'code' => $coupon_code]);

        /** @var PromotionCode */
        $coupon = $collection->first();

        return $coupon;
    }

    public function findCheckoutSession($session_id)
    {
        return Session::retrieve($session_id);
    }

    public function findSubscription($subscription_id)
    {
        return StripeSubscription::retrieve($subscription_id);
    }
}