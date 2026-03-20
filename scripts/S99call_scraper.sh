#!/bin/bash
# Synology rc.d startup script for Call Log Scraper
#
# Install:
#   cp scripts/S99call_scraper.sh /usr/local/etc/rc.d/
#   chmod 755 /usr/local/etc/rc.d/S99call_scraper.sh
#
# Usage:
#   /usr/local/etc/rc.d/S99call_scraper.sh start
#   /usr/local/etc/rc.d/S99call_scraper.sh stop
#   /usr/local/etc/rc.d/S99call_scraper.sh status

DAEMON="/volume1/web/circulation/scripts/call_scraper_daemon.sh"
PIDFILE="/tmp/call_scraper_daemon.pid"

start() {
    if [ -f "$PIDFILE" ] && kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
        echo "Call scraper daemon already running (PID $(cat "$PIDFILE"))"
        return 1
    fi
    echo "Starting call scraper daemon..."
    nohup bash "$DAEMON" > /dev/null 2>&1 &
    sleep 1
    if [ -f "$PIDFILE" ] && kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
        echo "Started (PID $(cat "$PIDFILE"))"
    else
        echo "Failed to start"
        return 1
    fi
}

stop() {
    if [ ! -f "$PIDFILE" ]; then
        echo "No PID file found"
        return 1
    fi
    PID=$(cat "$PIDFILE")
    if kill -0 "$PID" 2>/dev/null; then
        echo "Stopping call scraper daemon (PID $PID)..."
        kill "$PID"
        rm -f "$PIDFILE"
        echo "Stopped"
    else
        echo "Process $PID not running. Cleaning up PID file."
        rm -f "$PIDFILE"
    fi
}

status() {
    if [ -f "$PIDFILE" ] && kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
        echo "Running (PID $(cat "$PIDFILE"))"
    else
        echo "Not running"
    fi
}

case "$1" in
    start)  start ;;
    stop)   stop ;;
    status) status ;;
    restart) stop; sleep 1; start ;;
    *)      echo "Usage: $0 {start|stop|status|restart}" ;;
esac
