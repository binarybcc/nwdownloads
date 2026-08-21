<?php

namespace NWDownloads\Tests\Unit;

use PHPUnit\Framework\TestCase;
use CirculationDashboard\AllSubscriberImporter;

require_once PROJECT_ROOT . '/web/lib/AllSubscriberImporter.php';

/**
 * Which export counts as a week's snapshot.
 *
 * Newzware exports the All Subscriber Report every morning Monday-Saturday, and every
 * one of those files maps to the same week. Before this rule the last file of the week
 * won, so a week silently described a reality up to 12 days after the week it was
 * labelled with, and the newest point on every trend moved daily.
 *
 * The export runs at ~05:10, so the file produced the Monday after a week ends captures
 * subscribers as they stood at that week's close. That is the week's snapshot.
 */
class AuthoritativeExportTest extends TestCase
{
    /** Week of 2026-08-03 closes with the export produced Monday 2026-08-10. */
    public function testMondayAfterTheWeekIsAuthoritative(): void
    {
        $this->assertTrue(AllSubscriberImporter::isAuthoritativeExport('2026-08-03', '2026-08-10'));
    }

    /** These are the exports that used to overwrite a closed week. */
    public function testMidWeekExportsAreNotAuthoritative(): void
    {
        foreach (['2026-08-11', '2026-08-12', '2026-08-13', '2026-08-14', '2026-08-15'] as $file_date) {
            $this->assertFalse(
                AllSubscriberImporter::isAuthoritativeExport('2026-08-03', $file_date),
                "$file_date is a mid-week export and must not close the week of 2026-08-03"
            );
        }
    }

    /** An export produced during the week it belongs to describes an unfinished week. */
    public function testExportFromInsideTheWeekIsNotAuthoritative(): void
    {
        $this->assertFalse(AllSubscriberImporter::isAuthoritativeExport('2026-08-03', '2026-08-05'));
    }

    /** The following week's Monday belongs to the following week, not this one. */
    public function testLaterMondayIsNotAuthoritative(): void
    {
        $this->assertFalse(AllSubscriberImporter::isAuthoritativeExport('2026-08-03', '2026-08-17'));
    }

    public function testWorksAcrossAMonthBoundary(): void
    {
        $this->assertTrue(AllSubscriberImporter::isAuthoritativeExport('2026-07-27', '2026-08-03'));
    }

    public function testWorksAcrossAYearBoundary(): void
    {
        $this->assertTrue(AllSubscriberImporter::isAuthoritativeExport('2025-12-29', '2026-01-05'));
    }

    /**
     * Every week currently in the dashboard was built by an export either 7 days after
     * the week started (correct) or 12 days after (the polluted ones). Only the first
     * should qualify.
     */
    public function testMatchesTheRealHistoricalPattern(): void
    {
        $this->assertTrue(AllSubscriberImporter::isAuthoritativeExport('2026-03-16', '2026-03-23'));
        $this->assertFalse(AllSubscriberImporter::isAuthoritativeExport('2026-03-23', '2026-04-04'));
    }
}
