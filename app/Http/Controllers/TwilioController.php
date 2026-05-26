<?php

namespace App\Http\Controllers;

use App\Helper\TwilioClient;
use Illuminate\Support\Facades\Request;

class TwilioController extends Controller {

    public function webhooks(Request $request) {
        $client = new TwilioClient();
        return $client->handleWebhook();
    }

}
