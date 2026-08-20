<?php

namespace NWDownloads\Tests\Unit;

use PHPUnit\Framework\TestCase;
use CirculationDashboard\AllSubscriberImporter;

require_once PROJECT_ROOT . '/web/lib/AllSubscriberImporter.php';

/**
 * Duplicate subscriber collapsing in the All Subscriber Report.
 *
 * Newzware's export has no unique subscription ID, so a subscriber holding two
 * subscriptions on the same paper appears as two rows with the same
 * (SUB NUM, Ed) pair. That pair is the database uniqueness rule, so the raw
 * export cannot be imported as-is.
 *
 * Regression: every AllSubscriberReport from 2026-07-08 through 2026-08-20
 * failed to import because subscriber 6326 (paper TJ) appeared twice.
 */
class AllSubscriberDuplicateTest extends TestCase
{
    /** Column layout used by the fixtures below. */
    private const COL_MAP = ['SUB NUM' => 0, 'Ed' => 1, 'Paid Thru' => 2, 'BEGIN' => 3];

    public function testKeepsTheSubscriptionWithTheLatestPaidThru(): void
    {
        $rows = [
            ['6326', 'TJ', '2027-01-22', '2026-07-07'],
            ['6326', 'TJ', '2027-02-14', '2026-07-22'],
        ];

        $result = AllSubscriberImporter::collapseDuplicateSubscribers($rows, self::COL_MAP);

        $this->assertCount(1, $result['rows']);
        $this->assertSame('2027-02-14', $result['rows'][0][2]);
        $this->assertSame(['6326|TJ'], $result['dropped']);
    }

    public function testKeepsTheLatestPaidThruRegardlessOfFileOrder(): void
    {
        $rows = [
            ['6326', 'TJ', '2027-02-14', '2026-07-22'],
            ['6326', 'TJ', '2027-01-22', '2026-07-07'],
        ];

        $result = AllSubscriberImporter::collapseDuplicateSubscribers($rows, self::COL_MAP);

        $this->assertCount(1, $result['rows']);
        $this->assertSame('2027-02-14', $result['rows'][0][2]);
    }

    public function testLeavesFilesWithoutDuplicatesUntouched(): void
    {
        $rows = [
            ['6326', 'TJ', '2027-01-22', '2026-07-07'],
            ['6327', 'TJ', '2027-03-01', '2026-07-07'],
            ['6328', 'TR', '2027-04-01', '2026-07-07'],
        ];

        $result = AllSubscriberImporter::collapseDuplicateSubscribers($rows, self::COL_MAP);

        $this->assertSame($rows, $result['rows']);
        $this->assertSame([], $result['dropped']);
    }

    public function testSameSubscriberOnDifferentPapersIsNotADuplicate(): void
    {
        $rows = [
            ['6326', 'TJ', '2027-01-22', '2026-07-07'],
            ['6326', 'TR', '2027-01-22', '2026-07-07'],
        ];

        $result = AllSubscriberImporter::collapseDuplicateSubscribers($rows, self::COL_MAP);

        $this->assertCount(2, $result['rows']);
        $this->assertSame([], $result['dropped']);
    }

    /** Byte-identical rows still violate the uniqueness rule; keep one. */
    public function testIdenticalDuplicateRowsCollapseToOne(): void
    {
        $rows = [
            ['6326', 'TJ', '2027-01-22', '2026-07-07'],
            ['6326', 'TJ', '2027-01-22', '2026-07-07'],
        ];

        $result = AllSubscriberImporter::collapseDuplicateSubscribers($rows, self::COL_MAP);

        $this->assertCount(1, $result['rows']);
        $this->assertSame(['6326|TJ'], $result['dropped']);
    }

    /**
     * A blank or unparseable Paid Thru must not win over a real date, otherwise
     * a stale row would silently replace the subscriber's current subscription.
     */
    public function testRealPaidThruBeatsAnEmptyOne(): void
    {
        $rows = [
            ['6326', 'TJ', '2027-01-22', '2026-07-07'],
            ['6326', 'TJ', '', '2026-07-22'],
        ];

        $result = AllSubscriberImporter::collapseDuplicateSubscribers($rows, self::COL_MAP);

        $this->assertCount(1, $result['rows']);
        $this->assertSame('2027-01-22', $result['rows'][0][2]);
    }

    /** Rows the main parser would skip anyway must pass through untouched. */
    public function testRowsMissingKeyFieldsArePassedThrough(): void
    {
        $rows = [
            ['', 'TJ', '2027-01-22', '2026-07-07'],
            ['6326', '', '2027-01-22', '2026-07-07'],
        ];

        $result = AllSubscriberImporter::collapseDuplicateSubscribers($rows, self::COL_MAP);

        $this->assertCount(2, $result['rows']);
        $this->assertSame([], $result['dropped']);
    }

    /** The real July 8 export: 7,810 data rows, one duplicated pair. */
    public function testMatchesTheShapeOfTheFailingProductionFile(): void
    {
        $rows = [];
        for ($i = 1; $i <= 100; $i++) {
            $rows[] = [(string)$i, 'TJ', '2027-01-22', '2026-07-07'];
        }
        $rows[] = ['6326', 'TJ', '2027-01-22', '2026-07-07'];
        $rows[] = ['6326', 'TJ', '2027-01-22', '2026-07-07'];

        $result = AllSubscriberImporter::collapseDuplicateSubscribers($rows, self::COL_MAP);

        $this->assertCount(101, $result['rows']);
        $this->assertCount(1, $result['dropped']);
    }
}
