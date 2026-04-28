<?php

use Shan\LaravelRefactor\Services\NamespaceResolver;

beforeEach(function () {
    // Create a temp base path with a composer.json for testing
    $this->basePath = sys_get_temp_dir().'/laravel-refactor-test-'.uniqid();
    mkdir($this->basePath);

    file_put_contents($this->basePath.'/composer.json', json_encode([
        'autoload' => [
            'psr-4' => [
                'App\\' => 'app/',
            ],
        ],
    ]));

    $this->resolver = new NamespaceResolver($this->basePath);
});

afterEach(function () {
    unlink($this->basePath.'/composer.json');
    rmdir($this->basePath);
});

it('converts FQCN to file path', function () {
    $path = $this->resolver->fqcnToPath('App\\Models\\User');
    expect($path)->toEndWith('app'.DIRECTORY_SEPARATOR.'Models'.DIRECTORY_SEPARATOR.'User.php');
});

it('converts file path to FQCN', function () {
    $filePath = $this->basePath.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Models'.DIRECTORY_SEPARATOR.'User.php';
    $fqcn = $this->resolver->pathToFqcn($filePath);
    expect($fqcn)->toBe('App\\Models\\User');
});

it('returns null for unknown FQCN prefix', function () {
    $path = $this->resolver->fqcnToPath('Vendor\\Package\\SomeClass');
    expect($path)->toBeNull();
});
