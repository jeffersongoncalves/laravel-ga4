## Laravel GA4

### Overview

A lightweight Google Analytics 4 (GA4) API client for Laravel. It wraps the
GA4 Data API, Admin API, and Measurement Protocol behind a small static
`GA4Client`, threads the OAuth bearer token, and returns `null`/`[]`/`false`
sentinels on ordinary HTTP failures instead of throwing.

### Key Concepts

- **GA4Client**: Static client exposing report, conversion-event, and Measurement Protocol helpers
- **Property id normalization**: accepts a bare numeric id or a `properties/`-prefixed id; throws `InvalidArgumentException` on anything else
- **Measurement Protocol**: fire-and-forget telemetry — any 2xx response is treated as accepted, GA answers 204 with no body

### Public API

@verbatim
<code-snippet name="ga4-client" lang="php">
use JeffersonGoncalves\GA4\GA4Client;

GA4Client::runReport('123456789', ['country'], ['activeUsers'], '7daysAgo', 'today'); // array<string,mixed>|null
GA4Client::runRealtimeReport('123456789', ['country'], ['activeUsers']);              // array<string,mixed>|null
GA4Client::listConversionEvents('123456789');                                         // list<array<string,mixed>>
GA4Client::createConversionEvent('123456789', 'purchase');                            // array<string,mixed>|null
GA4Client::sendEvent('client-id', 'purchase', ['value' => 19.99]);                    // bool
</code-snippet>
@endverbatim

### Configuration

@verbatim
<code-snippet name="config-keys" lang="php">
// config/ga4.php
'access_token'   => env('GA4_ACCESS_TOKEN'),
'measurement_id' => env('GA4_MEASUREMENT_ID'),
'api_secret'     => env('GA4_API_SECRET'),
'timeout'        => (int) env('GA4_TIMEOUT', 8),
</code-snippet>
@endverbatim

### Conventions

- All methods are static — there is no facade or container binding
- `runReport()` defaults to a `30daysAgo` → `today` date range when none is passed
- `runRealtimeReport()` never sends a date range — realtime is always "now"
- Network/timeout failures are logged and surfaced as `null`/`[]`/`false`, never thrown
- Only a malformed property id throws (`InvalidArgumentException`)
