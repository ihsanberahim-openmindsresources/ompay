<?php

namespace Omconnect\Pay\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Auth\User;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Exception\ClientException;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Omconnect\Pay\Models\PriceTier;
use Omconnect\Pay\Models\Product;
use Omconnect\Pay\Models\Purchase;
use Omconnect\Pay\Models\Subscription;
use Omconnect\Pay\Models\TokenTransaction;
use Omconnect\Pay\Models\IosPayNotification;

class IosPayService
{
    private $_client;
    private $_app_id;
    private $_team_id;
    private $_key_id;
    private $_keyfile;
    private $_iap_secret;

    public function __construct($app_id, $team_id, $key_id, $keyfile, $iap_secret)
    {
        $this->_client = new Client([
            'base_uri' => 'https://appleid.apple.com/',
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
        ]);
        $this->_app_id = $app_id;
        $this->_team_id = $team_id;
        $this->_key_id = $key_id;
        $this->_keyfile = $keyfile;
        $this->_iap_secret = $iap_secret;
    }

    private function _generateSecret()
    {
        $now = Carbon::now();
        $private_key = Storage::get($this->_keyfile);
        $payload = [
            'iss' => $this->_team_id,
            'iat' => $now->timestamp,
            'exp' => $now->addMinutes(10)->timestamp,
            'aud' => 'https://appleid.apple.com',
            'sub' => $this->_app_id,
        ];
        $token = JWT::encode($payload, $private_key, 'ES256', $this->_key_id);
        return $token;
    }

    public function getAppId()
    {
        return $this->_app_id;
    }

    public function validateCode($auth_code)
    {
        try {
            $request = $this->_client->post('auth/token', [
                'form_params' => [
                    'client_id' => $this->_app_id,
                    'client_secret' => $this->_generateSecret(),
                    'grant_type' => 'authorization_code',
                    'code' => $auth_code,
                ],
            ]);
            // [
            //     "access_token" => "ac1b22...fbaw"
            //     "token_type" => "Bearer"
            //     "expires_in" => 3600
            //     "refresh_token" => "r34aa...A53A"
            //     "id_token" => "eyJraW...OdGkgllvcEg"
            // ]
            return json_decode($request->getBody(), true);
        } catch (ClientException $ex) {
            Log::error('AppleService::validateCode - Error - ' . $ex->getMessage());
        }
        return null;
    }

    private function _getApplePublicKey($kid)
    {
        $keys = Cache::remember('apple_publickey', 86400, function () {
            $request = $this->_client->get('auth/keys');
            return json_decode($request->getBody(), true);
        });
        return JWK::parseKeySet($keys)[$kid];
    }

    public function getUserInfo($id_token)
    {
        $parts = explode('.', $id_token);
        $header = json_decode(base64_decode($parts[0]), true);
        $key = $this->_getApplePublicKey($header['kid']);
        $jwt = JWT::decode($id_token, $key, [$header['alg']]);
        if ($this->_app_id != $jwt->aud) {
            return null;
        }
        return [
            'uid' => $jwt->sub,
            'email' => $jwt->email,
            'expires_at' => Carbon::createFromTimestamp($jwt->exp),
        ];
    }

    private function _buildProductionClient()
    {
        return new Client([
            'base_uri' => 'https://buy.itunes.apple.com/',
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    private function _buildSandboxClient()
    {
        return new Client([
            'base_uri' => 'https://sandbox.itunes.apple.com/',
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function validateIapSecret($secret)
    {
        return $this->_iap_secret == $secret;
    }

    public function verifyReceipt($verification_data)
    {
        $client = $this->_buildProductionClient();
        try {
            $request = $client->post('/verifyReceipt', [
                RequestOptions::JSON => [
                    'receipt-data' => $verification_data,
                    'password' => $this->_iap_secret,
                ],
            ]);
            $data = json_decode($request->getBody(), true);
            if (isset($data['status']) && $data['status'] === 21007) {
                $client = $this->_buildSandboxClient();
                $request = $client->post('/verifyReceipt', [
                    RequestOptions::JSON => [
                        'receipt-data' => $verification_data,
                        'password' => $this->_iap_secret,
                    ],
                ]);
                $data = json_decode($request->getBody(), true);
            }
            if (isset($data['status'])) {
                if ($data['status'] !== 0) {
                    Log::error('AppleService::verifyReceipt - Invalid data [' . $data['status'] . '] - ' . $verification_data);
                } else {
                    Log::info('AppleService::verifyReceipt - Validated - ' . $verification_data);
                }
            }
            return $data;
        } catch (ClientException $ex) {
            Log::error('AppleService::verifyReceipt - Error - ' . $ex->getMessage());
        }
    }

    public function processReceipt($data, User $user = null, IosPayNotification $notification = null)
    {
        if (!isset($data['status'])) {
            return;
        }
        if ($data['status'] !== 0) {
            return;
        }

        $receipt = $data['receipt'];
        $receipt_type = $receipt['receipt_type'];
        // Check app id? - $receipt['bundle_id'];
        $purchases = $receipt['in_app'];
        foreach ($purchases as $purchase) {
            $product = Product::where('product_id_apple', $purchase['product_id'])->first();
            if ($product) {
                if ($product->months > 0) {
                    $this->_processSubscription($purchase, $receipt_type, $product, $user, $notification);
                } else {
                    $this->_processConsumableProduct($purchase, $receipt_type, $product, $user, $notification);
                }
                continue;
            }
            $price_tier = PriceTier::where('product_id_apple', $purchase['product_id'])->first();
            if ($price_tier) {
                $this->_processBundle($purchase, $receipt_type, $price_tier, $user, $notification);
                continue;
            }
        }
    }

    private function _processSubscription($purchase, $receipt_type, Product $product, User $user = null, IosPayNotification $notification = null)
    {
        $transaction_id = $purchase['transaction_id'];
        $subscription = Subscription::where('platform', Subscription::PLATFORM_APPLE)->where('transaction_id', $transaction_id)->first();
        if (!$subscription) {
            $subscription = new Subscription([
                'platform' => Subscription::PLATFORM_APPLE,
                'transaction_id' => $transaction_id,
            ]);
        }
        $original_transaction_id = $purchase['original_transaction_id'];
        $original_subscription = null;
        if ($original_transaction_id) {
            $original_subscription = Subscription::where('platform', Subscription::PLATFORM_APPLE)->where(function ($query) use ($original_transaction_id) {
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
        $subscription->receipt_payload = json_encode($purchase);
        $subscription->save();

        if ($subscription->user_id) {
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

    private function _processConsumableProduct($purchase, $receipt_type, Product $product, User $user = null, IosPayNotification $notification = null)
    {
        // Consumables requires pending purchase with User ID
        if (!$user) {
            return;
        }

        $transaction_id = $purchase['transaction_id'];
        $purchase = Purchase::where('platform', Purchase::PLATFORM_APPLE)
            ->where('transaction_id', $transaction_id)->first();
        if (!$purchase) {
            // Look for pending transaction
            $purchase = Purchase::where('platform', Purchase::PLATFORM_APPLE)
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

    private function _processBundle($purchase, $receipt_type, PriceTier $price_tier, User $user = null, IosPayNotification $notification = null)
    {
        // Bundle requires pending purchase with User ID
        if (!$user) {
            return;
        }

        $transaction_id = $purchase['transaction_id'];
        $purchase = Purchase::where('platform', Purchase::PLATFORM_APPLE)
            ->where('transaction_id', $transaction_id)->first();
        if (!$purchase) {
            // Look for pending transaction
            $purchase = Purchase::where('platform', Purchase::PLATFORM_APPLE)
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