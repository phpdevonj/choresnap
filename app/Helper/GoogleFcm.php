<?php

namespace App\Helper;

use Google\Client;

class GoogleFcm {


    private static string $googleServiceJson = 'choresnap-83bea-firebase-adminsdk-fbsvc-ec023624c4.json';

    private static function readServiceFile(): array {
        $fileContent = file_get_contents(base_path(self::$googleServiceJson));
        return json_decode($fileContent, true);
    }

    private static function getToken() {
        $serviceJsonPath = self::readServiceFile();

        $client = new Client();
        try {
            $client->setAuthConfig($serviceJsonPath);
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
            $accessToken = $client->fetchAccessTokenWithAssertion();
            return $accessToken['access_token'];
        } catch (\Google_Exception $e) {
            return null;
        }
    }

    public static function sendNotification($token, $title, $body): bool {

        $accessToken = self::getToken();

        if (!$accessToken) {
            return false;
        }

        $projectId = self::readServiceFile()['project_id'];

        $url = "https://fcm.googleapis.com/v1/projects/" . $projectId . "/messages:send";

        $notification = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body
                ]
            ]
        ];

        $json = json_encode($notification);

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        if ($response === false) {
            curl_close($ch);
            return false;
        }
        curl_close($ch);

        $result = json_decode($response);

        $isError = $result->error ?? false;

        if ($isError) {
            if (env('APP_DEBUG')) {
                logger($response);
            }
            return false;
        }

        return true;
    }

}