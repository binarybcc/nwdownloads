<?php

namespace NWDownloads\Tests\Unit;

use PHPUnit\Framework\TestCase;
use DateTime;

/**
 * Test week boundary calculations
 *
 * Ensures week number calculation is consistent between upload.php and api.php
 */
class WeekBoundariesTest extends TestCase
{
    /**
     * Test that getWeekBoundaries returns correct Sunday-Saturday range
     */
    public function testGetWeekBoundariesReturnsSundayToSaturday(): void
    {
        // Load the function from api.php
        require_once PROJECT_ROOT . '/web/api.php';

        // Test with a Wednesday (should return Sunday before it and Saturday after)
        $result = getWeekBoundaries('2025-12-03'); // Wednesday

        $this->assertEquals('2025-11-30', $result['start']); // Sunday
        $this->assertEquals('2025-12-06', $result['end']);   // Saturday
    }

    /**
     * Test week number calculation for Monday snapshots
     * Monday snapshots should represent the previous week's data
     */
    public function testMondaySnapshotRepresentsPreviousWeek(): void
    {
        require_once PROJECT_ROOT . '/web/api.php';

        // Nov 24, 2025 is a Monday
        // It should be assigned to Week 47 (the week that just ended)
        $result = getWeekBoundaries('2025-11-24');

        $this->assertEquals(47, $result['week_num']);
        $this->assertEquals(2025, $result['year']);
    }

    /**
     * Test week number calculation for Saturday snapshots
     */
    public function testSaturdaySnapshotWeekNumber(): void
    {
        require_once PROJECT_ROOT . '/web/api.php';

        // Dec 6, 2025 is a Saturday
        // It should be Week 49
        $result = getWeekBoundaries('2025-12-06');

        $this->assertEquals(49, $result['week_num']);
        $this->assertEquals(2025, $result['year']);
    }

    /**
     * Test year boundary handling
     */
    public function testYearBoundaryWeekNumbers(): void
    {
        require_once PROJECT_ROOT . '/web/api.php';

        // Week 1 of 2026 (early January)
        $result = getWeekBoundaries('2026-01-04'); // Sunday

        $this->assertEquals(1, $result['week_num']);
        $this->assertEquals(2026, $result['year']);

        // Week 52 or 53 of 2025 (late December)
        $result2 = getWeekBoundaries('2025-12-28'); // Sunday

        $this->assertGreaterThanOrEqual(52, $result2['week_num']);
        $this->assertEquals(2025, $result2['year']);
    }

    /**
     * Test that all days in a week return the same boundaries
     */
    public function testAllDaysInWeekHaveSameBoundaries(): void
    {
        require_once PROJECT_ROOT . '/web/api.php';

        // Test all 7 days in Week 49 (Nov 30 - Dec 6, 2025)
        $dates = [
            '2025-11-30', // Sunday
            '2025-12-01', // Monday
            '2025-12-02', // Tuesday
            '2025-12-03', // Wednesday
            '2025-12-04', // Thursday
            '2025-12-05', // Friday
            '2025-12-06', // Saturday
        ];

        $expectedStart = '2025-11-30';
        $expectedEnd = '2025-12-06';

        foreach ($dates as $date) {
            $result = getWeekBoundaries($date);
            $this->assertEquals($expectedStart, $result['start'], "Failed for $date");
            $this->assertEquals($expectedEnd, $result['end'], "Failed for $date");
        }
    }
}
