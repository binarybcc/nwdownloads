-- Add a 'skipped' status to raw_uploads
--
-- Newzware exports the All Subscriber Report six mornings a week, but a week's
-- snapshot is the export taken once that week ends. The other five are skipped
-- deliberately (see AllSubscriberImporter::isAuthoritativeExport).
--
-- Without a distinct status those routine skips record as 'failed', which would put
-- roughly five false failures a week into the audit trail and bury the real ones.
--
-- Date: 2026-08-21

ALTER TABLE raw_uploads
    MODIFY processing_status
        ENUM('pending', 'completed', 'failed', 'reprocessing', 'skipped')
        DEFAULT 'completed';
