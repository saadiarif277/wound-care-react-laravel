<?php

namespace Tests\Unit\Services\FieldMapping;

use App\Services\FieldMapping\FieldTransformer;
use PHPUnit\Framework\TestCase;

class FieldTransformerTest extends TestCase
{
    private FieldTransformer $transformer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transformer = new FieldTransformer();
    }

    /** @test */
    public function it_transforms_dates_to_mdy_format()
    {
        $result = $this->transformer->transform('2023-12-25', 'date:m/d/Y');
        $this->assertEquals('12/25/2023', $result);
    }

    /** @test */
    public function it_transforms_dates_to_iso_format()
    {
        $result = $this->transformer->transform('12/25/2023', 'date:Y-m-d');
        $this->assertEquals('2023-12-25', $result);
    }

    /** @test */
    public function it_transforms_us_phone_numbers()
    {
        $result = $this->transformer->transform('1234567890', 'phone:US');
        $this->assertEquals('(123) 456-7890', $result);
    }

    /** @test */
    public function it_transforms_phone_to_e164_format()
    {
        $result = $this->transformer->transform('1234567890', 'phone:E164');
        $this->assertEquals('+11234567890', $result);
    }

    /** @test */
    public function it_transforms_booleans_to_yes_no()
    {
        $this->assertEquals('Yes', $this->transformer->transform(true, 'boolean:yes_no'));
        $this->assertEquals('No', $this->transformer->transform(false, 'boolean:yes_no'));
        $this->assertEquals('Yes', $this->transformer->transform('1', 'boolean:yes_no'));
        $this->assertEquals('No', $this->transformer->transform('0', 'boolean:yes_no'));
    }

    /** @test */
    public function it_transforms_booleans_to_numeric()
    {
        $this->assertEquals(1, $this->transformer->transform(true, 'boolean:1_0'));
        $this->assertEquals(0, $this->transformer->transform(false, 'boolean:1_0'));
        $this->assertEquals(1, $this->transformer->transform('yes', 'boolean:1_0'));
        $this->assertEquals(0, $this->transformer->transform('no', 'boolean:1_0'));
    }

    /** @test */
    public function it_formats_full_address()
    {
        $data = [
            'line1' => '123 Main St',
            'line2' => 'Apt 4B',
            'city' => 'Springfield',
            'state' => 'IL',
            'postal_code' => '62701'
        ];

        $result = $this->transformer->transform($data, 'address:full');
        $expected = '123 Main St, Apt 4B, Springfield, IL, 62701';
        $this->assertEquals($expected, $result);
    }

    /** @test */
    public function it_rounds_numbers_to_integer()
    {
        $this->assertEquals(5, $this->transformer->transform(4.7, 'number:0'));
        $this->assertEquals(4, $this->transformer->transform(4.3, 'number:0'));
    }

    /** @test */
    public function it_rounds_numbers_to_two_decimals()
    {
        $this->assertEquals(4.67, $this->transformer->transform(4.666, 'number:2'));
        $this->assertEquals(4.30, $this->transformer->transform(4.3, 'number:2'));
    }

    /** @test */
    public function it_transforms_text_case()
    {
        $this->assertEquals('HELLO WORLD', $this->transformer->transform('hello world', 'text:upper'));
        $this->assertEquals('hello world', $this->transformer->transform('HELLO WORLD', 'text:lower'));
        $this->assertEquals('Hello World', $this->transformer->transform('hello world', 'text:title'));
    }

    /** @test */
    public function it_returns_original_value_on_transformation_error()
    {
        $result = $this->transformer->transform('invalid-date', 'date:m/d/Y');
        $this->assertEquals('invalid-date', $result);
    }

    /** @test */
    public function it_throws_exception_for_invalid_transformer_format()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->transformer->transform('value', 'invalid-format');
    }

    /** @test */
    public function it_throws_exception_for_unknown_transformer()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->transformer->transform('value', 'unknown:format');
    }

    /** @test */
    public function it_returns_original_value_for_null_transformer()
    {
        $result = $this->transformer->transform('value', null);
        $this->assertEquals('value', $result);
    }

    /** @test */
    public function it_returns_original_value_for_empty_transformer()
    {
        $result = $this->transformer->transform('value', '');
        $this->assertEquals('value', $result);
    }

    /** @test */
    public function it_formats_duration_correctly()
    {
        $data = [
            'wound_duration_years' => 1,
            'wound_duration_months' => 6,
            'wound_duration_weeks' => 2,
            'wound_duration_days' => 3,
        ];

        $result = $this->transformer->formatDuration($data);
        $this->assertEquals('1 year, 6 months, 2 weeks, 3 days', $result);
    }

    /** @test */
    public function it_handles_phone_with_country_code()
    {
        $result = $this->transformer->transform('11234567890', 'phone:US');
        $this->assertEquals('+1 (123) 456-7890', $result);
    }

    /** @test */
    public function it_handles_invalid_phone_numbers()
    {
        $result = $this->transformer->transform('123', 'phone:US');
        $this->assertEquals('123', $result); // Returns original for invalid format
    }

    /** @test */
    public function it_handles_address_line_transformation()
    {
        $data = [
            'line1' => '123 Main St',
            'line2' => 'Suite 200'
        ];

        $result = $this->transformer->transform($data, 'address:line');
        $this->assertEquals('123 Main St Suite 200', $result);
    }
}