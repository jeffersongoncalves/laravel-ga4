# Laravel GA4

[![Tests](https://github.com/jeffersongoncalves/laravel-ga4/actions/workflows/run-tests.yml/badge.svg)](https://github.com/jeffersongoncalves/laravel-ga4/actions/workflows/run-tests.yml)
[![PHPStan](https://github.com/jeffersongoncalves/laravel-ga4/actions/workflows/phpstan.yml/badge.svg)](https://github.com/jeffersongoncalves/laravel-ga4/actions/workflows/phpstan.yml)
[![Code Style](https://github.com/jeffersongoncalves/laravel-ga4/actions/workflows/fix-php-code-style-issues.yml/badge.svg)](https://github.com/jeffersongoncalves/laravel-ga4/actions/workflows/fix-php-code-style-issues.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/jeffersongoncalves/laravel-ga4.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/laravel-ga4)
[![Total Downloads](https://img.shields.io/packagist/dt/jeffersongoncalves/laravel-ga4.svg?style=flat-square)](https://packagist.org/packages/jeffersongoncalves/laravel-ga4)
[![License](https://img.shields.io/packagist/l/jeffersongoncalves/laravel-ga4.svg?style=flat-square)](LICENSE.md)

A lightweight Google Analytics 4 (GA4) API client for Laravel. It wraps the GA4 Data API, Admin API, and Measurement Protocol behind a small static client, threads your OAuth bearer token, and returns `null`/`[]`/`false` sentinels on ordinary HTTP failures instead of throwing.

## Features

- **`runReport()`** — run a GA4 Data API report over a date range (defaults to `30daysAgo` → `today`)
- **`runRealtimeReport()`** — run a GA4 Data API realtime report (no date range)
- **`listConversionEvents()`** — list the conversion events configured on a property
- **`createConversionEvent()`** — mark an existing event as a conversion
- **`sendEvent()`** — send a fire-and-forget Measurement Protocol event

## Installation

```bash
composer require jeffersongoncalves/laravel-ga4
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="ga4-config"
```

## Configuration

Add to your `.env`:

```env
GA4_ACCESS_TOKEN=ya29.xxxxxxxxxxxxxxxxxxxx
GA4_MEASUREMENT_ID=G-XXXXXXX
GA4_API_SECRET=xxxxxxxxxxxxxxxxxxxxxx
GA4_TIMEOUT=8
```

`GA4_ACCESS_TOKEN` is the OAuth bearer token used to authenticate the Data API and Admin API — see the [GA4 Data API guide](https://developers.google.com/analytics/devguides/reporting/data/v1). `GA4_MEASUREMENT_ID` and `GA4_API_SECRET` identify the data stream used by the Measurement Protocol — see the [Measurement Protocol guide](https://developers.google.com/analytics/devguides/collection/protocol/ga4) for how to create an API secret.

### Config Options

```php
// config/ga4.php
return [
    'access_token' => env('GA4_ACCESS_TOKEN'),
    'measurement_id' => env('GA4_MEASUREMENT_ID'),
    'api_secret' => env('GA4_API_SECRET'),
    'timeout' => (int) env('GA4_TIMEOUT', 8),
];
```

## Usage

```php
use JeffersonGoncalves\GA4\GA4Client;

// Run a report (property id can be a bare numeric id or "properties/123456789")
$report = GA4Client::runReport('123456789', ['country'], ['activeUsers'], '7daysAgo', 'today');

// Realtime report — no date range, always "now"
$realtime = GA4Client::runRealtimeReport('123456789', ['country'], ['activeUsers']);

// List the conversion events configured on a property
$events = GA4Client::listConversionEvents('123456789');

// Mark an existing event as a conversion
$event = GA4Client::createConversionEvent('123456789', 'purchase');

// Send a Measurement Protocol event (fire-and-forget)
$accepted = GA4Client::sendEvent('client-id-123', 'purchase', ['value' => 19.99, 'currency' => 'USD']);

// Override the configured measurement id / api secret per call
$accepted = GA4Client::sendEvent('client-id-123', 'purchase', [], 'G-OTHERSTREAM', 'other-secret');
```

## Testing

```bash
composer test
```

## Static Analysis

```bash
composer analyse
```

## Code Formatting

```bash
composer format
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
