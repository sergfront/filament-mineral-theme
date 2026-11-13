<?php

namespace SerhiiAntoniuk\Mineral;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Facades\FilamentColor;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Log;
use SerhiiAntoniuk\Mineral\Enums\Density;
use SerhiiAntoniuk\Mineral\Enums\Palette;
use SerhiiAntoniuk\Mineral\Enums\SidebarStyle;

class MineralThemePlugin implements Plugin
{
    protected ?Palette $palette = null;

    protected ?Density $density = null;

    protected ?SidebarStyle $sidebar = null;

    protected ?string $font = null;

    protected bool $preview = false;

    protected bool $warned = false;

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'mineral-theme';
    }

    protected function allowPro(): bool
    {
        if (Edition::PRO) {
            return true;
        }
        if (! $this->warned) {
            Log::warning('Mineral Lite: Pro options were ignored.');
            $this->warned = true;
        }

        return false;
    }

    public function palette(Palette $palette): static
    {
        if ($palette === Palette::Default || $this->allowPro()) {
            $this->palette = $palette;
        }

        return $this;
    }

    public function density(Density $density): static
    {
        if ($this->allowPro()) {
            $this->density = $density;
        }

        return $this;
    }

    public function sidebar(SidebarStyle $sidebar): static
    {
        if ($this->allowPro()) {
            $this->sidebar = $sidebar;
        }

        return $this;
    }

    public function font(string $font): static
    {
        $this->font = $font;

        return $this;
    }

    public function previewPage(bool $enabled = true): static
    {
        if ($this->allowPro()) {
            $this->preview = $enabled;
        }

        return $this;
    }

    public function hasPreviewPage(): bool
    {
        return Edition::PRO && $this->preview;
    }

    public function getPalette(): Palette
    {
        return Edition::PRO ? ($this->palette ?? Palette::from(config('mineral-theme.palette', 'default'))) : Palette::Default;
    }

    public function getDensity(): Density
    {
        return Edition::PRO ? ($this->density ?? Density::from(config('mineral-theme.density', 'default'))) : Density::Default;
    }

    public function getSidebar(): SidebarStyle
    {
        return Edition::PRO ? ($this->sidebar ?? SidebarStyle::from(config('mineral-theme.sidebar', 'standard'))) : SidebarStyle::Standard;
    }

    public function getFont(): string
    {
        return $this->font ?? config('mineral-theme.font', 'Inter');
    }

    /** @return array{palette: string, density: string, sidebar: string} */
    public function settings(Panel $panel): array
    {
        $settings = ['palette' => $this->getPalette()->value, 'density' => $this->getDensity()->value, 'sidebar' => $this->getSidebar()->value];
        if ($this->hasPreviewPage() && request()->hasSession()) {
            foreach (['palette' => Palette::class, 'density' => Density::class, 'sidebar' => SidebarStyle::class] as $key => $enum) {
                $value = session()->get('mineral.'.$panel->getId().'.'.$key);
                if (is_string($value) && $enum::tryFrom($value)) {
                    $settings[$key] = $value;
                }
            }
        }

        return $settings;
    }

    public function register(Panel $panel): void
    {
        $panel->colors(['primary' => $this->getPalette()->primary()])
            ->font($this->getFont())
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn () => view('mineral-theme::styles'))
            ->renderHook(PanelsRenderHook::BODY_START, fn () => view('mineral-theme::marker', $this->settings($panel)));
        
    }

    public function boot(Panel $panel): void
    {
        // ColorManager resolves this closure at render time, after StartSession.
        FilamentColor::register(fn (): array => ['primary' => Palette::from($this->settings($panel)['palette'])->primary()]);
    }
}
