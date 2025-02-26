<?php declare(strict_types=1);

namespace Radebatz\TypeInfoExtras\Tests\Type;

use PhpParser\Node\UnionType;
use PHPUnit\Framework\TestCase;
use Radebatz\TypeInfoExtras\Type\IntRangeType;
use Radebatz\TypeInfoExtras\Type\Type;

class IntRangeTypeTest extends TestCase
{
    public function testToString()
    {
        $this->assertSame('int<0, max>', (string) new IntRangeType(from: 0, explicitType: 'int<0, max>'));
        $this->assertSame('int<min, 0>', (string) new IntRangeType(to: 0, explicitType: 'int<min, 0>'));
        $this->assertSame('int<1, max>', (string) new IntRangeType(from: 1, explicitType: 'int<1, max>'));
        $this->assertSame('int<-3, 5>', (string) new IntRangeType(from: -3, to: 5, explicitType: 'int<-3, 5>'));
        $this->assertSame('int<min, -1>', (string) new IntRangeType(to: -1, explicitType: 'int<min, -1>'));
        $this->assertSame('int<-3, 5>', (string) new IntRangeType(from: -3, to: 5, explicitType: 'int<-3, 5>'));
        $this->assertSame('int<min, max>', (string) new IntRangeType(explicitType: 'int<min, max>'));
        $this->assertSame('int<min, 5>', (string) new IntRangeType(to: 5, explicitType: 'int<min, 5>'));
    }

    public function testAccepts()
    {
        $this->assertFalse((new IntRangeType(from: 0, to: 5, explicitType: 'int<0, 5>'))->accepts('string'));
        $this->assertFalse((new IntRangeType(from: 0, to: 5, explicitType: 'int<0, 5>'))->accepts([]));

        $this->assertFalse((new IntRangeType(from: -1, to: 5, explicitType: 'int<1, 5>'))->accepts(-3));
        $this->assertTrue((new IntRangeType(from: -1, to: 5, explicitType: 'int<1, 5>'))->accepts(0));
        $this->assertTrue((new IntRangeType(from: -1, to: 5, explicitType: 'int<1, 5>'))->accepts(2));
        $this->assertFalse((new IntRangeType(from: -1, to: 5, explicitType: 'int<1, 5>'))->accepts(6));

        if (method_exists(UnionType::class, 'accepts')) {
            $this->assertFalse(Type::union(Type::intRange(to: -1, explicitType: 'int<min, -1>'), Type::intRange(from: 1, explicitType: 'int<1, max>'))->accepts(0));
        }
    }
}
