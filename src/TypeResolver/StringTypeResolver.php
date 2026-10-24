<?php declare(strict_types=1);

namespace Radebatz\TypeInfoExtras\TypeResolver;

use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprIntegerNode;
use PHPStan\PhpDocParser\Ast\Type\ConstTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use Radebatz\TypeInfoExtras\Type\ExplicitType;
use Radebatz\TypeInfoExtras\Type\IntRangeType;
use Radebatz\TypeInfoExtras\Type\Type;
use Symfony\Component\TypeInfo\Type as BaseType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\TypeContext\TypeContext;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Component\TypeInfo\TypeResolver\StringTypeResolver as BaseStringTypeResolver;

/**
 * Resolves type for a given string, keeping the PHPStan types that are more specific than their builtin type.
 *
 * @author Martin Rademacher <mano@radebatz.net>
 */
class StringTypeResolver extends BaseStringTypeResolver
{
    protected function getTypeFromNode(TypeNode $node, ?TypeContext $typeContext): BaseType
    {
        if ($node instanceof IdentifierTypeNode && null !== $type = $this->getExplicitTypeFromIdentifier($node->name)) {
            return $type;
        }

        if ($node instanceof GenericTypeNode) {
            if (\in_array($node->type->name, ['int', 'integer'], true)) {
                return $this->resolveIntRange($node);
            }

            if (\in_array($node->type->name, ['class-string', 'interface-string', 'trait-string'], true)
                && 1 === \count($node->genericTypes)
                && ($objectType = $this->getTypeFromNode($node->genericTypes[0], $typeContext)) instanceof ObjectType) {
                return Type::classLike($node->type->name, $objectType);
            }

            // a collection key has to be a builtin type
            if (2 === \count($node->genericTypes) && ($keyType = $this->getTypeFromNode($node->genericTypes[0], $typeContext)) instanceof ExplicitType) {
                $node = new GenericTypeNode($node->type, [new IdentifierTypeNode($keyType->getTypeIdentifier()->value), $node->genericTypes[1]], $node->variances);
            }
        }

        return parent::getTypeFromNode($node, $typeContext);
    }

    protected function resolveIntRange(GenericTypeNode $node): IntRangeType
    {
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

        $boundaries = array_map(fn (TypeNode $t): int => $getBoundaryFromNode($t), $node->genericTypes);

        return Type::intRange($boundaries[0], $boundaries[1]);
    }

    private function getExplicitTypeFromIdentifier(string $identifier): ?BaseType
    {
        return match ($identifier) {
            'positive-int' => Type::intRange(from: 1, explicitType: $identifier),
            'negative-int' => Type::intRange(to: -1, explicitType: $identifier),
            'non-positive-int' => Type::intRange(to: 0, explicitType: $identifier),
            'non-negative-int' => Type::intRange(from: 0, explicitType: $identifier),
            'non-zero-int' => BaseType::union(Type::intRange(to: -1), Type::intRange(from: 1)),
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
            'html-escaped-string' => Type::explicit(TypeIdentifier::STRING, $identifier),
            default => null,
        };
    }
}
