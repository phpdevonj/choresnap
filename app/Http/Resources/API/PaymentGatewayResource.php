<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentGatewayResource extends JsonResource
{
    /**
     * Credential fields that must never leave the server. This endpoint used to
     * return the decoded value/live_value as-is, which handed every logged-in
     * app user the live Stripe secret key.
     */
    private const SECRET_PATTERN = '/secret|salt|passphrase|private/i';

    private const SECRET_FIELDS = [
        'stripe_key',
    ];

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type'   => $this->type,
            'status'  => $this->status,
            'is_test'=> $this->is_test,
            'value'  => $this->publicCredentials($this->value),
            'live_value'=> $this->publicCredentials($this->live_value),
        ];
    }

    /**
     * Strip secret credentials, keeping the publishable keys and urls the app
     * needs to start a payment.
     */
    private function publicCredentials($raw)
    {
        if ($raw === null || $raw === '') {
            return $raw;
        }

        $credentials = is_array($raw) ? $raw : json_decode($raw, true);

        if (!is_array($credentials)) {
            return $credentials;
        }

        foreach (array_keys($credentials) as $field) {
            if (in_array($field, self::SECRET_FIELDS, true) || preg_match(self::SECRET_PATTERN, $field)) {
                unset($credentials[$field]);
            }
        }

        return $credentials;
    }
}
