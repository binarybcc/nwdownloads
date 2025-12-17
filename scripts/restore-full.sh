#!/bin/bash
###############################################################################
# Full System Restore Script
#
# Purpose: Restore both database and code from backup
# Usage: ./restore-full.sh <backup_number>
# Example: ./restore-full.sh 1
#
# Security: Validates backup number (1, 2, or 3 only)
# Safety: Creates safety backups before restore
###############################################################################

set -euo pipefail

###############################################################################
# CONFIGURATION
###############################################################################

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RESTORE_LOG="/volume1/homes/newzware/backup/logs/restore-full-$(date +%Y-%m-%d-%H%M%S).log"

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
# INPUT VALIDATION
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
# MAIN EXECUTION
###############################################################################

main() {
    log "========================================"
    log "Full System Restore Started"
    log "========================================"

    # Validate input
    if [ $# -ne 1 ]; then
        log_error "Usage: $0 <backup_number>"
        log_error "Example: $0 1"
        exit 1
    fi

    local backup_num="$1"

    # Validate backup number
    validate_backup_number "$backup_num"

    # Step 1: Restore Database
    log "=== Step 1: Restoring Database ==="
    if "${SCRIPT_DIR}/restore-database.sh" "$backup_num"; then
        log_success "Database restore completed"
    else
        log_error "Database restore failed"
        exit 1
    fi

    # Step 2: Restore Code
    log "=== Step 2: Restoring Code ==="
    if "${SCRIPT_DIR}/restore-code.sh" "$backup_num"; then
        log_success "Code restore completed"
    else
        log_error "Code restore failed"
        exit 1
    fi

    log "========================================"
    log "Full system restore completed successfully!"
    log "========================================"
    log "Restored from: backup-${backup_num}"
    log "Next: Test site at https://cdash.upstatetoday.com"
    log "      Verify data and functionality"
}

# Run main function
main "$@"
