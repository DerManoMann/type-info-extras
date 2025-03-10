<?php

/*
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Radebatz\TypeInfoExtras\TypeResolver;

use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprArrayNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprFalseNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprFloatNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprIntegerNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprNullNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprStringNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprTrueNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\CallableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ConstTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IntersectionTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ObjectShapeNode;
use PHPStan\PhpDocParser\Ast\Type\ThisTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use Radebatz\TypeInfoExtras\Type\ExplicitType;
use Radebatz\TypeInfoExtras\Type\Type;
use Symfony\Component\TypeInfo\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Exception\UnsupportedException;
use Symfony\Component\TypeInfo\Type as BaseType;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\TypeContext\TypeContext;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolverInterface;

/**
 * Resolves type for a given string.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 * @author Martin Rademacher <mano@radebatz.net>
 */
final class StringTypeResolver implements TypeResolverInterface
{
    /**
     * @var array<string, bool>
     */
    private static array $classExistCache = [];

    private readonly Lexer $lexer;
    private readonly TypeParser $parser;

    public function __construct(?Lexer $lexer = null, ?TypeParser $parser = null)
    {
        $this->lexer = $lexer ?? new Lexer(new ParserConfig([]));
        $this->parser = $parser ?? new TypeParser($config = new ParserConfig([]), new ConstExprParser($config));
    }

    public function resolve(mixed $subject, ?TypeContext $typeContext = null): BaseType
    {
        if ($subject instanceof \Stringable) {
            $subject = (string) $subject;
        } elseif (!\is_string($subject)) {
            throw new UnsupportedException(\sprintf('Expected subject to be a "string", "%s" given.', get_debug_type($subject)), $subject);
        }

        try {
            $tokens = new TokenIterator($this->lexer->tokenize($subject));
            $node = $this->parser->parse($tokens);

            return $this->getTypeFromNode($node, $typeContext);
        } catch (\DomainException $e) {
            throw new UnsupportedException(\sprintf('Cannot resolve "%s".', $subject), $subject, previous: $e);
        }
    }

    private function getTypeFromNode(TypeNode $node, ?TypeContext $typeContext): BaseType
    {
        $typeIsCollectionObject = fn (BaseType $type): bool => $type->isIdentifiedBy(\Traversable::class) || $type->isIdentifiedBy(\ArrayAccess::class);

        if ($node instanceof CallableTypeNode) {
            return Type::callable();
        }

        if ($node instanceof ArrayTypeNode) {
            return Type::list($this->getTypeFromNode($node->type, $typeContext));
        }

        if ($node instanceof ArrayShapeNode) {
            return Type::array();
        }

        if ($node instanceof ObjectShapeNode) {
            return Type::object();
        }

        if ($node instanceof ThisTypeNode) {
            if (null === $typeContext) {
                throw new InvalidArgumentException(\sprintf('A "%s" must be provided to resolve "$this".', TypeContext::class));
            }

            return Type::object($typeContext->getCalledClass());
        }

        if ($node instanceof ConstTypeNode) {
            return match ($node->constExpr::class) {
                ConstExprArrayNode::class => Type::array(),
                ConstExprFalseNode::class => Type::false(),
                ConstExprFloatNode::class => Type::float(),
                ConstExprIntegerNode::class => Type::int(),
                ConstExprNullNode::class => Type::null(),
                ConstExprStringNode::class => Type::string(),
                ConstExprTrueNode::class => Type::true(),
                default => throw new \DomainException(\sprintf('Unhandled "%s" constant expression.', $node->constExpr::class)),
            };
        }

        if ($node instanceof IdentifierTypeNode) {
            $type = match ($node->name) {
                'bool', 'boolean' => Type::bool(),
                'true' => Type::true(),
                'false' => Type::false(),
                'int', 'integer' => Type::int(),
                'positive-int' => Type::intRange(from: 1, explicitType: $node->name),
                'negative-int' => Type::intRange(to: -1, explicitType: $node->name),
                'non-positive-int' => Type::intRange(to: 0, explicitType: $node->name),
                'non-negative-int' => Type::intRange(from: 0, explicitType: $node->name),
                'non-zero-int' => BaseType::union(Type::intRange(to: -1), Type::intRange(from: 1)),
                'float', 'double' => Type::float(),
                'string' => BaseType::string(),
                'class-string',
                'trait-string',
                'interface-string',
                'callable-string',
                'numeric-string',
                'lowercase-string',
                'non-empty-lowercase-string',
                'non-empty-string',
                'non-falsy-string',
                'truthy-string',
                'literal-string',
                'html-escaped-string' => Type::explicit(TypeIdentifier::STRING, $node->name),
                'resource' => Type::resource(),
                'object' => Type::object(),
                'callable' => Type::callable(),
                'array', 'non-empty-array' => Type::array(),
                'list', 'non-empty-list' => Type::list(),
                'iterable' => Type::iterable(),
                'mixed' => Type::mixed(),
                'null' => Type::null(),
                'array-key' => Type::union(Type::int(), Type::string()),
                'scalar' => Type::union(Type::int(), Type::float(), Type::string(), Type::bool()),
                'number' => Type::union(Type::int(), Type::float()),
                'numeric' => Type::union(Type::int(), Type::float(), Type::string()),
                'self' => $typeContext ? Type::object($typeContext->getDeclaringClass()) : throw new InvalidArgumentException(\sprintf('A "%s" must be provided to resolve "self".', TypeContext::class)),
                'static' => $typeContext ? Type::object($typeContext->getCalledClass()) : throw new InvalidArgumentException(\sprintf('A "%s" must be provided to resolve "static".', TypeContext::class)),
                'parent' => $typeContext ? Type::object($typeContext->getParentClass()) : throw new InvalidArgumentException(\sprintf('A "%s" must be provided to resolve "parent".', TypeContext::class)),
                'void' => Type::void(),
                'never', 'never-return', 'never-returns', 'no-return' => Type::never(),
                default => $this->resolveCustomIdentifier($node->name, $typeContext),
            };

            if ($typeIsCollectionObject($type)) {
                return Type::collection($type);
            }

            return $type;
        }

        if ($node instanceof NullableTypeNode) {
            return Type::nullable($this->getTypeFromNode($node->type, $typeContext));
        }

        if ($node instanceof GenericTypeNode) {
            $type = $this->getTypeFromNode($node->type, $typeContext);

            // handle integer ranges
            if ($type->isIdentifiedBy(TypeIdentifier::INT)) {
                $getBoundaryFromNode = function (TypeNode $node) {
                    if ($node instanceof IdentifierTypeNode) {
                        return match ($node->name) {
                            'min' => \PHP_INT_MIN,
                            'max' => \PHP_INT_MAX,
                            default => throw new \DomainException(\sprintf('Invalid int range value "%s".', $node->name)),
                        };
                    }

                    if ($node instanceof ConstTypeNode && $node->constExpr instanceof ConstExprIntegerNode) {
                        return (int) $node->constExpr->value;
                    }

                    throw new \DomainException(\sprintf('Invalid int range expression "%s".', \get_class($node)));
                };

                $boundaries = array_map(static fn (TypeNode $t): int => $getBoundaryFromNode($t), $node->genericTypes);

                return Type::intRange($boundaries[0], $boundaries[1]);
            }

            $variableTypes = array_map(fn (TypeNode $t): BaseType => $this->getTypeFromNode($t, $typeContext), $node->genericTypes);

            if ($type instanceof CollectionType) {
                $asList = $type->isList();
                $keyType = $type->getCollectionKeyType();
                $type = $type->getWrappedType();

                if ($type instanceof GenericType) {
                    $type = $type->getWrappedType();
                }

                if (1 === \count($variableTypes)) {
                    return new CollectionType(Type::generic($type, $keyType, $variableTypes[0]), $asList);
                } elseif (2 === \count($variableTypes)) {
                    return Type::collection($type, $variableTypes[1], $variableTypes[0], $asList);
                }
            }

            if ($typeIsCollectionObject($type)) {
                return match (\count($variableTypes)) {
                    1 => Type::collection($type, $variableTypes[0]),
                    2 => Type::collection($type, $variableTypes[1], $variableTypes[0]),
                    default => Type::collection($type),
                };
            }

            if ($type instanceof ExplicitType
                && \in_array($type->getExplicitType(), ['class-string', 'interface-string', 'trait-string'], true)
                && 1 === \count($variableTypes) && $variableTypes[0] instanceof ObjectType) {
                return Type::classLike($type->getExplicitType(), $variableTypes[0]);
            }

            if ($type instanceof BuiltinType && TypeIdentifier::ARRAY !== $type->getTypeIdentifier() && TypeIdentifier::ITERABLE !== $type->getTypeIdentifier()) {
                return $type;
            }

            return Type::generic($type, ...$variableTypes);
        }

        if ($node instanceof UnionTypeNode) {
            $types = [];

            foreach ($node->types as $nodeType) {
                $type = $this->getTypeFromNode($nodeType, $typeContext);

                if ($type instanceof BuiltinType && TypeIdentifier::MIXED === $type->getTypeIdentifier()) {
                    return Type::mixed();
                }

                $types[] = $type;
            }

            return Type::union(...$types);
        }

        if ($node instanceof IntersectionTypeNode) {
            return Type::intersection(...array_map(fn (TypeNode $t): BaseType => $this->getTypeFromNode($t, $typeContext), $node->types));
        }

        throw new \DomainException(\sprintf('Unhandled "%s" node.', $node::class));
    }

    private function resolveCustomIdentifier(string $identifier, ?TypeContext $typeContext): BaseType
    {
        $className = $typeContext ? $typeContext->normalize($identifier) : $identifier;

        if (!isset(self::$classExistCache[$className])) {
            self::$classExistCache[$className] = false;

            if (class_exists($className) || interface_exists($className)) {
                self::$classExistCache[$className] = true;
            } else {
                try {
                    new \ReflectionClass($className);
                    self::$classExistCache[$className] = true;
                } catch (\Throwable) {
                }
            }
        }

        if (self::$classExistCache[$className]) {
            if (is_subclass_of($className, \UnitEnum::class)) {
                return Type::enum($className);
            }

            return Type::object($className);
        }

        if (isset($typeContext?->templates[$identifier])) {
            return Type::template($identifier, $typeContext->templates[$identifier]);
        }

        if (isset($typeContext?->typeAliases[$identifier])) {
            return $typeContext->typeAliases[$identifier];
        }

        throw new \DomainException(\sprintf('Unhandled "%s" identifier.', $identifier));
    }
}
