<?php

namespace Shan\LaravelRefactor\Scanners;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use Shan\LaravelRefactor\DTOs\ReferenceMatch;

class PhpFileScanner
{
    /**
     * Scan a PHP file and return all references to $targetFqcn.
     *
     * @return ReferenceMatch[]
     */
    public function scan(string $filePath, string $targetFqcn): array
    {
        $code = file_get_contents($filePath);

        if ($code === false) {
            return [];
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse($code);
        } catch (Error) {
            return [];
        }

        if ($ast === null) {
            return [];
        }

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver(null, ['preserveOriginalNames' => true, 'replaceNodes' => false]));

        $visitor = new class($targetFqcn) extends NodeVisitorAbstract {
            /** @var ReferenceMatch[] */
            public array $matches = [];

            public function __construct(private readonly string $targetFqcn) {}

            public function enterNode(Node $node): null
            {
                $fqcn = ltrim($this->targetFqcn, '\\');

                // use App\Models\User;
                if ($node instanceof Stmt\Use_) {
                    foreach ($node->uses as $use) {
                        if ($this->nameMatches($use->name, $fqcn)) {
                            $this->matches[] = new ReferenceMatch(
                                file: '',
                                line: $node->getStartLine(),
                                type: ReferenceMatch::TYPE_USE,
                                matched: $use->name->toString(),
                            );
                        }
                    }
                }

                // use App\Models\{ User, Post }
                if ($node instanceof Stmt\GroupUse) {
                    foreach ($node->uses as $use) {
                        $full = $node->prefix->toString().'\\'.$use->name->toString();
                        if (ltrim($full, '\\') === $fqcn) {
                            $this->matches[] = new ReferenceMatch(
                                file: '',
                                line: $node->getStartLine(),
                                type: ReferenceMatch::TYPE_USE,
                                matched: $full,
                            );
                        }
                    }
                }

                // class Foo extends User / implements Bar, User
                if ($node instanceof Stmt\Class_) {
                    if ($node->extends !== null && $this->nameMatches($node->extends, $fqcn)) {
                        $this->matches[] = new ReferenceMatch(
                            file: '',
                            line: $node->extends->getStartLine(),
                            type: ReferenceMatch::TYPE_EXTENDS,
                            matched: $node->extends->toString(),
                        );
                    }

                    foreach ($node->implements as $iface) {
                        if ($this->nameMatches($iface, $fqcn)) {
                            $this->matches[] = new ReferenceMatch(
                                file: '',
                                line: $iface->getStartLine(),
                                type: ReferenceMatch::TYPE_IMPLEMENTS,
                                matched: $iface->toString(),
                            );
                        }
                    }
                }

                // interface Foo extends User
                if ($node instanceof Stmt\Interface_) {
                    foreach ($node->extends as $iface) {
                        if ($this->nameMatches($iface, $fqcn)) {
                            $this->matches[] = new ReferenceMatch(
                                file: '',
                                line: $iface->getStartLine(),
                                type: ReferenceMatch::TYPE_EXTENDS,
                                matched: $iface->toString(),
                            );
                        }
                    }
                }

                // new User() / new \App\Models\User()
                if ($node instanceof Expr\New_
                    && $node->class instanceof Name
                    && $this->nameMatches($node->class, $fqcn)) {
                    $this->matches[] = new ReferenceMatch(
                        file: '',
                        line: $node->getStartLine(),
                        type: ReferenceMatch::TYPE_NEW,
                        matched: $node->class->toString(),
                    );
                }

                // Type hints: function foo(User $u) / property User $user
                if ($node instanceof Node\Param && $node->type instanceof Name) {
                    if ($this->nameMatches($node->type, $fqcn)) {
                        $this->matches[] = new ReferenceMatch(
                            file: '',
                            line: $node->type->getStartLine(),
                            type: ReferenceMatch::TYPE_TYPEHINT,
                            matched: $node->type->toString(),
                        );
                    }
                }

                if ($node instanceof Stmt\Property) {
                    if ($node->type instanceof Name && $this->nameMatches($node->type, $fqcn)) {
                        $this->matches[] = new ReferenceMatch(
                            file: '',
                            line: $node->type->getStartLine(),
                            type: ReferenceMatch::TYPE_TYPEHINT,
                            matched: $node->type->toString(),
                        );
                    }
                }

                // Return types
                if (($node instanceof Stmt\Function_
                    || $node instanceof Stmt\ClassMethod)
                    && $node->returnType instanceof Name
                    && $this->nameMatches($node->returnType, $fqcn)) {
                    $this->matches[] = new ReferenceMatch(
                        file: '',
                        line: $node->returnType->getStartLine(),
                        type: ReferenceMatch::TYPE_TYPEHINT,
                        matched: $node->returnType->toString(),
                    );
                }

                // User::class / User::staticMethod()
                if ($node instanceof Expr\ClassConstFetch
                    && $node->class instanceof Name
                    && $this->nameMatches($node->class, $fqcn)) {
                    $type = ($node->name instanceof Node\Identifier && $node->name->name === 'class')
                        ? ReferenceMatch::TYPE_CLASS_CONST
                        : ReferenceMatch::TYPE_STATIC;

                    $this->matches[] = new ReferenceMatch(
                        file: '',
                        line: $node->getStartLine(),
                        type: $type,
                        matched: $node->class->toString(),
                    );
                }

                if ($node instanceof Expr\StaticCall
                    && $node->class instanceof Name
                    && $this->nameMatches($node->class, $fqcn)) {
                    $this->matches[] = new ReferenceMatch(
                        file: '',
                        line: $node->getStartLine(),
                        type: ReferenceMatch::TYPE_STATIC,
                        matched: $node->class->toString(),
                    );
                }

                if ($node instanceof Expr\StaticPropertyFetch
                    && $node->class instanceof Name
                    && $this->nameMatches($node->class, $fqcn)) {
                    $this->matches[] = new ReferenceMatch(
                        file: '',
                        line: $node->getStartLine(),
                        type: ReferenceMatch::TYPE_STATIC,
                        matched: $node->class->toString(),
                    );
                }

                return null;
            }

            private function nameMatches(Name $name, string $fqcn): bool
            {
                // Check resolved name first (setAttribute by NameResolver)
                $resolved = $name->getAttribute('resolvedName');

                if ($resolved instanceof Name) {
                    return ltrim($resolved->toString(), '\\') === $fqcn;
                }

                return ltrim($name->toString(), '\\') === $fqcn;
            }
        };

        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        // Attach the real file path to each match
        return array_map(
            fn (ReferenceMatch $m) => new ReferenceMatch($filePath, $m->line, $m->type, $m->matched),
            $visitor->matches,
        );
    }
}
