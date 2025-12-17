#!/bin/bash
###############################################################################
# Circulation Dashboard Backup Script
#
# Purpose: Automated backup of MariaDB database and web application files
# Schedule: Sun 23:30, Wed 00:30, Fri 00:30 (via Synology Task Scheduler)
# Retention: 3-copy rotation (backup-1, backup-2, backup-3)
#
# Author: Automated deployment system
# Created: 2025-12-17
###############################################################################

set -euo pipefail  # Exit on error, undefined vars, pipe failures

###############################################################################
# CONFIGURATION
###############################################################################

BACKUP_BASE="/volume1/homes/newzware/backup"
LOG_DIR="${BACKUP_BASE}/logs"
TIMESTAMP=$(date +%Y-%m-%d-%H%M%S)
LOG_FILE="${LOG_DIR}/backup-${TIMESTAMP}.log"

# Database configuration
DB_SOCKET="/run/mysqld/mysqld10.sock"
DB_USER="root"
DB_PASS="P@ta675N0id"
DB_NAME="circulation_dashboard"

# MariaDB binary paths (Synology)
MYSQL="/usr/local/mariadb10/bin/mysql"
MYSQLDUMP="/usr/local/mariadb10/bin/mysqldump"

# Code backup configuration
WEB_DIR="/volume1/web/circulation"

# Minimum required disk space (5GB in KB)
MIN_DISK_SPACE=$((5 * 1024 * 1024))

###############################################################################
# LOGGING FUNCTIONS
###############################################################################

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" | tee -a "$LOG_FILE"
}

log_error() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: $*" | tee -a "$LOG_FILE" >&2
}

log_success() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ✓ $*" | tee -a "$LOG_FILE"
}

###############################################################################
# EMAIL NOTIFICATION
###############################################################################

send_failure_email() {
    local error_msg="$1"
    log "Sending failure email notification..."

    # Path to email notification script
    local email_script="/volume1/homes/it/scripts/send-failure-email.php"

    # Send email notification via PHP script
    if [ -f "$email_script" ]; then
        /usr/bin/php "$email_script" "$error_msg" >> "$LOG_FILE" 2>&1
        if [ $? -eq 0 ]; then
            log_success "Email notification sent"
        else
            log_error "Failed to send email notification"
        fi
    else
        log_error "Email script not found: $email_script"
    fi
}

###############################################################################
# PRE-FLIGHT CHECKS
###############################################################################

preflight_checks() {
    log "=== Pre-flight checks ==="

    # Check disk space
    local available_space
    available_space=$(df /volume1 | tail -1 | awk '{print $4}')

    if [ "$available_space" -lt "$MIN_DISK_SPACE" ]; then
        log_error "Insufficient disk space: ${available_space}KB available, ${MIN_DISK_SPACE}KB required"
        send_failure_email "Backup failed: Insufficient disk space"
        exit 1
    fi
    log_success "Disk space check: ${available_space}KB available"

    # Check database accessibility
    if ! "$MYSQL" -u"$DB_USER" -p"$DB_PASS" -S "$DB_SOCKET" -e "USE $DB_NAME" 2>/dev/null; then
        log_error "Cannot connect to database: $DB_NAME"
        send_failure_email "Backup failed: Cannot connect to database"
        exit 1
    fi
    log_success "Database connection verified"

    # Check web directory exists
    if [ ! -d "$WEB_DIR" ]; then
        log_error "Web directory not found: $WEB_DIR"
        send_failure_email "Backup failed: Web directory not found"
        exit 1
    fi
    log_success "Web directory verified: $WEB_DIR"
}

###############################################################################
# ROTATION LOGIC
###############################################################################

rotate_backups() {
    log "=== Rotating backups ==="

    cd "$BACKUP_BASE" || exit 1

    # Delete backup-3 if it exists
    if [ -d "backup-3" ]; then
        log "Deleting oldest backup (backup-3)..."
        rm -rf backup-3
        log_success "Deleted backup-3"
    fi

    # Rename backup-2 to backup-3
    if [ -d "backup-2" ]; then
        log "Rotating backup-2 → backup-3..."
        mv backup-2 backup-3
        log_success "Rotated backup-2 → backup-3"
    fi

    # Rename backup-1 to backup-2
    if [ -d "backup-1" ]; then
        log "Rotating backup-1 → backup-2..."
        mv backup-1 backup-2
        log_success "Rotated backup-1 → backup-2"
    fi

    # Create fresh backup-1 directory structure
    log "Creating new backup-1 directory..."
    mkdir -p backup-1/{database,code}
    log_success "Created backup-1 structure"
}

###############################################################################
# DATABASE BACKUP
###############################################################################

backup_database() {
    log "=== Backing up database ==="

    local db_backup="${BACKUP_BASE}/backup-1/database/${DB_NAME}.sql.gz"

    log "Dumping database: $DB_NAME"
    if "$MYSQLDUMP" -u"$DB_USER" -p"$DB_PASS" -S "$DB_SOCKET" \
        --single-transaction \
        --routines \
        --triggers \
        "$DB_NAME" | gzip > "$db_backup"; then

        local db_size
        db_size=$(du -h "$db_backup" | cut -f1)
        log_success "Database backup complete: $db_size"
    else
        log_error "Database backup failed"
        send_failure_email "Backup failed: Database dump error"
        exit 1
    fi
}

###############################################################################
# CODE BACKUP
###############################################################################

backup_code() {
    log "=== Backing up code files ==="

    local code_backup="${BACKUP_BASE}/backup-1/code/web-files.tar.gz"

    log "Archiving web directory: $WEB_DIR"
    if tar czf "$code_backup" -C "$(dirname "$WEB_DIR")" "$(basename "$WEB_DIR")"; then
        local code_size
        code_size=$(du -h "$code_backup" | cut -f1)
        log_success "Code backup complete: $code_size"
    else
        log_error "Code backup failed"
        send_failure_email "Backup failed: Code archive error"
        exit 1
    fi
}

###############################################################################
# VALIDATION
###############################################################################

validate_backups() {
    log "=== Validating backups ==="

    local db_backup="${BACKUP_BASE}/backup-1/database/${DB_NAME}.sql.gz"
    local code_backup="${BACKUP_BASE}/backup-1/code/web-files.tar.gz"

    # Validate database backup
    log "Validating database archive..."
    if gunzip -t "$db_backup" 2>/dev/null; then
        log_success "Database archive is valid"
    else
        log_error "Database archive validation failed"
        send_failure_email "Backup failed: Database archive corrupted"
        exit 1
    fi

    # Validate code backup
    log "Validating code archive..."
    if tar tzf "$code_backup" >/dev/null 2>&1; then
        log_success "Code archive is valid"
    else
        log_error "Code archive validation failed"
        send_failure_email "Backup failed: Code archive corrupted"
        exit 1
    fi
}

###############################################################################
# MAIN EXECUTION
###############################################################################

main() {
    log "========================================"
    log "Circulation Dashboard Backup Started"
    log "========================================"

    # Ensure log directory exists
    mkdir -p "$LOG_DIR"

    # Execute backup workflow
    preflight_checks
    rotate_backups
    backup_database
    backup_code
    validate_backups

    log "========================================"
    log "Backup completed successfully!"
    log "========================================"
    log "Backup location: ${BACKUP_BASE}/backup-1"
    log "Next backup: Check Synology Task Scheduler"
}

# Run main function
main "$@"
