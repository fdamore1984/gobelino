<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FcmPushService
{
    private const TOKEN_CACHE_KEY = 'fcm:access_token';

    public function sendPollNow(string $fcmToken): void
    {
        try {
            $accessToken = $this->accessToken();
            $projectId = $this->credentials()['project_id'];

            $response = Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                    'message' => [
                        'token' => $fcmToken,
                        'data' => ['type' => 'poll_now'],
                        'android' => ['priority' => 'high'],
                    ],
                ])
                ->throw();

            Log::info('FCM push inviato', ['fcm_message_name' => $response->json('name')]);
        } catch (Throwable $e) {
            Log::warning('FCM push fallito', [
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }

    private function accessToken(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, 3300, function () {
            $credentials = $this->credentials();
            $now = time();

            $jwt = JWT::encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ], $credentials['private_key'], 'RS256');

            $response = Http::asForm()
                ->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ])
                ->throw();

            return $response->json('access_token');
        });
    }

    private function credentials(): array
    {
        $json = config('services.fcm.credentials_json');

        abort_unless($json, 500, 'FIREBASE_CREDENTIALS_JSON non configurata.');

        // Un JSON valido può avere a-capo "strutturali" (formattazione tra
        // le coppie chiave/valore): quelli sono whitespace JSON legittimo e
        // vanno lasciati intatti. Il problema è solo quando compare un vero
        // a-capo DENTRO una stringa (tipicamente il campo private_key
        // incollato con newline reali invece della sequenza \n, comune con
        // copia-incolla da mobile) perché lì rompe il parsing.
        // Sostituendo indiscriminatamente OGNI newline del testo si
        // rompono anche quelli strutturali (diventano un backslash "nudo"
        // fuori da una stringa, non valido in JSON): per questo operiamo
        // solo all'interno dei letterali stringa.
        $sanitized = preg_replace_callback(
            '/"(?:[^"\\\\]|\\\\.)*"/s',
            fn (array $m): string => str_replace(["\r\n", "\r", "\n"], '\\n', $m[0]),
            $json
        );

        return json_decode($sanitized, true, flags: JSON_THROW_ON_ERROR);
    }
}
