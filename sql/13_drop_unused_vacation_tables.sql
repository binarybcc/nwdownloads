-- Drop unused vacation tables
--
-- vacations, vacation_snapshots and vacation_types were created for a design that
-- was never implemented. All three are empty, nothing in the application reads or
-- writes them, and the only foreign key among them points from vacations to
-- vacation_types, so the set drops cleanly on its own.
--
-- Real vacation data lives in subscriber_snapshots (on_vacation, vacation_start,
-- vacation_end, vacation_weeks), written by VacationImporter and carried across
-- weekly rebuilds by AllSubscriberImporter.
--
-- Date: 2026-08-21

-- vacations first: it holds the foreign key to vacation_types
DROP TABLE IF EXISTS vacations;
DROP TABLE IF EXISTS vacation_types;
DROP TABLE IF EXISTS vacation_snapshots;
