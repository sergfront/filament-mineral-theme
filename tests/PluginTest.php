<?php

use Filament\Panel;
use Filament\Support\Colors\Color;
use Filament\Support\Colors\ColorManager;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Support\Facades\Log;
use SerhiiAntoniuk\Mineral\Edition;
use SerhiiAntoniuk\Mineral\Enums\Density;
use SerhiiAntoniuk\Mineral\Enums\Palette;
use SerhiiAntoniuk\Mineral\Enums\SidebarStyle;
use SerhiiAntoniuk\Mineral\MineralThemePlugin;

it('registers a panel and resolves configuration overrides', function () {
    config()->set('mineral-theme.font', 'Manrope');
    $plugin = MineralThemePlugin::make();
    expect($plugin->getFont())->toBe('Manrope');
    $panel = Panel::make()->id('test')->plugin($plugin->font('Inter'));
    expect($panel->hasPlugin('mineral-theme'))->toBeTrue();
    expect($panel->getColors()['primary'])->toBe(Palette::Default->primary());
    expect($plugin->getFont())->toBe('Inter');
});
it('has complete light and dark palettes', function () {
    foreach (Palette::cases() as $palette) {
        $css = file_get_contents(__DIR__.'/../resources/css/palettes/'.$palette->value.'.css');
        foreach (Palette::REQUIRED_VARS as $variable) {
            expect(substr_count($css, '--mineral-'.$variable.':'))->toBe(2);
        }
    }
});
it('applies pro options or ignores them with one warning in Lite', function () {
    if (! Edition::PRO) {
        Log::shouldReceive('warning')->once();
    }
    $plugin = MineralThemePlugin::make()->density(Density::Compact)->sidebar(SidebarStyle::Floating)->previewPage();
    expect($plugin->getDensity())->toBe(Edition::PRO ? Density::Compact : Density::Default);
    expect($plugin->getSidebar())->toBe(Edition::PRO ? SidebarStyle::Floating : SidebarStyle::Standard);
    expect($plugin->hasPreviewPage())->toBe(Edition::PRO);
});
it('does not share mutable options between instances', function () {
    MineralThemePlugin::make()->font('Manrope');
    expect(MineralThemePlugin::make()->getFont())->toBe('Inter');
});

it('keeps preview overrides in the correct panel and validates session values', function () {
    if (! Edition::PRO) {
        $this->markTestSkipped('Preview is not included in Lite.');
    }
    $session = app('session')->driver();
    request()->setLaravelSession($session);
    $session->put('mineral.first', ['palette' => 'ocean', 'density' => 'compact', 'sidebar' => 'floating']);
    $session->put('mineral.second', ['palette' => 'not-a-palette']);
    $plugin = MineralThemePlugin::make()->previewPage();
    expect($plugin->settings(Panel::make()->id('first')))->toBe(['palette' => 'ocean', 'density' => 'compact', 'sidebar' => 'floating']);
    expect($plugin->settings(Panel::make()->id('second'))['palette'])->toBe('default');
    expect(MineralThemePlugin::make()->settings(Panel::make()->id('first'))['palette'])->toBe('default');
});

it('lets explicit options override config defaults', function () {
    if (! Edition::PRO) {
        $this->markTestSkipped('Extra palettes are not included in Lite.');
    }
    config()->set('mineral-theme.palette', 'rose');
    expect(MineralThemePlugin::make()->getPalette())->toBe(Palette::Rose);
    expect(MineralThemePlugin::make()->palette(Palette::Ocean)->getPalette())->toBe(Palette::Ocean);
});

it('resolves primary colors after the session starts', function () {
    if (! Edition::PRO) {
        $this->markTestSkipped('Preview is not included in Lite.');
    }
    $manager = new ColorManager;
    FilamentColor::swap($manager);
    request()->setLaravelSession(app('session')->driver());
    session()->forget('mineral.late');
    $plugin = MineralThemePlugin::make()->previewPage();
    $plugin->boot(Panel::make()->id('late'));
    session()->put('mineral.late.palette', 'ocean');
    expect($manager->getColors()['primary'])->toBe(Color::generatePalette(Palette::Ocean->primary()));
});
