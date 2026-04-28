<?php

namespace Shan\LaravelRefactor\Scanners;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt;
use PhpParser\Node\UnionType;
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
                            $this->addMatch($node->getStartLine(), ReferenceMatch::TYPE_USE, $use->name->toString());
                        }
                    }
                }

                // use App\Models\{ User, Post }
                if ($node instanceof Stmt\GroupUse) {
                    foreach ($node->uses as $use) {
                        $full = $node->prefix->toString().'\\'.$use->name->toString();
                        if (ltrim($full, '\\') === $fqcn) {
                            $this->addMatch($node->getStartLine(), ReferenceMatch::TYPE_USE, $full);
                        }
                    }
                }

                // class Foo extends User / implements Bar, User
                if ($node instanceof Stmt\Class_) {
                    if ($node->extends !== null && $this->nameMatches($node->extends, $fqcn)) {
                        $this->addMatch($node->extends->getStartLine(), ReferenceMatch::TYPE_EXTENDS, $node->extends->toString());
                    }
                    foreach ($node->implements as $iface) {
                        if ($this->nameMatches($iface, $fqcn)) {
                            $this->addMatch($iface->getStartLine(), ReferenceMatch::TYPE_IMPLEMENTS, $iface->toString());
                        }
                    }
                }

                // interface Foo extends User
                if ($node instanceof Stmt\Interface_) {
                    foreach ($node->extends as $iface) {
                        if ($this->nameMatches($iface, $fqcn)) {
                            $this->addMatch($iface->getStartLine(), ReferenceMatch::TYPE_EXTENDS, $iface->toString());
                        }
                    }
                }

                // new User() / new \App\Models\User()
                if ($node instanceof Expr\New_
                    && $node->class instanceof Name
                    && $this->nameMatches($node->class, $fqcn)) {
                    $this->addMatch($node->getStartLine(), ReferenceMatch::TYPE_NEW, $node->class->toString());
                }

                // Parameter type hints: User $u, ?User $u, User|null $u, User&Loggable $u
                if ($node instanceof Node\Param && $node->type !== null) {
                    foreach ($this->extractNames($node->type) as $name) {
                        if ($this->nameMatches($name, $fqcn)) {
                            $this->addMatch($name->getStartLine(), ReferenceMatch::TYPE_TYPEHINT, $name->toString());
                        }
                    }
                }

                // Property type hints: private User $user; private ?User $user; private User|null $user;
                if ($node instanceof Stmt\Property && $node->type !== null) {
                    foreach ($this->extractNames($node->type) as $name) {
                        if ($this->nameMatches($name, $fqcn)) {
                            $this->addMatch($name->getStartLine(), ReferenceMatch::TYPE_TYPEHINT, $name->toString());
                        }
                    }
                }

                // Constructor promoted properties: public function __construct(private User $user)
                // Already covered by Node\Param above (PHP-Parser promotes them as Param nodes too)

                // Return types: : User / : ?User / : User|null / : User&Loggable
                if (($node instanceof Stmt\Function_ || $node instanceof Stmt\ClassMethod)
                    && $node->returnType !== null) {
                    foreach ($this->extractNames($node->returnType) as $name) {
                        if ($this->nameMatches($name, $fqcn)) {
                            $this->addMatch($name->getStartLine(), ReferenceMatch::TYPE_TYPEHINT, $name->toString());
                        }
                    }
                }

                // Arrow function return types and param types
                if ($node instanceof Expr\ArrowFunction) {
                    foreach ($node->params as $param) {
                        if ($param->type !== null) {
                            foreach ($this->extractNames($param->type) as $name) {
                                if ($this->nameMatches($name, $fqcn)) {
                                    $this->addMatch($name->getStartLine(), ReferenceMatch::TYPE_TYPEHINT, $name->toString());
                                }
                            }
                        }
                    }
                    if ($node->returnType !== null) {
                        foreach ($this->extractNames($node->returnType) as $name) {
                            if ($this->nameMatches($name, $fqcn)) {
                                $this->addMatch($name->getStartLine(), ReferenceMatch::TYPE_TYPEHINT, $name->toString());
                            }
                        }
                    }
                }

                // Closure param/return types
                if ($node instanceof Expr\Closure) {
                    foreach ($node->params as $param) {
                        if ($param->type !== null) {
                            foreach ($this->extractNames($param->type) as $name) {
                                if ($this->nameMatches($name, $fqcn)) {
                                    $this->addMatch($name->getStartLine(), ReferenceMatch::TYPE_TYPEHINT, $name->toString());
                                }
                            }
                        }
                    }
                    if ($node->returnType !== null) {
                        foreach ($this->extractNames($node->returnType) as $name) {
                            if ($this->nameMatches($name, $fqcn)) {
                                $this->addMatch($name->getStartLine(), ReferenceMatch::TYPE_TYPEHINT, $name->toString());
                            }
                        }
                    }
                }

                // User::class / User::CONST
                if ($node instanceof Expr\ClassConstFetch && $node->class instanceof Name && $this->nameMatches($node->class, $fqcn)) {
                    $type = ($node->name instanceof Node\Identifier && $node->name->name === 'class')
                        ? ReferenceMatch::TYPE_CLASS_CONST
                        : ReferenceMatch::TYPE_STATIC;
                    $this->addMatch($node->getStartLine(), $type, $node->class->toString());
                }

                // User::method() / User::$prop
                if ($node instanceof Expr\StaticCall && $node->class instanceof Name && $this->nameMatches($node->class, $fqcn)) {
                    $this->addMatch($node->getStartLine(), ReferenceMatch::TYPE_STATIC, $node->class->toString());
                }

                if ($node instanceof Expr\StaticPropertyFetch && $node->class instanceof Name && $this->nameMatches($node->class, $fqcn)) {
                    $this->addMatch($node->getStartLine(), ReferenceMatch::TYPE_STATIC, $node->class->toString());
                }

                return null;
            }

            /**
             * Extract all Name nodes from any type node (simple, nullable, union, intersection).
             *
             * @return Name[]
             */
            private function extractNames(Node $typeNode): array
            {
                if ($typeNode instanceof Name) {
                    return [$typeNode];
                }

                // ?User
                if ($typeNode instanceof NullableType) {
                    return $typeNode->type instanceof Name ? [$typeNode->type] : [];
                }

                // User|null|Post
                if ($typeNode instanceof UnionType) {
                    $names = [];
                    foreach ($typeNode->types as $t) {
                        $names = array_merge($names, $this->extractNames($t));
                    }

                    return $names;
                }

                // User&Loggable
                if ($typeNode instanceof IntersectionType) {
                    $names = [];
                    foreach ($typeNode->types as $t) {
                        if ($t instanceof Name) {
                            $names[] = $t;
                        }
                    }

                    return $names;
                }

                return [];
            }

            private function nameMatches(Name $name, string $fqcn): bool
            {
                $resolved = $name->getAttribute('resolvedName');

                if ($resolved instanceof Name) {
                    return ltrim($resolved->toString(), '\\') === $fqcn;
                }

                return ltrim($name->toString(), '\\') === $fqcn;
            }

            private function addMatch(int $line, string $type, string $matched): void
            {
                $this->matches[] = new ReferenceMatch(file: '', line: $line, type: $type, matched: $matched);
            }
        };

        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return array_map(
            fn (ReferenceMatch $m) => new ReferenceMatch($filePath, $m->line, $m->type, $m->matched),
            $visitor->matches,
        );
    }
}
