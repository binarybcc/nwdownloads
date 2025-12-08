<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddWeekColumnsToDailySnapshots extends AbstractMigration
{
    /**
     * Add week_num and year columns to daily_snapshots table
     * Required for week-based upload system
     */
    public function change(): void
    {
        $table = $this->table('daily_snapshots');

        // Add columns
        $table->addColumn('week_num', 'integer', [
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

        // Backfill existing data
        $this->execute("
            UPDATE daily_snapshots
            SET
                week_num = WEEK(snapshot_date, 3),
                year = YEAR(snapshot_date)
            WHERE week_num IS NULL OR year IS NULL
        ");
    }
}
