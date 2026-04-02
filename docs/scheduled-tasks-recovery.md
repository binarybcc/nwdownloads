# Scheduled Tasks Recovery Guide

Recovery instructions for NAS scheduled tasks. Synology Task Scheduler has a history of deleting user-defined tasks, so this document captures everything needed to recreate them.

**NAS Timezone:** Eastern (US/Eastern)

---

## Task 1: Circulation Daily Import

**Purpose:** Processes CSV files from Newzware SFTP inbox (AllSubscriberReport, RenewalChurnReport, SubscribersOnVacation, NewSubscriptionStarts, StopAnalysisReport) into the circulation dashboard database.

**How to verify it's running:**

```bash
tail -20 /volume1/homes/newzware/auto_process.log
```

**Script location:** `/volume1/web/circulation/scripts/run-auto-process.sh`

- This is a wrapper that calls: `/var/packages/PHP8.2/target/usr/local/bin/php82 /volume1/web/circulation/auto_process.php`
- Source copy in repo: `scripts/run-auto-process.sh`

### Recreate in Task Scheduler (DSM > Control Panel > Task Scheduler)

| Setting            | Value                                                       |
| ------------------ | ----------------------------------------------------------- |
| Type               | Scheduled Task > User-defined script                        |
| Task name          | `Circulation Daily Import`                                  |
| User               | `it`                                                        |
| Schedule           | Daily, 07:30 AM (Sunday skip handled in script)             |
| Command            | `bash /volume1/web/circulation/scripts/run-auto-process.sh` |
| Email notification | `jcorbin@upstatetoday.com`                                  |

### If the wrapper script is also missing

Recreate `/volume1/web/circulation/scripts/run-auto-process.sh`:

```bash
#!/bin/bash
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
```

Then: `chmod +x /volume1/web/circulation/scripts/run-auto-process.sh`

### Manual test run

```bash
bash /volume1/web/circulation/scripts/run-auto-process.sh
```

---

## Task 2: Call Log Scraper (MyCommPilot)

**Purpose:** Scrapes VOIP call logs from MyCommPilot every hour during business hours. Runs as a persistent background daemon, NOT a scheduled task.

**How to verify it's running:**

```bash
/usr/local/etc/rc.d/S99call_scraper.sh status
```

**Components:**

1. **rc.d startup script:** `/usr/local/etc/rc.d/S99call_scraper.sh` — auto-starts on NAS boot
2. **Daemon loop:** `/volume1/web/circulation/scripts/call_scraper_daemon.sh` — runs `fetch_call_logs.php` every 3600 seconds
3. **PHP scraper:** `/volume1/web/circulation/fetch_call_logs.php` — the actual scraper (handles business-hours filtering internally)
4. **Source copies in repo:** `scripts/S99call_scraper.sh`, `scripts/call_scraper_daemon.sh`

### Task Scheduler entry (optional, for boot resilience)

The Task Scheduler entry for this task calls the rc.d script. DSM may delete it, but the rc.d auto-start keeps the daemon running regardless.

| Setting   | Value                                                       |
| --------- | ----------------------------------------------------------- |
| Type      | Scheduled Task > User-defined script                        |
| Task name | `call log scraper`                                          |
| User      | `it`                                                        |
| Event     | Boot-up                                                     |
| Command   | `/volume1/web/circulation/scripts/S99call_scraper.sh start` |

### If the daemon dies and won't restart

```bash
# Check status
/usr/local/etc/rc.d/S99call_scraper.sh status

# Restart
/usr/local/etc/rc.d/S99call_scraper.sh restart

# Check logs
tail -20 /volume1/web/circulation/logs/call_scraper_daemon.log
```

### If the rc.d script is missing

Reinstall from repo:

```bash
cp /volume1/web/circulation/scripts/S99call_scraper.sh /usr/local/etc/rc.d/
chmod 755 /usr/local/etc/rc.d/S99call_scraper.sh
/usr/local/etc/rc.d/S99call_scraper.sh start
```

---

## Retired: macOS LaunchAgent

**Previously:** A macOS launchd agent (`com.circulation.auto-import`) on John's Mac triggered the weekly import by SSHing into the NAS. This was replaced by the NAS Task Scheduler entry on 2026-03-30.

**Plist location:** `~/Library/LaunchAgents/com.circulation.auto-import.plist`
**Status:** Unloaded. File retained for reference but should NOT be re-enabled.
