<?php

namespace Omconnect\Pay\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

use Stripe\Webhook;
use Stripe\Checkout\Session;
use Stripe\Exception\SignatureVerificationException;

use Omconnect\Pay\Models\Transaction;
use Omconnect\Pay\Models\TransactionPayload;
use Omconnect\Pay\Models\Subscription;

class StripeController extends Controller
{
    //
    public function handle(Request $request)
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
                switch ($event->data->object->metadata->type) {
                    case 'subscription':
                        $this->_processSubscription($data, $event);
                        break;
                    default:
                        $this->_processContentUnlock($data, $event);
                        break;
                }
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
        });
    }

    private function _processSubscription($payload, $event)
    {
        $useClass = app(
            config('auth.providers.users.model')
        );

        /** @var Session */
        $session = $event->data->object;

        $subscription = Subscription::where('transaction_id', $session->id)->first();
        if (!$subscription) {
            abort(202);
        }

        $email = $session->customer_details->email;
        $purchase_date = new Carbon($event->created);

        // Try associate with existing users
        if ($email) {
            $useClass = app(
                config('auth.providers.users.model')
            );

            $user = $useClass::where('email', $email)->first();
            if ($user) {
                $subscription->user_id = $user->id;
                $subscription->expires_date = Carbon::now()->addMonth($subscription->product->months);
            }
        }

        // Update Info
        $subscription->purchase_date = $purchase_date;
        $subscription->receipt_payload = $payload;
        $subscription->save();

        // Refresh User Subscription
        if ($subscription->user_id) {
            $owner = $useClass::find($subscription->user_id);
            if ($owner) {
                $owner->resetActiveSubscriptionCache();
            }
        }
    }
}
