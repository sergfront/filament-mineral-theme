# Mineral Lite for Filament

A calm theme with a Default palette in light and dark mode. Requires PHP 8.2+, Laravel 12, Filament 4/5.

![Mineral Lite](art/default-light.png)

Install: `composer require serhii-antoniuk/filament-mineral-theme`, then `php artisan filament:assets`. Register `\SerhiiAntoniuk\Mineral\MineralThemePlugin::make()` with your panel.

Publish defaults with `php artisan vendor:publish --tag=mineral-theme-config`. Fluent font options override config. Pro methods are ignored with one log warning per plugin instance. Only Palette::Default is included.

Development: composer install; npm ci; npm run build; npm run contrast; composer test.

License: MIT. Support: sergefrontend@gmail.com. Repository: https://github.com/sergfront/filament-mineral-theme
