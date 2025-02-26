<?php declare(strict_types=1);

namespace Radebatz\TypeInfoExtras\Tests\Type;

use PHPUnit\Framework\TestCase;
use Radebatz\TypeInfoExtras\Type\ExplicitType;
use Symfony\Component\TypeInfo\TypeIdentifier;

class ExplicitTypeTest extends TestCase
{
    public function testToString()
    {
        $this->assertSame('numeric-int', (string) new ExplicitType(TypeIdentifier::INT, 'numeric-int'));
    }

    public function testIsIdentifiedBy()
    {
        $this->assertFalse((new ExplicitType(TypeIdentifier::INT, 'numeric-int'))->isIdentifiedBy(TypeIdentifier::ARRAY));
        $this->assertTrue((new ExplicitType(TypeIdentifier::INT, 'numeric-int'))->isIdentifiedBy(TypeIdentifier::INT));

        $this->assertTrue((new ExplicitType(TypeIdentifier::INT, 'numeric-int'))->isIdentifiedBy('int'));

        $this->assertTrue((new ExplicitType(TypeIdentifier::INT, 'numeric-int'))->isIdentifiedBy('string', 'int'));
    }

    public function testIsNullable()
    {
        $this->assertFalse((new ExplicitType(TypeIdentifier::INT, 'numeric-int'))->isNullable());
    }
}
