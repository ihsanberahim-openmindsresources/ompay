<?php

namespace Omconnect\Pay\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Validator;

use Omconnect\Pay\Models\Product;
use Omconnect\Pay\Services\AndroidPayService;
use Omconnect\Pay\Services\IosPayService;

class SubscriptionController extends Controller
{
    public function list(Request $request, User $user)
    {
        $product_collection = Product::where('is_active', true)
            ->orderBy('months', 'desc')
            ->get();
            
        $products = [];

        $active_subscription = $user->activeSubscription();

        foreach ($product_collection as $p) {
            $owned = ($active_subscription) ? $active_subscription->product_id == $p->id : false;
            $product = $p->toArray();

            $product = array_merge($product, [
                'owned' => $owned,
                'expiryDate' => $owned ?  $active_subscription->expires_date : null,
                'platform' => $owned ?  $active_subscription->platform : null,
            ]);

            $products[] = $product;
        }

        return response([
            'status' => 1,
            'products' => $products,
        ]);
    }

    public function verify(Request $request, User $user, IosPayService $iosPaySvc, AndroidPayService $androidPaySvc)
    {
        $validation = Validator::make($request->all(), [
            'source' => [
                'required',
                Rule::in([
                    'apple',
                    'google',
                ]),
            ],
            'product_id' => ['required'],
            'verification_data' => ['required'],
        ]);

        if ($validation->fails()) {
            return response([
                'status' => 0,
                'errors' => $validation->errors(),
            ], 422);
        }

        $input = $validation->valid();

        // Before Update
        $current_active_subscription = $user->activeSubscription();
        $current_active_token_subscriptions = $user->activeSubscriptions(Product::TYPE_TOKENSUBSCRIPTION);

        switch ($input['source']) {
            case 'apple':
                $data = $iosPaySvc->verifyReceipt($input['verification_data']);
                $iosPaySvc->processReceipt($data, $user);
                break;
            case 'google':
                $data = $androidPaySvc->verifyPurchase($input['verification_data'], $input['product_id']);
                $androidPaySvc->processReceipt($data, $user);
                break;
            default:
                return response([
                    'status' => 0,
                    'errors' => [
                        'source' => ['Unsupported'],
                    ],
                ], 422);
        }

        // After Update
        $new_active_subscription = $user->activeSubscription();
        $new_active_token_subscriptions = $user->activeSubscriptions(Product::TYPE_TOKENSUBSCRIPTION);

        $status = 0;
        $new_subscriptions = [];
        // Any new Subscription
        if (
            $new_active_subscription != null
            && ($current_active_subscription == null
                || ($current_active_subscription->id != $new_active_subscription->id)
            )
        ) {
            $status = 1;
            $new_subscriptions[] = true;
        }
        // Any new Token Subscriptions
        if ($current_active_token_subscriptions != $new_active_token_subscriptions) {
            $status = 1;
            $new_subscriptions[Product::TYPE_TOKENSUBSCRIPTION] = true;
        }

        if ($status) {
            // event::dispatch($user->id);
        }

        return response([
            'status' => $status,
            'newSubscriptions' => (object) $new_subscriptions,
        ]);
    }

    /** V2 */
    public function listAll(Request $request, User $user)
    {
        $data = [];

        $product_collection = Product::where('is_active', true)
            ->orderBy('months', 'desc')
            ->get();
        $products = [];

        $active_subscription = $user->activeSubscription();

        foreach ($product_collection as $p) {
            $owned = ($active_subscription) ? $active_subscription->product_id == $p->id : false;
            $product = $p->toArray();
            $product = array_merge($product, [
                'owned' => $owned,
                'expiryDate' => $owned ?  $active_subscription->expires_date : null,
                'platform' => $owned ?  $active_subscription->platform : null,
            ]);
            $products[] = $product;
        }

        $data[] = [
            'products' => $products,
            'upgradeable' => false,
        ];

        /** Token Subscriptions */
        $product_collection = Product::where('is_active', true)
            ->where('type', Product::TYPE_TOKENPACK)
            ->orderBy('tokens')
            ->get();
        $token_packs = [];

        foreach ($product_collection as $p) {
            $product = [
                'id' => $p->id,
                'title' => $p->title,
                'productIdGoogle' => $p->product_id_google,
                'productIdApple' => $p->product_id_apple,
                'months' => $p->months,
                'tokens' => $p->tokens,
                'freeTokens' => $p->free_tokens,
            ];
            $token_packs[] = $product;
        }

        return response([
            'status' => 1,
            'subscriptions' => $data,
            'tokenPacks' => $token_packs,
        ]);
    }
}
