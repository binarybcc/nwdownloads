#!/bin/bash
###############################################################################
# Database Restore Script
#
# Purpose: Restore MariaDB database from backup
# Usage: ./restore-database.sh <backup_number>
# Example: ./restore-database.sh 1
#
# Security: Validates backup number (1, 2, or 3 only)
# Safety: Creates pre-restore snapshot before dropping database
###############################################################################

set -euo pipefail

###############################################################################
# CONFIGURATION
###############################################################################

BACKUP_BASE="/volume1/homes/newzware/backup"
RESTORE_LOG="/volume1/homes/newzware/backup/logs/restore-$(date +%Y-%m-%d-%H%M%S).log"

# Database configuration
DB_SOCKET="/run/mysqld/mysqld10.sock"
DB_USER="root"
DB_PASS="P@ta675N0id"
DB_NAME="circulation_dashboard"

# MariaDB binary paths
MYSQL="/usr/local/mariadb10/bin/mysql"
MYSQLDUMP="/usr/local/mariadb10/bin/mysqldump"

###############################################################################
# LOGGING
###############################################################################

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" | tee -a "$RESTORE_LOG"
}

log_error() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: $*" | tee -a "$RESTORE_LOG" >&2
}

log_success() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ✓ $*" | tee -a "$RESTORE_LOG"
}

###############################################################################
# INPUT VALIDATION (SECURITY LAYER 1: WHITELIST)
###############################################################################

validate_backup_number() {
    local backup_num="$1"

    # Whitelist: Only allow 1, 2, or 3
    if [[ ! "$backup_num" =~ ^[1-3]$ ]]; then
        log_error "Invalid backup number: $backup_num (must be 1, 2, or 3)"
        exit 1
    fi

    log_success "Backup number validated: $backup_num"
}

###############################################################################
# PRE-RESTORE SAFETY SNAPSHOT
###############################################################################

create_safety_snapshot() {
    log "=== Creating pre-restore safety snapshot ==="

    local safety_backup="${BACKUP_BASE}/logs/pre-restore-snapshot-$(date +%Y-%m-%d-%H%M%S).sql.gz"

    log "Creating safety snapshot: $(basename "$safety_backup")"
    if "$MYSQLDUMP" -u"$DB_USER" -p"$DB_PASS" -S "$DB_SOCKET" \
        --single-transaction \
        --routines \
        --triggers \
        "$DB_NAME" | gzip > "$safety_backup"; then

        local snapshot_size
        snapshot_size=$(du -h "$safety_backup" | cut -f1)
        log_success "Safety snapshot created: $snapshot_size"
        echo "$safety_backup"
    else
        log_error "Failed to create safety snapshot"
        exit 1
    fi
}

###############################################################################
# DATABASE RESTORE
###############################################################################

restore_database() {
    local backup_num="$1"
    local backup_file="${BACKUP_BASE}/backup-${backup_num}/database/${DB_NAME}.sql.gz"

    log "=== Restoring database from backup-${backup_num} ==="

    # Verify backup exists
    if [ ! -f "$backup_file" ]; then
        log_error "Backup file not found: $backup_file"
        exit 1
    fi
    log_success "Backup file found: $(du -h "$backup_file" | cut -f1)"

    # Verify backup integrity
    log "Validating backup integrity..."
    if ! gunzip -t "$backup_file" 2>/dev/null; then
        log_error "Backup file is corrupted"
        exit 1
    fi
    log_success "Backup integrity verified"

    # Get table count before restore
    local tables_before
    tables_before=$("$MYSQL" -u"$DB_USER" -p"$DB_PASS" -S "$DB_SOCKET" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME'" -sN 2>/dev/null || echo "0")
    log "Current database has $tables_before tables"

    # Drop existing database
    log "Dropping existing database..."
    if "$MYSQL" -u"$DB_USER" -p"$DB_PASS" -S "$DB_SOCKET" -e "DROP DATABASE IF EXISTS $DB_NAME" 2>/dev/null; then
        log_success "Database dropped"
    else
        log_error "Failed to drop database"
        exit 1
    fi

    # Create fresh database
    log "Creating fresh database..."
    if "$MYSQL" -u"$DB_USER" -p"$DB_PASS" -S "$DB_SOCKET" -e "CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" 2>/dev/null; then
        log_success "Fresh database created"
    else
        log_error "Failed to create database"
        exit 1
    fi

    # Restore from backup
    log "Restoring data from backup..."
    if gunzip -c "$backup_file" | "$MYSQL" -u"$DB_USER" -p"$DB_PASS" -S "$DB_SOCKET" "$DB_NAME" 2>/dev/null; then
        log_success "Database restore complete"
    else
        log_error "Failed to restore database"
        exit 1
    fi
}

###############################################################################
# POST-RESTORE VERIFICATION
###############################################################################

verify_restore() {
    log "=== Verifying restored database ==="

    # Count tables
    local table_count
    table_count=$("$MYSQL" -u"$DB_USER" -p"$DB_PASS" -S "$DB_SOCKET" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME'" -sN 2>/dev/null)
    log_success "Restored database has $table_count tables"

    # Count records in daily_snapshots
    local snapshot_count
    snapshot_count=$("$MYSQL" -u"$DB_USER" -p"$DB_PASS" -S "$DB_SOCKET" -D "$DB_NAME" -e "SELECT COUNT(*) FROM daily_snapshots" -sN 2>/dev/null || echo "0")
    log_success "daily_snapshots table has $snapshot_count records"

    # Verify key tables exist
    local key_tables=("daily_snapshots" "publication_schedule")
    for table in "${key_tables[@]}"; do
        if "$MYSQL" -u"$DB_USER" -p"$DB_PASS" -S "$DB_SOCKET" -D "$DB_NAME" -e "SELECT 1 FROM $table LIMIT 1" >/dev/null 2>&1; then
            log_success "Table verified: $table"
        else
            log_error "Table missing or empty: $table"
        fi
    done
}

###############################################################################
# MAIN EXECUTION
###############################################################################

main() {
    log "========================================"
    log "Database Restore Started"
    log "========================================"

    # Validate input
    if [ $# -ne 1 ]; then
        log_error "Usage: $0 <backup_number>"
        log_error "Example: $0 1"
        exit 1
    fi

    local backup_num="$1"

    # Validate backup number (Security Layer 1)
    validate_backup_number "$backup_num"

    # Create safety snapshot
    local safety_snapshot
    safety_snapshot=$(create_safety_snapshot)

    # Restore database
    restore_database "$backup_num"

    # Verify restore
    verify_restore

    log "========================================"
    log "Database restore completed successfully!"
    log "========================================"
    log "Restored from: backup-${backup_num}"
    log "Safety snapshot: $(basename "$safety_snapshot")"
    log "Next: Verify application functionality"
}

# Run main function
main "$@"
