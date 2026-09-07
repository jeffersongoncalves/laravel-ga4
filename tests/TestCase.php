<?php

namespace JeffersonGoncalves\GA4\Tests;

use JeffersonGoncalves\GA4\GA4ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            GA4ServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('ga4.access_token', 'fake-access-token');
        $app['config']->set('ga4.measurement_id', 'G-FAKE123');
        $app['config']->set('ga4.api_secret', 'fake-api-secret');
        $app['config']->set('ga4.timeout', 5);
    }
}
