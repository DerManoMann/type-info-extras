<?php declare(strict_types=1);

namespace Radebatz\TypeInfoExtras;

use Radebatz\TypeInfoExtras\Type\ExplicitType;
use Radebatz\TypeInfoExtras\Type\IntRangeType;
use Symfony\Component\TypeInfo\TypeFactoryTrait as BaseTypeFactoryTrait;
use Symfony\Component\TypeInfo\TypeIdentifier;

trait TypeFactoryTrait
{
    use BaseTypeFactoryTrait;

    /**
     * @template T of TypeIdentifier
     * @template U value-of<T>
     *
     * @param T|U $identifier
     * @param string $explicitType the explicit type
     *
     * @return ExplicitType<T>
     */
    public static function explicit(TypeIdentifier|string $identifier, string $explicitType): ExplicitType
    {
        return new ExplicitType($identifier, $explicitType);
    }

    public static function intRange(int $from = \PHP_INT_MIN, int $to = \PHP_INT_MAX, ?string $explicitType = null): IntRangeType
    {
        return new IntRangeType($from, $to, $explicitType);
    }
}