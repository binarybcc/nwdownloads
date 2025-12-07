<?php

namespace NWDownloads\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Simple test to verify PHPUnit is working
 */
class SimpleTest extends TestCase
{
    public function testPhpUnitWorks(): void
    {
        $this->assertTrue(true, 'PHPUnit is working correctly');
    }

    public function testBasicMath(): void
    {
        $result = 2 + 2;
        $this->assertEquals(4, $result, '2 + 2 should equal 4');
    }

    public function testStringComparison(): void
    {
        $string = 'Hello, World!';
        $this->assertStringContainsString('World', $string);
    }
}
