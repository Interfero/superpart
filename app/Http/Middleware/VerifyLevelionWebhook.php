<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HMAC вебхуков CRM→SP.
 * Dual-compat: legacy body HMAC или FR-SYNC-05 (timestamp + event_id + body_hash).
 * Ротация: LEVELION_API_SECRET + LEVELION_API_SECRET_PREVIOUS.
 */
class VerifyLevelionWebhook
{
    private const MAX_SKEW_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = config('services.levelion.api_key');
        $apiSecret = (string) config('services.levelion.api_secret');
        $apiSecretPrevious = (string) config('services.levelion.api_secret_previous', '');

        // Без ключей вебхуки всегда закрыты (в т.ч. local/staging) — иначе charge без auth.
        if (empty($apiKey) || $apiSecret === '') {
            return response()->json(['message' => 'Интеграция не настроена'], 503);
        }

        $headerKey = (string) $request->header('X-API-Key', '');
        if (! hash_equals((string) $apiKey, $headerKey)) {
            return response()->json(['message' => 'Неверный API-ключ'], 401);
        }

        $rawBody = $request->getContent();
        $sign = (string) $request->header('X-Signature', '');
        $timestamp = (string) $request->header('X-Timestamp', '');
        $eventId = (string) $request->header('X-Event-ID', '');

        $secrets = array_values(array_filter([$apiSecret, $apiSecretPrevious], fn ($s) => is_string($s) && $s !== ''));

        if ($timestamp !== '' && $eventId !== '') {
            if (! ctype_digit($timestamp)) {
                return response()->json(['message' => 'Неверный timestamp'], 401);
            }
            $skew = abs(time() - (int) $timestamp);
            if ($skew > self::MAX_SKEW_SECONDS) {
                return response()->json(['message' => 'Timestamp вне окна'], 401);
            }

            $bodyHash = hash('sha256', $rawBody);
            $payload = $timestamp."\n".$eventId."\n".$bodyHash;
            if (! $this->signatureMatchesAny($payload, $sign, $secrets)) {
                return response()->json(['message' => 'Неверная подпись'], 401);
            }

            return $next($request);
        }

        // Legacy: HMAC от сырого тела (partner-order-created / order-completed / sources).
        if (! $this->signatureMatchesAny($rawBody, $sign, $secrets)) {
            return response()->json(['message' => 'Неверная подпись'], 401);
        }

        return $next($request);
    }

    /**
     * @param  list<string>  $secrets
     */
    private function signatureMatchesAny(string $payload, string $sign, array $secrets): bool
    {
        foreach ($secrets as $secret) {
            $expected = hash_hmac('sha256', $payload, $secret);
            if (hash_equals($expected, $sign)) {
                return true;
            }
        }

        return false;
    }
}
