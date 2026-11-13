<?php

namespace SerhiiAntoniuk\Mineral\Tests;

use SerhiiAntoniuk\Mineral\MineralThemeServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app): array
    {
        return [MineralThemeServiceProvider::class];
    }
}
