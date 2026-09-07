<?php

namespace JeffersonGoncalves\GA4;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class GA4ServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('ga4')
            ->hasConfigFile();
    }
}
