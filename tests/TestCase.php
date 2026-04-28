<?php

namespace Shan\LaravelRefactor\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Shan\LaravelRefactor\LaravelRefactorServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [LaravelRefactorServiceProvider::class];
    }
}
