<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BasicTest extends TestCase
{
    public function testIntField()
    {
        $field = new PhpProtobuf\IntField();

        $bytes = $field->encode(3);
        $this->assertTrue(array_vs_string($bytes, '03'));
        $this->assertEquals(3, $field->decode($bytes));

        $bytes = $field->encode(270);
        $this->assertTrue(array_vs_string($bytes, '8e 02'));
        $this->assertEquals(270, $field->decode($bytes));

        $bytes = $field->encode(86942);
        $this->assertTrue(array_vs_string($bytes, '9e a7 05'));
        $this->assertEquals(86942, $field->decode($bytes));

        $bytes = $field->encode(-1);
        $this->assertTrue(array_vs_string($bytes, 'ff ff ff ff ff ff ff ff ff 01'));
        $this->assertEquals(-1, $field->decode($bytes));

        $bytes = $field->encode(-123456789);
        $this->assertTrue(array_vs_string($bytes, 'eb e5 90 c5 ff ff ff ff ff 01'));
        $this->assertEquals(-123456789, $field->decode($bytes));
    }

    public function testUIntField()
    {
        $field = new PhpProtobuf\UIntField();

        $bytes = $field->encode(3);
        $this->assertTrue(array_vs_string($bytes, '03'));
        $this->assertEquals(3, $field->decode($bytes));

        $bytes = $field->encode(270);
        $this->assertTrue(array_vs_string($bytes, '8e 02'));
        $this->assertEquals(270, $field->decode($bytes));

        $bytes = $field->encode(86942);
        $this->assertTrue(array_vs_string($bytes, '9e a7 05'));
        $this->assertEquals(86942, $field->decode($bytes));
    }

    public function testSIntField()
    {
        $field = new PhpProtobuf\SIntField();

        $bytes = $field->encode(345);
        $this->assertTrue(array_vs_string($bytes, 'b2 05'));
        $this->assertEquals(345, $field->decode($bytes));

        $bytes = $field->encode(270);
        $this->assertTrue(array_vs_string($bytes, '9c 04'));
        $this->assertEquals(270, $field->decode($bytes));

        $bytes = $field->encode(86942);
        $this->assertTrue(array_vs_string($bytes, 'bc ce 0a'));
        $this->assertEquals(86942, $field->decode($bytes));

        $bytes = $field->encode(-123);
        $this->assertTrue(array_vs_string($bytes, 'f5 01'));
        $this->assertEquals(-123, $field->decode($bytes));
    }

    public function testBoolField()
    {
        $field = new PhpProtobuf\BoolField();

        $bytes = $field->encode(true);
        $this->assertTrue(array_vs_string($bytes, '01'));
        $this->assertTrue($field->decode($bytes));
    }

    public function testStringField()
    {
        $field = new PhpProtobuf\StringField();

        $bytes = $field->encode("hello, world!");
        $this->assertTrue(array_vs_string($bytes, '0d 68 65 6c 6c 6f 2c 20 77 6f 72 6c 64 21'));
        $this->assertEquals("hello, world!", $field->decode($bytes));

        $long_text = str_pad("", 1000, "very long string");
        $bytes = $field->encode($long_text);
        $this->assertEquals($long_text, $field->decode($bytes));
    }

    public function testFloatField()
    {
        $field = new PhpProtobuf\FloatField();

        $bytes = $field->encode(0.1);
        $this->assertTrue(array_vs_string($bytes, 'cd cc cc 3d'));
        $this->assertLessThan(0.00000001, $field->decode($bytes) - 0.1);
    }

    public function testDoubleField()
    {
        $field = new PhpProtobuf\DoubleField();
        
        $bytes = $field->encode(0.1);
        $this->assertTrue(array_vs_string($bytes, '9a 99 99 99 99 99 b9 3f'));
        $this->assertLessThan(0.00000001, $field->decode($bytes) - 0.1);
    }

    public function testBytesField()
    {
        $field = new PhpProtobuf\BytesField();

        $bytes = $field->encode([12, 34, 56, 78]);
        $this->assertEquals([12, 34, 56, 78], $field->decode($bytes));

        $big_byte_array = array_fill(0, 1000, 1);
        $bytes = $field->encode($big_byte_array);
        $this->assertEquals($big_byte_array, $field->decode($bytes));
    }
    
    public function testFixed32Field()
    {
        $field = new PhpProtobuf\Fixed32Field();

        $bytes = $field->encode(345);
        $this->assertTrue(array_vs_string($bytes, '59 01 00 00'));
        $this->assertEquals(345, $field->decode($bytes));

        $bytes = $field->encode(270);
        $this->assertTrue(array_vs_string($bytes, '0e 01 00 00'));
        $this->assertEquals(270, $field->decode($bytes));

        $bytes = $field->encode(86942);
        $this->assertTrue(array_vs_string($bytes, '9e 53 01 00'));
        $this->assertEquals(86942, $field->decode($bytes));

        $big_number = 4294967295;
        $bytes = $field->encode($big_number);
        $this->assertTrue(array_vs_string($bytes, 'ff ff ff ff'));
        $this->assertEquals($big_number, $field->decode($bytes));
    }
    
    public function testFixed64Field()
    {
        $field = new PhpProtobuf\Fixed64Field();

        $bytes = $field->encode(345);
        $this->assertTrue(array_vs_string($bytes, '59 01 00 00 00 00 00 00'));
        $this->assertEquals(345, $field->decode($bytes));

        $bytes = $field->encode(270);
        $this->assertTrue(array_vs_string($bytes, '0e 01 00 00 00 00 00 00'));
        $this->assertEquals(270, $field->decode($bytes));

        $bytes = $field->encode(86942);
        $this->assertTrue(array_vs_string($bytes, '9e 53 01 00 00 00 00 00'));
        $this->assertEquals(86942, $field->decode($bytes));

        $big_number = PHP_INT_MAX;
        $bytes = $field->encode($big_number);
        $this->assertTrue(array_vs_string($bytes, 'ff ff ff ff ff ff ff 7f'));
        $this->assertEquals($big_number, $field->decode($bytes));
    }

    public function testSFixed32Field()
    {
        $field = new PhpProtobuf\SFixed32Field();

        $bytes = $field->encode(123);
        $this->assertTrue(array_vs_string($bytes, '7b 00 00 00'));
        $this->assertEquals(123, $field->decode($bytes));

        $bytes = $field->encode(2147483647);
        $this->assertTrue(array_vs_string($bytes, 'ff ff ff 7f'));
        $this->assertEquals(2147483647, $field->decode($bytes));

        $bytes = $field->encode(-2147483648);
        $this->assertTrue(array_vs_string($bytes, '00 00 00 80'));
        $this->assertEquals(-2147483648, $field->decode($bytes));
    }

    public function testSFixed64Field()
    {
        $field = new PhpProtobuf\SFixed64Field();

        $bytes = $field->encode(123);
        $this->assertTrue(array_vs_string($bytes, '7b 00 00 00 00 00 00 00'));
        $this->assertEquals(123, $field->decode($bytes));

        $bytes = $field->encode(PHP_INT_MAX);
        $this->assertTrue(array_vs_string($bytes, 'ff ff ff ff ff ff ff 7f'));
        $this->assertEquals(PHP_INT_MAX, $field->decode($bytes));

        $bytes = $field->encode(PHP_INT_MIN);
        $this->assertTrue(array_vs_string($bytes, '00 00 00 00 00 00 00 80'));
        $this->assertEquals(PHP_INT_MIN, $field->decode($bytes));
    }
}