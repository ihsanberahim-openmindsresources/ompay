<?php

namespace Omconnect\Pay\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Omconnect\Pay\Models\AndroidPayNotification;
use Omconnect\Pay\Services\AndroidPayService;

class AndroidPayController extends Controller
{
    const NOTIFICATION_TYPE = [
        1 => 'SUBSCRIPTION_RECOVERED',
        2 => 'SUBSCRIPTION_RENEWED',
        3 => 'SUBSCRIPTION_CANCELED',
        4 => 'SUBSCRIPTION_PURCHASED',
        5 => 'SUBSCRIPTION_ON_HOLD',
        6 => 'SUBSCRIPTION_IN_GRACE_PERIOD',
        7 => 'SUBSCRIPTION_RESTARTED',
        8 => 'SUBSCRIPTION_PRICE_CHANGE_CONFIRMED',
        9 => 'SUBSCRIPTION_DEFERRED',
        10 => 'SUBSCRIPTION_PAUSED',
        11 => 'SUBSCRIPTION_PAUSE_SCHEDULE_CHANGED',
        12 => 'SUBSCRIPTION_REVOKED',
        13 => 'SUBSCRIPTION_EXPIRED',
    ];

    public function notification(Request $request, AndroidPayService $google_svc)
    {
        $input = json_decode($request->getContent(), true);

        if (
            !isset($input['message'])
            || !isset($input['message']['data'])
        ) {
            return response('', 202);
        }

        $data = json_decode(base64_decode($input['message']['data']), true);

        if (!isset($data['subscriptionNotification'])) {
            return response('', 202);
        }

        $subscription_data = $data['subscriptionNotification'];

        $notification_type = (isset(self::NOTIFICATION_TYPE[$subscription_data['notificationType']])) 
            ? self::NOTIFICATION_TYPE[$subscription_data['notificationType']] 
            : 'UNKNOWN';

        // Insert an entry
        $google_notification = new AndroidPayNotification([
            'notification_type' => $notification_type,
            'auto_renew_product_id' => $subscription_data['subscriptionId'],
            'payload' => json_encode($input),
        ]);
        $google_notification->save();

        $data = $google_svc->verifyPurchase($subscription_data['purchaseToken'], $subscription_data['subscriptionId']);
        $google_svc->processReceipt($data, null, $google_notification);

        return response('');
    }
}
