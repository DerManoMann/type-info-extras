<?php declare(strict_types=1);

namespace Radebatz\TypeInfoExtras\Tests\TypeResolver;

use PHPUnit\Framework\Attributes\DataProvider;
use Radebatz\TypeInfoExtras\Type\ExplicitType;
use Radebatz\TypeInfoExtras\TypeResolver\StringTypeResolver;
use Stringable;
use Symfony\Component\TypeInfo\Tests\TypeResolver\StringTypeResolverTest as BaseTypeResolverTest;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeContext\TypeContext;
use Symfony\Component\TypeInfo\TypeIdentifier;

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

            yield $key => $set;
        }
    }

    #[DataProvider('extrasResolveDataProvider')]
    public function testResolve(Type $expectedType, string $string, ?TypeContext $typeContext = null)
    {
        $this->assertEquals($expectedType, $this->resolver->resolve($string, $typeContext));
    }

    #[DataProvider('extrasResolveDataProvider')]
    public function testResolveStringable(Type $expectedType, string $string, ?TypeContext $typeContext = null)
    {
        $this->assertEquals($expectedType, $this->resolver->resolve(new class($string) implements Stringable {
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