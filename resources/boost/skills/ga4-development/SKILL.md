---
name: ga4-development
description: Development guide for the Laravel GA4 package - a lightweight Google Analytics 4 API client covering the Data API, Admin API, and Measurement Protocol
---

## When to use this skill

- Adding new GA4 Data API or Admin API helpers to `GA4Client`
- Adjusting property id validation/normalization
- Tuning the access token / measurement id / api secret / timeout configuration
- Writing tests for GA4 API interactions with `Http::fake()`
- Understanding the Measurement Protocol's fire-and-forget semantics

## Setup

### Requirements

- PHP 8.2+
- Laravel 11, 12, or 13
- spatie/laravel-package-tools ^1.14.0
- A GA4 OAuth access token (for the Data and Admin APIs) and, optionally, a Measurement Protocol measurement id + API secret

### Installation

```bash
composer require jeffersongoncalves/laravel-ga4
```

### Publish Config

```bash
php artisan vendor:publish --tag=ga4-config
```

### Environment Variables

```env
GA4_ACCESS_TOKEN=ya29.your-oauth-access-token
GA4_MEASUREMENT_ID=G-XXXXXXX
GA4_API_SECRET=your-measurement-protocol-api-secret
GA4_TIMEOUT=8
```

## Architecture

### Namespace Structure

```
JeffersonGoncalves\GA4\
    GA4ServiceProvider     # Registers the config file only
    GA4Client               # Static Data API / Admin API / Measurement Protocol client
```

### Service Provider

The provider is intentionally minimal — it only registers the config file via
`spatie/laravel-package-tools`:

```php
public function configurePackage(Package $package): void
{
    $package
        ->name('ga4')
        ->hasConfigFile();
}
```

The package short name is `ga4`, so the published config lives at
`config/ga4.php` and the publish tag is `ga4-config`.

## Public API

```php
use JeffersonGoncalves\GA4\GA4Client;

// Data API — historical report, defaults to 30daysAgo -> today
GA4Client::runReport('123456789', ['country'], ['activeUsers'], '7daysAgo', 'today');
// array<string,mixed>|null

// Data API — realtime report, no date range
GA4Client::runRealtimeReport('123456789', ['country'], ['activeUsers']);
// array<string,mixed>|null

// Admin API — conversion events
GA4Client::listConversionEvents('123456789');              // list<array<string,mixed>>
GA4Client::createConversionEvent('123456789', 'purchase'); // array<string,mixed>|null

// Measurement Protocol — fire-and-forget event
GA4Client::sendEvent('client-id', 'purchase', ['value' => 19.99]); // bool
```

## Property Id Normalization

`normalizePropertyId()` trims an optional leading `properties/` prefix and
requires the remainder to be purely numeric:

```php
private static function normalizePropertyId(string $propertyId): string
{
    $propertyId = preg_replace('#^properties/#', '', $propertyId) ?? $propertyId;

    if ($propertyId === '' || preg_match('/^\d+$/', $propertyId) !== 1) {
        throw new InvalidArgumentException("Invalid GA4 property id: [{$propertyId}]. Expected a numeric id, optionally prefixed with 'properties/'.");
    }

    return $propertyId;
}
```

Both `'123456789'` and `'properties/123456789'` are accepted; anything else
throws `InvalidArgumentException`.

## Measurement Protocol

`sendEvent()` posts to `https://www.google-analytics.com/mp/collect` with the
measurement id and API secret as query parameters (not OAuth) and treats any
2xx response as accepted — GA answers 204 with an empty body on success, so
the response is never parsed as JSON.

```php
// Uses the configured measurement_id / api_secret
GA4Client::sendEvent('client-id', 'purchase', ['value' => 19.99]);

// Overrides them per call
GA4Client::sendEvent('client-id', 'purchase', [], 'G-OTHERSTREAM', 'other-secret');
```

If neither the arguments nor the config provide a measurement id and API
secret, the call is skipped, logged, and returns `false` without hitting the
network.

## Configuration

```php
// config/ga4.php
return [
    'access_token' => env('GA4_ACCESS_TOKEN'),     // OAuth bearer token for Data + Admin APIs
    'measurement_id' => env('GA4_MEASUREMENT_ID'), // default Measurement Protocol stream id
    'api_secret' => env('GA4_API_SECRET'),          // default Measurement Protocol API secret
    'timeout' => (int) env('GA4_TIMEOUT', 8),
];
```

The token, measurement id, api secret, and timeout are read lazily via
private helpers so config can be overridden at runtime (e.g. in tests).

## Testing Patterns

### Mocking GA4 responses

```php
use Illuminate\Support\Facades\Http;
use JeffersonGoncalves\GA4\GA4Client;

it('runs a report and returns the decoded payload', function () {
    Http::fake([
        'analyticsdata.googleapis.com/*' => Http::response(['rows' => []], 200),
    ]);

    expect(GA4Client::runReport('123456789'))->toBe(['rows' => []]);
});
```

### Asserting the Measurement Protocol query string

```php
it('sends a measurement protocol event', function () {
    Http::fake([
        'www.google-analytics.com/mp/collect*' => Http::response('', 204),
    ]);

    expect(GA4Client::sendEvent('client-1', 'purchase'))->toBeTrue();
});
```

## Dev Commands

```bash
# Run tests
vendor/bin/pest

# Run static analysis (PHPStan level 5 + Larastan)
vendor/bin/phpstan analyse

# Format code (Pint, Laravel preset)
vendor/bin/pint
```
