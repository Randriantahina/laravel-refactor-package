<?php

use Shan\LaravelRefactor\DTOs\ReferenceMatch;
use Shan\LaravelRefactor\Scanners\PhpFileScanner;

beforeEach(function () {
    $this->tmpFile = tempnam(sys_get_temp_dir(), 'refactor_types_').'.php';
});

afterEach(function () {
    if (file_exists($this->tmpFile)) {
        unlink($this->tmpFile);
    }
});

function writeTypesPhp(string $path, string $code): void
{
    file_put_contents($path, "<?php\n\nuse App\\Models\\User;\n\n".$code);
}

it('detects nullable return type ?User', function () {
    writeTypesPhp($this->tmpFile, 'function find(): ?User { return null; }');

    $matches = (new PhpFileScanner())->scan($this->tmpFile, 'App\\Models\\User');

    $types = array_column($matches, 'type');
    expect($types)->toContain(ReferenceMatch::TYPE_TYPEHINT);
});

it('detects union return type User|null', function () {
    writeTypesPhp($this->tmpFile, 'function find(): User|null { return null; }');

    $matches = (new PhpFileScanner())->scan($this->tmpFile, 'App\\Models\\User');

    $types = array_column($matches, 'type');
    expect($types)->toContain(ReferenceMatch::TYPE_TYPEHINT);
});

it('detects nullable parameter type ?User', function () {
    writeTypesPhp($this->tmpFile, 'function foo(?User $u): void {}');

    $matches = (new PhpFileScanner())->scan($this->tmpFile, 'App\\Models\\User');

    $types = array_column($matches, 'type');
    expect($types)->toContain(ReferenceMatch::TYPE_TYPEHINT);
});

it('detects intersection parameter type User&Loggable', function () {
    writeTypesPhp($this->tmpFile, "use App\\Contracts\\Loggable;\nfunction foo(User&Loggable \$u): void {}");

    $matches = (new PhpFileScanner())->scan($this->tmpFile, 'App\\Models\\User');

    $types = array_column($matches, 'type');
    expect($types)->toContain(ReferenceMatch::TYPE_TYPEHINT);
});

it('detects union type in property declaration', function () {
    writeTypesPhp($this->tmpFile, 'class Foo { private User|null $user; }');

    $matches = (new PhpFileScanner())->scan($this->tmpFile, 'App\\Models\\User');

    $types = array_column($matches, 'type');
    expect($types)->toContain(ReferenceMatch::TYPE_TYPEHINT);
});

it('detects type hint in closure', function () {
    writeTypesPhp($this->tmpFile, '$fn = function(User $u): User { return $u; };');

    $matches = (new PhpFileScanner())->scan($this->tmpFile, 'App\\Models\\User');

    // Should detect both param and return type
    $typehints = array_filter($matches, fn ($m) => $m->type === ReferenceMatch::TYPE_TYPEHINT);
    expect(count($typehints))->toBeGreaterThanOrEqual(2);
});

it('detects type hint in arrow function', function () {
    writeTypesPhp($this->tmpFile, '$fn = fn(User $u): User => $u;');

    $matches = (new PhpFileScanner())->scan($this->tmpFile, 'App\\Models\\User');

    $typehints = array_filter($matches, fn ($m) => $m->type === ReferenceMatch::TYPE_TYPEHINT);
    expect(count($typehints))->toBeGreaterThanOrEqual(2);
});
