<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TwilioController;


//TwilioRoutes // VerifyCsrfToken <- Add this for csrf except
Route::any('twilio-webhook', [TwilioController::class, 'webhooks'])->name('twilioWebhook');

