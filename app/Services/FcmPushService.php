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

            Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                    'message' => [
                        'token' => $fcmToken,
                        'data' => ['type' => 'poll_now'],
                        'android' => ['priority' => 'high'],
                    ],
                ])
                ->throw();
        } catch (Throwable $e) {
            Log::warning('FCM push fallito', ['error' => $e->getMessage()]);
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

        // Un JSON a una riga valido non dovrebbe mai contenere un vero
        // a-capo: se compare, è quasi sempre il campo private_key
        // incollato con newline reali invece della sequenza \n
        // (tipico di copia-incolla da mobile). Li normalizziamo prima
        // del decode invece di richiedere un incollaggio perfetto.
        $sanitized = str_replace(["\r\n", "\r", "\n"], '\\n', $json);

        return json_decode($sanitized, true, flags: JSON_THROW_ON_ERROR);
    }
}
