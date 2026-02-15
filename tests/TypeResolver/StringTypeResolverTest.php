<?php declare(strict_types=1);

namespace Radebatz\TypeInfoExtras\Tests\TypeResolver;

use PHPUnit\Framework\Attributes\DataProvider;
use Radebatz\TypeInfoExtras\Tests\Fixtures\DummyInterface;
use Radebatz\TypeInfoExtras\Tests\Fixtures\DummyTrait;
use Radebatz\TypeInfoExtras\Type\ExplicitType;
use Radebatz\TypeInfoExtras\Type\IntRangeType;
use Radebatz\TypeInfoExtras\Type\Type;
use Radebatz\TypeInfoExtras\TypeResolver\StringTypeResolver;
use Symfony\Component\TypeInfo\Tests\Fixtures\Dummy;
use Symfony\Component\TypeInfo\Tests\TypeResolver\StringTypeResolverTest as BaseTypeResolverTest;
use Symfony\Component\TypeInfo\Type as BaseType;
use Symfony\Component\TypeInfo\Type\UnionType;
use Symfony\Component\TypeInfo\TypeContext\TypeContext;
use Symfony\Component\TypeInfo\TypeIdentifier;

use function sprintf;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

class StringTypeResolverTest extends BaseTypeResolverTest
{
    protected StringTypeResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new StringTypeResolver();
    }

    public static function extrasResolveDataProvider(): iterable
    {
        foreach (parent::resolveDataProvider() as $key => $set) {
            $typeName = $set[1];
            if (str_ends_with($typeName, '-string')) {
                $set[0] = new ExplicitType(TypeIdentifier::STRING, $typeName);
            }

            $set[0] = match ($typeName) {
                'positive-int' => new IntRangeType(from: 1, to: PHP_INT_MAX, explicitType: $typeName),
                'negative-int' => new IntRangeType(from: PHP_INT_MIN, to: -1, explicitType: $typeName),
                'non-positive-int' => new IntRangeType(from: PHP_INT_MIN, to: 0, explicitType: $typeName),
                'non-negative-int' => new IntRangeType(from: 0, to: PHP_INT_MAX, explicitType: $typeName),
                'non-zero-int' => new UnionType(new IntRangeType(from: PHP_INT_MIN, to: -1), new IntRangeType(from: 1, to: PHP_INT_MAX)),
                'int<0, 100>' => new IntRangeType(from: 0, to: 100),
                default => $set[0],
            };

            yield $key => $set;
        }

        // int range
        yield [Type::intRange(from: 1, explicitType: 'positive-int'), 'positive-int'];
        yield [Type::intRange(from: 0, explicitType: 'non-negative-int'), 'non-negative-int'];
        yield [Type::intRange(to: -1, explicitType: 'negative-int'), 'negative-int'];
        yield [Type::intRange(to: 0, explicitType: 'non-positive-int'), 'non-positive-int'];
        yield [Type::union(Type::intRange(to: -1), Type::intRange(from: 1)), 'non-zero-int'];
        yield [Type::intRange(0, 100), 'int<0, 100>'];
        yield [Type::intRange(), 'int<min, max>'];
        yield [Type::array(Type::bool(), Type::int()), 'array<positive-int, bool>'];

        yield [Type::explicit(TypeIdentifier::STRING, 'class-string'), 'class-string'];
        yield [Type::classLike('class-string', Type::object(Dummy::class)), sprintf('class-string<%s>', Dummy::class)];
        yield [Type::explicit(TypeIdentifier::STRING, 'trait-string'), 'trait-string'];
        yield [Type::classLike('trait-string', Type::object(DummyTrait::class)), sprintf('trait-string<%s>', DummyTrait::class)];
        yield [Type::explicit(TypeIdentifier::STRING, 'interface-string'), 'interface-string'];
        yield [Type::classLike('interface-string', Type::object(DummyInterface::class)), sprintf('interface-string<%s>', DummyInterface::class)];
    }

    #[DataProvider('extrasResolveDataProvider')]
    public function testResolve(BaseType $expectedType, string $string, ?TypeContext $typeContext = null)
    {
        $this->assertEquals($expectedType, $this->resolver->resolve($string, $typeContext));
    }

    #[DataProvider('extrasResolveDataProvider')]
    public function testResolveStringable(BaseType $expectedType, string $string, ?TypeContext $typeContext = null)
    {
        $this->assertEquals($expectedType, $this->resolver->resolve(new class ($string) implements \Stringable {
            public function __construct(private string $value)
            {
            }

            public function __toString(): string
            {
                return $this->value;
            }
        }, $typeContext));
    }
}
