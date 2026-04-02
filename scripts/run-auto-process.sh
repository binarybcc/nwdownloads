#!/bin/bash
# Wrapper for Synology Task Scheduler to run auto_process.php
#
# Task Scheduler config:
#   Task:     Circulation Daily Import
#   Command:  bash /volume1/web/circulation/scripts/run-auto-process.sh
#   Schedule: Daily, 7:30 AM (skips Sunday in script)
#   Run as:   it
#
# This script exists because Task Scheduler has historically deleted
# tasks that call PHP directly. Wrapping in a shell script is stable.
# Sunday skip is handled here because Task Scheduler only offers
# "daily" — no Mon-Sat option.

PHP="/var/packages/PHP8.2/target/usr/local/bin/php82"
SCRIPT="/volume1/web/circulation/auto_process.php"
LOGFILE="/volume1/homes/newzware/auto_process.log"

# Skip Sundays (day 0) — no Newzware export on Sunday
if [ "$(date +%w)" -eq 0 ]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Sunday — skipping (no export today)" >> "$LOGFILE"
    exit 0
fi

echo "[$(date '+%Y-%m-%d %H:%M:%S')] run-auto-process.sh triggered by Task Scheduler" >> "$LOGFILE"

"$PHP" "$SCRIPT"
