<?php

namespace Omconnect\Pay\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Omconnect\Pay\Events\AfterProcessSubscription;
use Omconnect\Pay\Models\Product;
use Stripe\Subscription as StripeSubscription;
use Stripe\Webhook;
use Stripe\Checkout\Session;
use Stripe\Exception\SignatureVerificationException;

use Omconnect\Pay\Models\Transaction;
use Omconnect\Pay\Models\TransactionPayload;
use Omconnect\Pay\Models\Subscription;
use Omconnect\Pay\Models\TokenTransaction;
use Omconnect\Pay\Services\StripeService;

class StripeController extends Controller
{
    //
    public function handle(Request $request, StripeService $service)
    {
        $webhook_key = config('stripe.webhook_key');
        $signature = $request->header('Stripe-Signature');
        $event = null;
        $data = $request->getContent();

        try {
            $event = Webhook::constructEvent($data, $signature, $webhook_key);
        } catch (\UnexpectedValueException $ex) {
            return response([
                'status' => 0,
                'message' => 'ERROR_INVALID_PAYLOAD',
            ], 400);
        } catch (SignatureVerificationException $ex) {
            return response([
                'status' => 0,
                'message' => 'ERROR_INVALID_SIGNATURE',
            ], 400);
        }

        switch ($event->type) {
            case 'checkout.session.completed':
                /** @var Session */
                $session = $event->data->object;
                switch (true) {
                    case ($session->metadata->type == 'subscription')
                        || ($session->mode == 'subscription'):
                        $this->_processSubscription($data, $event, $service);
                        break;
                    default:
                        $this->_processContentUnlock($data, $event);
                        break;
                }
                break;
            case 'customer.subscription.created':
            case 'customer.subscription.updated':
            case 'customer.subscription.deleted':
                $this->_processStripeSubscription($data, $event);
                break;
            default:
                // Unknown type
                return response([
                    'status' => 0,
                    'message' => 'ERROR_INVALID_EVENT',
                ], 400);
        }

        return response([
            'status' => 1,
        ]);
    }

    private function _processContentUnlock($payload, $event)
    {
        /** @var Session */
        $session = $event->data->object;

        $transaction = Transaction::where('stripe_session_id', $session->id)->first();
        if (!$transaction) {
            abort(202);
        }
        DB::transaction(function () use ($transaction, $payload) {
            $transaction->status = Transaction::STATUS_SUCCESS;
            $transaction->save();

            $payload = new TransactionPayload([
                'transaction_id' => $transaction->id,
                'payload' => $payload,
            ]);
            $payload->save();

            $sku_ids = $transaction->items()->whereNotNull('sku_id')->pluck('sku_id')->toArray();
            $transaction->user->skus()->syncWithoutDetaching($sku_ids);
            $transaction->user->resetOwnedCache();

            // Mail::queue(new MailTransactionSuccess($transaction));
        });
    }

    private function _processSubscription($payload, $event, StripeService $service)
    {
        /** @var Session */
        $session = $event->data->object;

        switch (true) {
            case ($session->metadata->type == 'subscription'):
                /** Legacy */
                $subscription = Subscription::where('transaction_id', $session->id)->first();
                if (!$subscription) {
                    abort(202);
                }

                $email = $session->customer_details->email;
                $purchase_date = new Carbon($event->created);

                // Try associate with existing users
                if ($email) {
                    $userModel = app(config('auth.providers.users.model'));
                    $user = $userModel::where('email', $email)->first();
                    if ($user) {
                        $subscription->user_id = $user->id;
                        $subscription->expires_date = Carbon::now()->addMonth($subscription->product->months);
                    }
                }

                // Update Info
                $subscription->purchase_date = $purchase_date;
                $subscription->receipt_payload = $payload;
                $subscription->save();
                break;
            case $session->mode == 'subscription':
                // Subscription Item
                $subscription_id = $session->subscription;
                $stripe_subscription = $service->findSubscription($subscription_id);
                $subscription = $this->_retrieveStripeSubscription($stripe_subscription, $event);
                if (!$subscription) {
                    abort(202);
                }

                if (!$subscription->user_id) {
                    $email = $session->customer_details->email;
                    // Try associate with existing users
                    if ($email) {
                        $userModel = app(config('auth.providers.users.model'));
                        $user = $userModel::where('email', $email)->first();
                        if ($user) {
                            $subscription->user_id = $user->id;
                        }
                    }
                }

                // Update Info
                $subscription->expires_date = Carbon::parse($stripe_subscription->current_period_end);
                $subscription->cancellation_date = $stripe_subscription->canceled_at ? Carbon::parse($stripe_subscription->canceled_at) : null;
                $subscription->receipt_payload = $payload;
                $subscription->save();
                break;
        }

        // Refresh User Subscription
        if ($subscription && $subscription->user_id) {
            $userModel = app(config('auth.providers.users.model'));
            $owner = $userModel::find($subscription->user_id);
            if ($owner) {
                $owner->resetActiveSubscriptionCache();
                // event::dispatch($owner->id);
                // event::dispatch($owner, $subscription);
                // Add tokens
                $product = $subscription->product;
                if ($product->tokens > 0) {
                    if (
                        !$owner->tokenTransactions()
                            ->where('referenceable_type', get_class($subscription))
                            ->where('referenceable_id', $subscription->id)
                            ->exists()
                    ) {
                        $owner->tokenTransactions()->save(new TokenTransaction([
                            'referenceable_type' => get_class($subscription),
                            'referenceable_id' => $subscription->id,
                            'value' => $product->tokens,
                        ]));
                    }
                }
            }
        }

        if($subscription) {
            AfterProcessSubscription::dispatch($subscription);
        }
    }

    private function _retrieveStripeSubscription(StripeSubscription $stripe_subscription, $event)
    {
        $transaction_id = $stripe_subscription->latest_invoice;
        $subscription = Subscription::where('platform', Subscription::PLATFORM_STRIPE)->where('transaction_id', $transaction_id)->first();
        if (!$subscription) {
            $original_subscription = Subscription::where('platform', Subscription::PLATFORM_STRIPE)->where('original_transaction_id', $stripe_subscription->id)->first();
            // Retrieve product
            $item = $stripe_subscription->items->data[0];
            $plan_stripe_id = $item->plan->id;
            $product = Product::where('product_id_stripe', $plan_stripe_id)->first();
            if (!$product) {
                Log::warning('Stripe product [' . $plan_stripe_id . '] not found.');
                return null;
            }
            //
            $subscription = new Subscription([
                'platform' => Subscription::PLATFORM_STRIPE,
                'transaction_id' => $transaction_id
            ]);
            $subscription->original_transaction_id = $stripe_subscription->id;
            $subscription->receipt_type = $event->livemode ? 'Production' : 'Sandbox';
            $subscription->product_id = $product->id;
            $subscription->purchase_date = Carbon::parse($event->created);
            $subscription->user_id = $original_subscription ? $original_subscription->user_id : null;
        }
        return $subscription;
    }

    private function _processStripeSubscription($payload, $event)
    {
        /** @var StripeSubscription */
        $stripe_subscription = $event->data->object;

        $subscription = $this->_retrieveStripeSubscription($stripe_subscription, $event);
        if (!$subscription) {
            abort(202);
        }

        // Update Info
        $subscription->expires_date = Carbon::parse($stripe_subscription->current_period_end);
        $subscription->cancellation_date = $stripe_subscription->canceled_at ? Carbon::parse($stripe_subscription->canceled_at) : null;
        $subscription->receipt_payload = $payload;
        $subscription->save();

        // Subscription Refresh
        $subscription->refresh();

        // Refresh User Subscription
        if ($subscription->user_id) {
            $userModel = app(config('auth.providers.users.model'));
            $owner = $userModel::find($subscription->user_id);
            if ($owner) {
                $owner->resetActiveSubscriptionCache();

                // event::dispatch($owner->id);
                // event::dispatch($owner, $subscription);

                // Add tokens
                $product = $subscription->product;
                if ($product->tokens > 0) {
                    if (
                        !$owner->tokenTransactions()
                            ->where('referenceable_type', get_class($subscription))
                            ->where('referenceable_id', $subscription->id)
                            ->exists()
                    ) {
                        $owner->tokenTransactions()->save(new TokenTransaction([
                            'referenceable_type' => get_class($subscription),
                            'referenceable_id' => $subscription->id,
                            'value' => $product->tokens,
                        ]));
                    }
                }
            }
        }

        AfterProcessSubscription::dispatch($subscription);
    }
}
