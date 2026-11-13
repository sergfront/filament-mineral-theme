<?php

namespace SerhiiAntoniuk\Mineral;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class MineralThemeServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('mineral-theme')->hasConfigFile()->hasViews();
    }

    public function packageBooted(): void
    {
        FilamentAsset::register([Css::make('mineral', __DIR__.'/../resources/dist/mineral.css')->loadedOnRequest()], package: 'mineral/theme');
    }
}
