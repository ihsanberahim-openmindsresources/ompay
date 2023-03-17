<?php

namespace Omconnect\Pay\Http\Controllers;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

use Omconnect\Pay\Models\Product;
use Omconnect\Pay\Services\AndroidPayService;
use Omconnect\Pay\Services\IosPayService;

class SubscriptionController extends Controller
{
    public function list(Request $request)
    {
        $productCollection = Product::where('is_active', true)
            ->orderBy('months', 'desc')
            ->get();
        
        $activeSubscription = $this->getUserActiveSubscription();

        $productId = Arr::get($activeSubscription, 'id');
        $expiresDate = Arr::get($activeSubscription, 'expires_date');
        $platform = Arr::get($activeSubscription, 'platform');

        $products = [];

        foreach ($productCollection as $p) {
            $product = $p->toArray();

            $owned = ($productId == $p->id);

            $product = array_merge($product, [
                'owned' => $owned,
                'expiry_date' => $expiresDate,
                'platform' => $platform,
            ]);

            $products[] = $product;
        }

        return response([
            'status' => 1,
            'products' => $products,
        ]);
    }

    public function verify(Request $request, IosPayService $iosPaySvc, AndroidPayService $androidPaySvc)
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
        $user = auth()->user();

        // Before Update
        $currentActiveSubscription = $user ? $user->activeSubscription() : $user;

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
        $newActionSubscription = $user ? $user->activeSubscription() : null;

        $status = 0;
        $new_subscriptions = [];

        // Any new Subscription
        $newActiveSubId = Arr::get($newActionSubscription, 'id');
        $currentActiveSubId = Arr::get($currentActiveSubscription, 'id');

        if ($newActiveSubId != $currentActiveSubId) {
            $status = 1;
            $new_subscriptions[] = true;
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
    public function listAll(Request $request)
    {
        $product_collection = Product::where('is_active', true)
            ->orderBy('months', 'desc')
            ->get();

        $activeSubscription = $this->getUserActiveSubscription();
        
        $productId = Arr::get($activeSubscription, 'id');
        $expiresDate = Arr::get($activeSubscription, 'expires_date');
        $platform = Arr::get($activeSubscription, 'platform');
        
        $products = [];
        foreach ($product_collection as $p) {

            $product = $p->toArray();

            $owned = ($productId == $p->id);

            $product = array_merge($product, [
                'owned' => $owned,
                'expiry_date' => $expiresDate,
                'platform' => $platform,
            ]);

            $products[] = $product;
        }

        $data[] = [
            'products' => $products,
            'upgradeable' => false,
        ];

        return response([
            'status' => 1,
            'subscriptions' => $data,
        ]);
    }

    /**
     * @return Subscription | null 
     * @throws BindingResolutionException 
     */
    private function getUserActiveSubscription() {
        $user = auth()->user();

        if(!$user) return null;

        return $user->activeSubscription();
    }
}
