<?php

namespace SerhiiAntoniuk\Mineral\Enums;

enum Palette: string
{
    case Default = 'default';

    public const REQUIRED_VARS = ['background', 'surface', 'text', 'muted', 'border', 'accent', 'on-accent'];

    public function primary(): string
    {
        return match ($this) {
            self::Default => '#315b50',
        };
    }
}
