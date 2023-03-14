<?php

namespace Omconnect\Pay\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Omconnect\Pay\Models\IosPayNotification;
use Omconnect\Pay\Services\IosPayService;

class IosPayController extends Controller
{
    public function notification(Request $request, IosPayService $iosPaySvc)
    {
        $input = json_decode($request->getContent(), true);

        // Check Shared Secret
        $password = $input['password'];

        // Remove password from payload
        unset($input['password']);

        // Insert an entry
        $iosPayNotification = new IosPayNotification([
            'environment' => $input['environment'],
            'notification_type' => $input['notification_type'],
            'auto_renew_product_id' => $input['auto_renew_product_id'],
            'auto_renew_status' => isset($input['auto_renew_status']) ? ($input['auto_renew_status'] == 'true') : null,
            'auto_renew_status_change_date' => (isset($input['auto_renew_status_change_date_ms'])) 
                ? Carbon::createFromTimestampMs($input['auto_renew_status_change_date_ms']) 
                : null,
            'payload' => json_encode($input),
        ]);
        $iosPayNotification->save();

        if ($iosPaySvc->validateIapSecret($password)) {
            $unified_receipt = $input['unified_receipt'];
            $verification_data = $unified_receipt['latest_receipt'];

            $data = $iosPaySvc->verifyReceipt($verification_data);
            $iosPaySvc->processReceipt($data, null, $iosPayNotification);

            return response('');
        }

        return response('', 202);
    }
}
