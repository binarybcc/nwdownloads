#!/bin/bash
# Call Log Scraper Daemon
#
# Simple loop that runs fetch_call_logs.php every hour.
# The PHP script handles business-hours filtering (8am-8pm ET),
# lock files, and error alerting internally.
#
# Management:
#   /usr/local/etc/rc.d/S99call_scraper.sh start|stop|status
#
# Logs:
#   /volume1/web/circulation/logs/call_scraper.log (PHP script)
#   /volume1/web/circulation/logs/call_scraper_daemon.log (this loop)

PHP="/var/packages/PHP8.2/target/usr/local/bin/php82"
SCRIPT="/volume1/web/circulation/fetch_call_logs.php"
PIDFILE="/tmp/call_scraper_daemon.pid"
LOGFILE="/volume1/web/circulation/logs/call_scraper_daemon.log"
INTERVAL=3600  # seconds between runs

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOGFILE"
}

# Refuse to start a second copy.
#
# Two daemons had been running since April 2026 — one started by rc.d as root,
# one as the "it" user — because the rc.d guard only consults $PIDFILE and the
# second instance simply overwrote it. An exclusive lock held for the life of
# the process catches a duplicate started by any user, by any trigger.
# (Synology has no pgrep, so process-name matching is not an option.)
#
# The lock lives beside the logs, NOT in /tmp: this daemon runs as root at boot,
# and /tmp is world-writable, so any local user could pre-create the lock path
# as a symlink and have root truncate an arbitrary file. The log directory is
# writable only by root and "it", the two accounts that need to coordinate.
LOCKFILE="$(dirname "$LOGFILE")/.call_scraper_daemon.lock"

# Group-writable so either root or "it" can take it. No chmod is performed here
# on purpose — never adjust modes on a path another user could have replaced.
if [ ! -e "$LOCKFILE" ]; then
    ( umask 002; : > "$LOCKFILE" ) 2>/dev/null
fi

if [ ! -w "$LOCKFILE" ]; then
    log "Lock file $LOCKFILE is not writable — another account's daemon owns it. Exiting."
    exit 0
fi

exec 9>"$LOCKFILE"
if ! flock -n 9; then
    log "Another call scraper daemon already holds the lock. Exiting."
    exit 0
fi

# Write PID for management
echo $$ > "$PIDFILE"
log "Daemon started (PID $$)"

while true; do
    log "Running scraper..."
    $PHP "$SCRIPT" >> "$LOGFILE" 2>&1
    EXIT_CODE=$?
    log "Scraper exited with code $EXIT_CODE. Sleeping ${INTERVAL}s."
    sleep $INTERVAL
done
