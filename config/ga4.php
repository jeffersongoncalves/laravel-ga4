<?php

return [
    /*
    |--------------------------------------------------------------------------
    | GA4 OAuth Access Token
    |--------------------------------------------------------------------------
    |
    | The OAuth bearer token used to authenticate requests to the GA4 Data API
    | and Admin API. See:
    | https://developers.google.com/analytics/devguides/reporting/data/v1
    |
    */
    'access_token' => env('GA4_ACCESS_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Measurement Protocol Measurement ID
    |--------------------------------------------------------------------------
    |
    | The default GA4 data stream measurement id (G-XXXXXXX) used by
    | sendEvent() when no measurement id is explicitly passed. See:
    | https://developers.google.com/analytics/devguides/collection/protocol/ga4
    |
    */
    'measurement_id' => env('GA4_MEASUREMENT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Measurement Protocol API Secret
    |--------------------------------------------------------------------------
    |
    | The default Measurement Protocol API secret used by sendEvent() when no
    | api secret is explicitly passed.
    |
    */
    'api_secret' => env('GA4_API_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | The number of seconds to wait for a response before giving up.
    |
    */
    'timeout' => (int) env('GA4_TIMEOUT', 8),
];
