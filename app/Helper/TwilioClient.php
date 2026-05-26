<?php

namespace App\Helper;

use Twilio\Rest\Client;
use Twilio\TwiML\VoiceResponse;
use Illuminate\Support\Facades\Log;

/**
 *
 * Required ENV
 *
 * TWILIO_ACCOUNT_SID
 * TWILIO_AUTH_TOKEN
 * TWILIO_NUMBER
 *
 */
class TwilioClient {

    private $twilio;
    private $twilio_number;
    private $webhook_url;

    public function __construct() {

        $sid = env("TWILIO_ACCOUNT_SID");
        $token = env("TWILIO_AUTH_TOKEN");
        $this->webhook_url = route('twilioWebhook'); // Navigate to TwilioController
        $this->twilio_number = env("TWILIO_NUMBER");
        $this->twilio = new Client($sid, $token);
    }

    public function connectCall($callerUserNumber, $receiverUserNumber) {
        $queryParams = ['type' => 'receiver', 'to' => $receiverUserNumber,];
        $webhook_url = $this->webhook_url . "?" . http_build_query($queryParams);

        try {
            // $call->sid
            $call = $this->twilio->calls->create(
                $callerUserNumber,
                $this->twilio_number,
                ["method" => "GET", "url" => $webhook_url]
            );
            return  $call->sid;
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
        return null;
    }

    private function receiveCall() {
        $receiverNumber = request()->input('to');
        $response = new VoiceResponse();

        if (!$receiverNumber) {
            $response->say('Sorry, we could not connect the call. Goodbye.');
            $response->hangup();
        } else {
            $response->say('Please wait while we connect you to the other party.');
            $response->dial($receiverNumber);
        }

        return $response;
    }

    public function handleWebhook() {
        $type = request()->input('type');

        if ($type == 'receiver') {
            $response = $this->receiveCall();
            return response((string)$response)->header('Content-Type', 'text/xml');
        }

        return http_response_code(400);
    }

}
