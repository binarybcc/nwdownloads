<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSourceTrackingForSoftBackfill extends AbstractMigration
{
    /**
     * Add source tracking columns for softBackfill system
     *
     * Tracks:
     * - Which CSV file created each snapshot
     * - Date of that CSV file (from filename)
     * - Whether data is backfilled or "real" (from actual upload date)
     * - How many weeks back this data was backfilled
     */
    public function change(): void
    {
        // Add source tracking to daily_snapshots
        $dailyTable = $this->table('daily_snapshots');
        $dailyTable
            ->addColumn('source_filename', 'string', [
                'limit' => 255,
                'null' => true,
                'comment' => 'Original CSV filename that created this snapshot',
                'after' => 'updated_at'
            ])
            ->addColumn('source_date', 'date', [
                'null' => true,
                'comment' => 'Date extracted from source filename (YYYYMMDD)',
                'after' => 'source_filename'
            ])
            ->addColumn('is_backfilled', 'boolean', [
                'default' => false,
                'comment' => '1 if backfilled from later upload, 0 if real data from upload date',
                'after' => 'source_date'
            ])
            ->addColumn('backfill_weeks', 'integer', [
                'null' => true,
                'comment' => 'Number of weeks this data was backfilled (0 = real data)',
                'after' => 'is_backfilled'
            ])
            ->addIndex(['source_date'], ['name' => 'idx_source_date'])
            ->addIndex(['is_backfilled'], ['name' => 'idx_backfilled'])
            ->update();

        // Add source tracking to subscriber_snapshots
        $subscriberTable = $this->table('subscriber_snapshots');
        $subscriberTable
            ->addColumn('source_filename', 'string', [
                'limit' => 255,
                'null' => true,
                'comment' => 'Original CSV filename that created this snapshot',
                'after' => 'updated_at'
            ])
            ->addColumn('source_date', 'date', [
                'null' => true,
                'comment' => 'Date extracted from source filename (YYYYMMDD)',
                'after' => 'source_filename'
            ])
            ->addColumn('is_backfilled', 'boolean', [
                'default' => false,
                'comment' => '1 if backfilled from later upload, 0 if real data from upload date',
                'after' => 'source_date'
            ])
            ->addColumn('backfill_weeks', 'integer', [
                'null' => true,
                'comment' => 'Number of weeks this data was backfilled (0 = real data)',
                'after' => 'is_backfilled'
            ])
            ->addIndex(['source_date'], ['name' => 'idx_source_date_subscriber'])
            ->addIndex(['is_backfilled'], ['name' => 'idx_backfilled_subscriber'])
            ->update();
    }
}
