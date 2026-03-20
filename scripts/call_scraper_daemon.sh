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
