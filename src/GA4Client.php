<?php

namespace JeffersonGoncalves\GA4;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * GA4 HTTP layer. Wraps the Data API (runReport / runRealtimeReport), the
 * Admin API (conversion events), and the Measurement Protocol (sendEvent)
 * behind a small static client, threading the OAuth bearer token and
 * returning null/[]/false sentinels on ordinary HTTP failures instead of
 * throwing.
 */
class GA4Client
{
    private const DATA_API = 'https://analyticsdata.googleapis.com/v1beta';

    private const ADMIN_API = 'https://analyticsadmin.googleapis.com/v1beta';

    private const MEASUREMENT_PROTOCOL = 'https://www.google-analytics.com/mp/collect';

    /**
     * @param  list<string>  $dimensions
     * @param  list<string>  $metrics
     * @return array<string, mixed>|null
     */
    public static function runReport(
        string $propertyId,
        array $dimensions = [],
        array $metrics = [],
        ?string $startDate = null,
        ?string $endDate = null,
    ): ?array {
        $propertyId = self::normalizePropertyId($propertyId);

        $body = [
            'dateRanges' => [[
                'startDate' => $startDate ?? '30daysAgo',
                'endDate' => $endDate ?? 'today',
            ]],
        ];

        if ($dimensions !== []) {
            $body['dimensions'] = self::toNamedList($dimensions);
        }

        if ($metrics !== []) {
            $body['metrics'] = self::toNamedList($metrics);
        }

        $response = self::request('post', self::DATA_API."/properties/{$propertyId}:runReport", 'ga4_run_report', $body);

        return self::jsonOrNull($response);
    }

    /**
     * @param  list<string>  $dimensions
     * @param  list<string>  $metrics
     * @return array<string, mixed>|null
     */
    public static function runRealtimeReport(string $propertyId, array $dimensions = [], array $metrics = []): ?array
    {
        $propertyId = self::normalizePropertyId($propertyId);

        $body = [];

        if ($dimensions !== []) {
            $body['dimensions'] = self::toNamedList($dimensions);
        }

        if ($metrics !== []) {
            $body['metrics'] = self::toNamedList($metrics);
        }

        $response = self::request('post', self::DATA_API."/properties/{$propertyId}:runRealtimeReport", 'ga4_run_realtime_report', $body);

        return self::jsonOrNull($response);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listConversionEvents(string $propertyId): array
    {
        $propertyId = self::normalizePropertyId($propertyId);

        $response = self::request('get', self::ADMIN_API."/properties/{$propertyId}/conversionEvents", 'ga4_list_conversion_events');

        $data = self::jsonOrNull($response);

        if ($data === null) {
            return [];
        }

        $events = $data['conversionEvents'] ?? [];

        return is_array($events) ? array_values($events) : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function createConversionEvent(string $propertyId, string $eventName): ?array
    {
        $propertyId = self::normalizePropertyId($propertyId);

        $response = self::request('post', self::ADMIN_API."/properties/{$propertyId}/conversionEvents", 'ga4_create_conversion_event', [
            'eventName' => $eventName,
        ]);

        return self::jsonOrNull($response);
    }

    /**
     * Fire-and-forget telemetry via the Measurement Protocol. GA answers 204
     * with an empty body on success, so any 2xx is treated as accepted.
     *
     * @param  array<string, mixed>  $params
     */
    public static function sendEvent(
        string $clientId,
        string $eventName,
        array $params = [],
        ?string $measurementId = null,
        ?string $apiSecret = null,
    ): bool {
        $measurementId ??= self::measurementId();
        $apiSecret ??= self::apiSecret();

        if ($measurementId === null || $apiSecret === null) {
            Log::warning('GA4Client sendEvent skipped: missing measurement_id or api_secret');

            return false;
        }

        $url = self::MEASUREMENT_PROTOCOL.'?'.http_build_query([
            'measurement_id' => $measurementId,
            'api_secret' => $apiSecret,
        ]);

        $response = self::request('post', $url, 'ga4_send_event', [
            'client_id' => $clientId,
            'events' => [[
                'name' => $eventName,
                'params' => $params,
            ]],
        ]);

        return $response !== null && $response->successful();
    }

    /**
     * @param  list<string>  $names
     * @return list<array{name: string}>
     */
    private static function toNamedList(array $names): array
    {
        return array_map(static fn (string $name): array => ['name' => $name], $names);
    }

    /**
     * Shared request/response handling: attaches the bearer token (when the
     * request targets a Google API host, not the Measurement Protocol),
     * catches transport failures, and logs them rather than throwing.
     *
     * @param  'get'|'post'  $method
     * @param  array<string, mixed>  $body
     */
    private static function request(string $method, string $url, string $context, array $body = []): ?Response
    {
        $headers = [];

        if (! str_contains($url, self::MEASUREMENT_PROTOCOL) && $token = self::accessToken()) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        try {
            $request = Http::timeout(self::timeout())->withHeaders($headers);

            return $method === 'get' ? $request->get($url) : $request->post($url, $body);
        } catch (Throwable $e) {
            self::logFailure($context, $url, $e);

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function jsonOrNull(?Response $response): ?array
    {
        if ($response === null || ! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return is_array($data) ? $data : null;
    }

    /**
     * Accepts a bare numeric GA4 property id, tolerating a leading
     * `properties/` prefix which is trimmed off.
     *
     * @throws InvalidArgumentException
     */
    private static function normalizePropertyId(string $propertyId): string
    {
        $propertyId = preg_replace('#^properties/#', '', $propertyId) ?? $propertyId;

        if ($propertyId === '' || preg_match('/^\d+$/', $propertyId) !== 1) {
            throw new InvalidArgumentException("Invalid GA4 property id: [{$propertyId}]. Expected a numeric id, optionally prefixed with 'properties/'.");
        }

        return $propertyId;
    }

    private static function logFailure(string $context, string $target, Throwable $e): void
    {
        Log::warning('GA4Client outbound fetch failed', [
            'context' => $context,
            'target' => $target,
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ]);
    }

    private static function accessToken(): ?string
    {
        $token = config('ga4.access_token');

        return is_string($token) && $token !== '' ? $token : null;
    }

    private static function measurementId(): ?string
    {
        $measurementId = config('ga4.measurement_id');

        return is_string($measurementId) && $measurementId !== '' ? $measurementId : null;
    }

    private static function apiSecret(): ?string
    {
        $apiSecret = config('ga4.api_secret');

        return is_string($apiSecret) && $apiSecret !== '' ? $apiSecret : null;
    }

    private static function timeout(): int
    {
        return (int) config('ga4.timeout', 8);
    }
}
