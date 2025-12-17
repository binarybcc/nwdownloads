#!/bin/bash
###############################################################################
# Code Restore Script
#
# Purpose: Restore web application files from backup
# Usage: ./restore-code.sh <backup_number>
# Example: ./restore-code.sh 1
#
# Security: Validates backup number (1, 2, or 3 only)
# Safety: Creates safety backup before replacing production files
###############################################################################

set -euo pipefail

###############################################################################
# CONFIGURATION
###############################################################################

BACKUP_BASE="/volume1/homes/newzware/backup"
RESTORE_LOG="/volume1/homes/newzware/backup/logs/restore-$(date +%Y-%m-%d-%H%M%S).log"
WEB_DIR="/volume1/web/circulation"
TEMP_EXTRACT="/tmp/circulation-restore-$$"

# Files to preserve during restore
PRESERVE_FILES=(".htaccess" ".build_number")

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
# PRE-RESTORE SAFETY BACKUP
###############################################################################

create_safety_backup() {
    log "=== Creating pre-restore safety backup ==="

    local safety_backup="${BACKUP_BASE}/logs/pre-restore-code-$(date +%Y-%m-%d-%H%M%S).tar.gz"

    log "Creating safety backup: $(basename "$safety_backup")"
    if tar czf "$safety_backup" -C "$(dirname "$WEB_DIR")" "$(basename "$WEB_DIR")"; then
        local backup_size
        backup_size=$(du -h "$safety_backup" | cut -f1)
        log_success "Safety backup created: $backup_size"
        echo "$safety_backup"
    else
        log_error "Failed to create safety backup"
        exit 1
    fi
}

###############################################################################
# CODE RESTORE
###############################################################################

restore_code() {
    local backup_num="$1"
    local backup_file="${BACKUP_BASE}/backup-${backup_num}/code/web-files.tar.gz"

    log "=== Restoring code from backup-${backup_num} ==="

    # Verify backup exists
    if [ ! -f "$backup_file" ]; then
        log_error "Backup file not found: $backup_file"
        exit 1
    fi
    log_success "Backup file found: $(du -h "$backup_file" | cut -f1)"

    # Verify backup integrity
    log "Validating backup integrity..."
    if ! tar tzf "$backup_file" >/dev/null 2>&1; then
        log_error "Backup file is corrupted"
        exit 1
    fi
    log_success "Backup integrity verified"

    # Create temporary extraction directory
    log "Creating temporary extraction directory..."
    mkdir -p "$TEMP_EXTRACT"
    log_success "Temp directory: $TEMP_EXTRACT"

    # Extract backup to temp
    log "Extracting backup to temp directory..."
    if tar xzf "$backup_file" -C "$TEMP_EXTRACT"; then
        log_success "Backup extracted successfully"
    else
        log_error "Failed to extract backup"
        rm -rf "$TEMP_EXTRACT"
        exit 1
    fi

    # Preserve production-specific files
    log "Preserving production-specific files..."
    for file in "${PRESERVE_FILES[@]}"; do
        if [ -f "${WEB_DIR}/${file}" ]; then
            log "Backing up: $file"
            cp "${WEB_DIR}/${file}" "${TEMP_EXTRACT}/circulation/${file}"
            log_success "Preserved: $file"
        fi
    done

    # Replace production files
    log "Replacing production files..."
    if rsync -av --delete "${TEMP_EXTRACT}/circulation/" "${WEB_DIR}/"; then
        log_success "Production files updated"
    else
        log_error "Failed to update production files"
        rm -rf "$TEMP_EXTRACT"
        exit 1
    fi

    # Clean up temp directory
    log "Cleaning up temporary files..."
    rm -rf "$TEMP_EXTRACT"
    log_success "Temporary files removed"
}

###############################################################################
# FIX FILE PERMISSIONS
###############################################################################

fix_permissions() {
    log "=== Fixing file permissions ==="

    # Set directory permissions: 755
    log "Setting directory permissions (755)..."
    find "$WEB_DIR" -type d -exec chmod 755 {} \; 2>/dev/null || true
    log_success "Directory permissions set"

    # Set file permissions: 644
    log "Setting file permissions (644)..."
    find "$WEB_DIR" -type f -exec chmod 644 {} \; 2>/dev/null || true
    log_success "File permissions set"

    # Set ownership
    log "Setting ownership (it:users)..."
    chown -R it:users "$WEB_DIR" 2>/dev/null || true
    log_success "Ownership set"
}

###############################################################################
# POST-RESTORE VERIFICATION
###############################################################################

verify_restore() {
    log "=== Verifying restored code ==="

    # Check key files exist
    local key_files=("index.php" "api.php" "assets/input.css")
    for file in "${key_files[@]}"; do
        if [ -f "${WEB_DIR}/${file}" ]; then
            log_success "File verified: $file"
        else
            log_error "File missing: $file"
        fi
    done

    # Count total files
    local file_count
    file_count=$(find "$WEB_DIR" -type f | wc -l)
    log_success "Total files restored: $file_count"

    # Test site accessibility (basic check)
    log "Testing site accessibility..."
    if [ -f "${WEB_DIR}/index.php" ] && [ -r "${WEB_DIR}/index.php" ]; then
        log_success "Site files are readable"
    else
        log_error "Site files may not be accessible"
    fi
}

###############################################################################
# MAIN EXECUTION
###############################################################################

main() {
    log "========================================"
    log "Code Restore Started"
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

    # Create safety backup
    local safety_backup
    safety_backup=$(create_safety_backup)

    # Restore code
    restore_code "$backup_num"

    # Fix permissions
    fix_permissions

    # Verify restore
    verify_restore

    log "========================================"
    log "Code restore completed successfully!"
    log "========================================"
    log "Restored from: backup-${backup_num}"
    log "Safety backup: $(basename "$safety_backup")"
    log "Next: Test site at https://cdash.upstatetoday.com"
}

# Run main function
main "$@"
