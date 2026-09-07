<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use JeffersonGoncalves\GA4\GA4Client;

it('runs a report and returns the decoded payload', function () {
    Http::fake([
        'analyticsdata.googleapis.com/*' => Http::response(['rows' => [['dimensionValues' => [['value' => 'US']]]]], 200),
    ]);

    $result = GA4Client::runReport('123456789', ['country'], ['activeUsers']);

    expect($result)->toBe(['rows' => [['dimensionValues' => [['value' => 'US']]]]]);

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://analyticsdata.googleapis.com/v1beta/properties/123456789:runReport'
            && $request->hasHeader('Authorization', 'Bearer fake-access-token')
            && $request['dateRanges'] === [['startDate' => '30daysAgo', 'endDate' => 'today']]
            && $request['dimensions'] === [['name' => 'country']]
            && $request['metrics'] === [['name' => 'activeUsers']];
    });
});

it('runs a report without an access token configured', function () {
    config()->set('ga4.access_token', null);

    Http::fake([
        'analyticsdata.googleapis.com/*' => Http::response(['rows' => []], 200),
    ]);

    expect(GA4Client::runReport('123456789'))->toBe(['rows' => []]);

    Http::assertSent(fn (Request $request) => ! $request->hasHeader('Authorization'));
});

it('returns null from runReport on a non-2xx', function () {
    Http::fake([
        'analyticsdata.googleapis.com/*' => Http::response('', 403),
    ]);

    expect(GA4Client::runReport('123456789'))->toBeNull();
});

it('runs a realtime report without a date range', function () {
    Http::fake([
        'analyticsdata.googleapis.com/*' => Http::response(['rows' => []], 200),
    ]);

    expect(GA4Client::runRealtimeReport('123456789', ['country']))->toBe(['rows' => []]);

    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), ':runRealtimeReport')
            && ! isset($request['dateRanges'])
            && $request['dimensions'] === [['name' => 'country']];
    });
});

it('lists conversion events', function () {
    Http::fake([
        'analyticsadmin.googleapis.com/*' => Http::response([
            'conversionEvents' => [
                ['name' => 'properties/123456789/conversionEvents/1', 'eventName' => 'purchase'],
            ],
        ], 200),
    ]);

    expect(GA4Client::listConversionEvents('123456789'))
        ->toBe([['name' => 'properties/123456789/conversionEvents/1', 'eventName' => 'purchase']]);
});

it('returns an empty array when listing conversion events fails', function () {
    Http::fake([
        'analyticsadmin.googleapis.com/*' => Http::response('', 500),
    ]);

    expect(GA4Client::listConversionEvents('123456789'))->toBe([]);
});

it('creates a conversion event', function () {
    Http::fake([
        'analyticsadmin.googleapis.com/*' => Http::response(['eventName' => 'purchase'], 200),
    ]);

    expect(GA4Client::createConversionEvent('123456789', 'purchase'))->toBe(['eventName' => 'purchase']);

    Http::assertSent(fn (Request $request) => $request['eventName'] === 'purchase');
});

it('normalizes a properties/-prefixed property id', function () {
    Http::fake([
        'analyticsadmin.googleapis.com/*' => Http::response(['eventName' => 'purchase'], 200),
    ]);

    GA4Client::createConversionEvent('properties/123456789', 'purchase');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/properties/123456789/conversionEvents'));
});

it('sends a measurement protocol event', function () {
    Http::fake([
        'www.google-analytics.com/mp/collect*' => Http::response('', 204),
    ]);

    expect(GA4Client::sendEvent('client-1', 'purchase', ['value' => 10]))->toBeTrue();

    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), 'measurement_id=G-FAKE123')
            && str_contains($request->url(), 'api_secret=fake-api-secret')
            && $request['client_id'] === 'client-1'
            && $request['events'] === [['name' => 'purchase', 'params' => ['value' => 10]]];
    });
});

it('overrides the configured measurement id and api secret', function () {
    Http::fake([
        'www.google-analytics.com/mp/collect*' => Http::response('', 204),
    ]);

    GA4Client::sendEvent('client-1', 'purchase', [], 'G-OVERRIDE', 'override-secret');

    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), 'measurement_id=G-OVERRIDE')
            && str_contains($request->url(), 'api_secret=override-secret');
    });
});

it('returns false when sendEvent has no measurement id or api secret configured', function () {
    config()->set('ga4.measurement_id', null);
    config()->set('ga4.api_secret', null);

    expect(GA4Client::sendEvent('client-1', 'purchase'))->toBeFalse();
});

it('returns false when sendEvent receives a non-2xx', function () {
    Http::fake([
        'www.google-analytics.com/mp/collect*' => Http::response('', 400),
    ]);

    expect(GA4Client::sendEvent('client-1', 'purchase'))->toBeFalse();
});

it('rejects an invalid property id', function () {
    expect(fn () => GA4Client::runReport('not-a-property-id'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects an empty property id', function () {
    expect(fn () => GA4Client::listConversionEvents(''))
        ->toThrow(InvalidArgumentException::class);
});
