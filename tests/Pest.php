<?php

use Illuminate\Support\Facades\Http;
use JeffersonGoncalves\GA4\Tests\TestCase;

uses(TestCase::class)
    ->beforeEach(fn () => Http::preventStrayRequests())
    ->in('Feature');
