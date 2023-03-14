<?php

namespace Omconnect\Pay\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Carbon;
use GuzzleHttp\Exception\ClientException;
use Google_Client;
use Google_Service_AndroidPublisher;
use Google_Service_Exception;

use Omconnect\Pay\Models\PriceTier;
use Omconnect\Pay\Models\Product;
use Omconnect\Pay\Models\Purchase;
use Omconnect\Pay\Models\Subscription;
use Omconnect\Pay\Models\TokenTransaction;
use App\Models\User;
use Omconnect\Pay\Models\AndroidPayNotification;

class AndroidPayService
{
    private $_keyfile;
    private $_iap_bundle;

    public function __construct($keyfile, $iap_bundle)
    {
        $this->_keyfile = $keyfile;
        $this->_iap_bundle = $iap_bundle;
    }

    public function getGoogleServiceKey() {
        return str($this->_keyfile)->startsWith('{') 
            ? $this->_keyfile 
            : Storage::path($this->_keyfile);
    }

    private function _getOrderId($order_id)
    {
        $regex = '/(.+?)(\.\.\d+)?$/';
        $matched = preg_match($regex, $order_id, $matches);
        return ($matched) ? $matches[1] : $order_id;
    }

    public function verifyPurchase($purchase_token, $product_id)
    {
        $client = new Google_Client();
        $client->setAuthConfig(
            $this->getGoogleServiceKey()
        );
        $client->setScopes(Google_Service_AndroidPublisher::ANDROIDPUBLISHER);

        try {
            $publisher = new Google_Service_AndroidPublisher($client);
            $result = $publisher->purchases_subscriptions->get($this->_iap_bundle, $product_id, $purchase_token);

            $original_transaction_id = null;
            if ($result->linkedPurchaseToken) {
                $linked = $this->verifyPurchase($result->linkedPurchaseToken, $product_id);
                if ($linked) {
                    $original_transaction_id = $this->_getOrderId($linked['transaction_id']);
                }
            }
            if ($original_transaction_id == null) {
                // Change original transaction ID
                $parsed_order_id = $this->_getOrderId($result->orderId);
                if ($parsed_order_id != $result->orderId) {
                    $original_transaction_id = $parsed_order_id;
                }
            }

            $data = [
                'receipt_type' => ($result->purchaseType === 0) ? 'Sandbox' : 'Production',
                'transaction_id' => $result->orderId,
                'original_transaction_id' => $original_transaction_id,
                'purchase_date_ms' => $result->startTimeMillis,
                'expires_date_ms' => $result->expiryTimeMillis,
                'cancellation_date_ms' => $result->userCancellationTimeMillis,
                'cancellation_reason' => $result->cancelReason,
                'product_id' => $product_id,
                'payload' => json_encode($result),
            ];

            return $data;
        } catch (Google_Service_Exception $ex) {
            Log::error("GoogleService::verifyPurchase - Error - {$purchase_token} - {$product_id} - {$ex->getMessage()}");
        } catch (ClientException $ex) {
            Log::error("GoogleService::verifyPurchase - Error - {$purchase_token} - {$product_id} - {$ex->getMessage()}");
        }
    }

    public function verifyConsumable($purchase_token, $product_id)
    {
        $client = new Google_Client();
        $client->setAuthConfig(Storage::path($this->_keyfile));
        $client->setScopes(Google_Service_AndroidPublisher::ANDROIDPUBLISHER);

        try {
            $publisher = new Google_Service_AndroidPublisher($client);
            $result = $publisher->purchases_products->get($this->_iap_bundle, $product_id, $purchase_token);

            $data = [
                'receipt_type' => ($result->purchaseType === 0) ? 'Sandbox' : 'Production',
                'transaction_id' => $this->_getOrderId($result->orderId),
                'original_transaction_id' => null,
                'purchase_date_ms' => $result->purchaseTimeMillis,
                'expires_date_ms' => $result->expiryTimeMillis,
                'cancellation_date_ms' => $result->userCancellationTimeMillis,
                'cancellation_reason' => $result->cancelReason,
                'product_id' => $product_id,
                'payload' => json_encode($result),
            ];

            return $data;
        } catch (Google_Service_Exception $ex) {
            Log::error("GoogleService::verifyPurchase - Error - {$purchase_token} - {$product_id} - {$ex->getMessage()}");
        } catch (ClientException $ex) {
            Log::error("GoogleService::verifyPurchase - Error - {$purchase_token} - {$product_id} - {$ex->getMessage()}");
        }
    }

    public function processReceipt($purchase, User $user = null, AndroidPayNotification $notification = null)
    {
        if ($purchase == null) {
            return;
        }

        $receipt_type = $purchase['receipt_type'];
        $product = Product::where('product_id_google', $purchase['product_id'])->first();
        if ($product) {
            if ($product->months > 0) {
                $this->_processSubscription($purchase, $receipt_type, $product, $user, $notification);
            } else {
                $this->_processConsumableProduct($purchase, $receipt_type, $product, $user, $notification);
            }
            return;
        }
        $price_tier = PriceTier::where('product_id_google', $purchase['product_id'])->first();
        if ($price_tier) {
            $this->_processBundle($purchase, $receipt_type, $price_tier, $user, $notification);
            return;
        }
    }

    private function _processSubscription($purchase, $receipt_type, Product $product, User $user = null, AndroidPayNotification $notification = null)
    {
        $transaction_id = $purchase['transaction_id'];
        $subscription = Subscription::where('platform', Subscription::PLATFORM_GOOGLE)->where('transaction_id', $transaction_id)->first();
        if (!$subscription) {
            $subscription = new Subscription([
                'platform' => Subscription::PLATFORM_GOOGLE,
                'transaction_id' => $transaction_id,
            ]);
        }
        $original_transaction_id = $purchase['original_transaction_id'];
        $original_subscription = null;
        if ($original_transaction_id) {
            $original_subscription = Subscription::where('platform', Subscription::PLATFORM_GOOGLE)->where(function ($query) use ($original_transaction_id) {
                $query->orWhere('transaction_id', $original_transaction_id)
                    ->orWhere('original_transaction_id', $original_transaction_id);
            })->whereNotNull('user_id')->first();
        }
        if (!$subscription->user_id && ($user || $original_subscription)) {
            $subscription->user_id = ($user) ? $user->id : $original_subscription->user_id;
        }
        $subscription->original_transaction_id = $purchase['original_transaction_id'];
        $subscription->receipt_type = $receipt_type;
        if ($product) {
            $subscription->product_id = $product->id;
        }
        $subscription->purchase_date = Carbon::createFromTimestampMs($purchase['purchase_date_ms']);
        $subscription->expires_date = Carbon::createFromTimestampMs($purchase['expires_date_ms']);
        if (isset($purchase['cancellation_date_ms'])) {
            $subscription->cancellation_date = Carbon::createFromTimestampMs($purchase['cancellation_date_ms']);
            $subscription->cancellation_reason = $purchase['cancellation_reason'];
        }
        $subscription->receipt_payload = $purchase['payload'];
        $subscription->save();

        if ($subscription->user_id) {
            /**
             * @var User $owner
             */
            $owner = User::find($subscription->user_id);
            if ($owner) {
                $owner->resetActiveSubscriptionCache();
                // Update Notification
                if ($notification) {
                    $notification->user_id = $owner->id;
                    $notification->save();
                }
                // Add tokens
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
    }

    private function _processConsumableProduct($purchase, $receipt_type, Product $product, User $user = null, AndroidPayNotification $notification = null)
    {
        // Consumables requires pending purchase with User ID
        if (!$user) {
            return;
        }

        $transaction_id = $purchase['transaction_id'];
        $purchase = Purchase::where('platform', Purchase::PLATFORM_GOOGLE)
            ->where('transaction_id', $transaction_id)->first();
        if (!$purchase) {
            // Look for pending transaction
            $purchase = Purchase::where('platform', Purchase::PLATFORM_GOOGLE)
                ->whereNull('transaction_id')
                ->where('user_id', $user->id)
                ->where('status', Purchase::STATUS_PENDING)
                ->where('product_id', $product->id)
                ->first();
            if (!$purchase) {
                // Unable to find pending purchase
                return;
            }
            $purchase->transaction_id = $transaction_id;
        }
        $purchase->receipt_type = $receipt_type;
        $purchase->purchase_date = Carbon::createFromTimestampMs($purchase['purchase_date_ms']);
        $purchase->receipt_payload = json_encode($purchase);
        $purchase->status = Purchase::STATUS_COMPLETED;
        $purchase->save();

        if ($purchase->user_id) {
            $owner = User::find($purchase->user_id);
            if ($owner) {
                // Add tokens
                if ($product->tokens > 0) {
                    if (
                        !$owner->tokenTransactions()
                            ->where('referenceable_type', get_class($purchase))
                            ->where('referenceable_id', $purchase->id)
                            ->exists()
                    ) {
                        $owner->tokenTransactions()->save(new TokenTransaction([
                            'referenceable_type' => get_class($purchase),
                            'referenceable_id' => $purchase->id,
                            'value' => $product->tokens,
                        ]));
                    }
                }
                // Update Notification
                if ($notification) {
                    $notification->user_id = $owner->id;
                    $notification->save();
                }
            }
        }
    }

    private function _processBundle($purchase, $receipt_type, PriceTier $price_tier, User $user = null, AndroidPayNotification $notification = null)
    {
        // Bundle requires pending purchase with User ID
        if (!$user) {
            return;
        }

        $transaction_id = $purchase['transaction_id'];
        $purchase = Purchase::where('platform', Purchase::PLATFORM_GOOGLE)
            ->where('transaction_id', $transaction_id)->first();
        if (!$purchase) {
            // Look for pending transaction
            $purchase = Purchase::where('platform', Purchase::PLATFORM_GOOGLE)
                ->whereNull('transaction_id')
                ->where('user_id', $user->id)
                ->where('status', Purchase::STATUS_PENDING)
                ->where('price_tier_id', $price_tier->id)
                ->first();
            if (!$purchase) {
                // Unable to find pending purchase
                return;
            }
            $purchase->transaction_id = $transaction_id;
        }
        $purchase->receipt_type = $receipt_type;
        $purchase->purchase_date = Carbon::createFromTimestampMs($purchase['purchase_date_ms']);
        $purchase->receipt_payload = json_encode($purchase);
        $purchase->status = Purchase::STATUS_COMPLETED;
        $purchase->save();

        if ($purchase->user_id && $purchase->sku_id) {
            $owner = User::find($purchase->user_id);
            if ($owner) {
                $owner->skus()->syncWithoutDetaching([$purchase->sku_id]);
                $owner->resetOwnedCache();
                // Update Notification
                if ($notification) {
                    $notification->user_id = $owner->id;
                    $notification->save();
                }
            }
        }
    }
}
