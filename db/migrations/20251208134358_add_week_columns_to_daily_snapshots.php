<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddWeekColumnsToDailySnapshots extends AbstractMigration
{
    /**
     * Add week_num and year columns to daily_snapshots and subscriber_snapshots tables
     * Required for week-based upload system
     */
    public function change(): void
    {
        // Add columns to daily_snapshots
        $dailyTable = $this->table('daily_snapshots');
        $dailyTable->addColumn('week_num', 'integer', [
            'null' => true,
            'comment' => 'ISO week number (1-53)',
            'after' => 'snapshot_date'
        ])
        ->addColumn('year', 'integer', [
            'null' => true,
            'comment' => 'Year for the week',
            'after' => 'week_num'
        ])
        ->addIndex(['week_num', 'year'], ['name' => 'idx_week'])
        ->update();

        // Backfill existing data in daily_snapshots
        $this->execute("
            UPDATE daily_snapshots
            SET
                week_num = WEEK(snapshot_date, 3),
                year = YEAR(snapshot_date)
            WHERE week_num IS NULL OR year IS NULL
        ");

        // Add columns to subscriber_snapshots
        $subscriberTable = $this->table('subscriber_snapshots');
        $subscriberTable->addColumn('week_num', 'integer', [
            'null' => true,
            'comment' => 'ISO week number (1-53)',
            'after' => 'snapshot_date'
        ])
        ->addColumn('year', 'integer', [
            'null' => true,
            'comment' => 'Year for the week',
            'after' => 'week_num'
        ])
        ->addIndex(['week_num', 'year'], ['name' => 'idx_week_subscriber'])
        ->update();

        // Backfill existing data in subscriber_snapshots
        $this->execute("
            UPDATE subscriber_snapshots
            SET
                week_num = WEEK(snapshot_date, 3),
                year = YEAR(snapshot_date)
            WHERE week_num IS NULL OR year IS NULL
        ");
    }
}
