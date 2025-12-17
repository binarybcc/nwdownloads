# Backup System Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build automated backup system with 3-copy rotation and GUI-based restore to protect against data loss.

**Design Reference:** `docs/plans/2025-12-17-backup-system-design.md` contains complete architecture, security model, and code examples.

**Security Model:** 6-layer defense-in-depth approach documented in design Section 3.

---

## Implementation Tasks

### Task 1: Create Backup Directory Structure
- SSH to NAS and create /volume1/homes/newzware/backup/ with subdirectories
- Test write permissions

### Task 2: Create Backup Script  
- Write scripts/backup-circulation.sh per design document
- Implements rotation, database dump, code archive, validation
- Comprehensive logging to logs/ directory

### Task 3: Test Backup Script
- Deploy to NAS at /volume1/homes/it/scripts/
- Run manually and verify backup creation
- Test rotation logic

### Task 4: Create Restore Scripts
- Write restore-database.sh, restore-code.sh, restore-full.sh
- Input validation (whitelist: 1, 2, or 3)
- Pre-restore snapshots for safety

### Task 5: Test Restore Scripts
- Deploy to NAS
- Test database restore from backup-1
- Test code restore
- Verify rollback capability

### Task 6: Add Settings UI
- Create api_backup_status.php (reads backup info)
- Create api_backup_restore.php (executes restore with security validation)
- Modify web/settings.php to add "Backup & Restore" tab
- Implement JavaScript for UI interactions

### Task 7: Configure Cron
- Use Synology Task Scheduler
- Schedule: Sun 23:30, Wed 00:30, Fri 00:30
- User: root
- Script: /volume1/homes/it/scripts/backup-circulation.sh

### Task 8: Add Email Notifications
- Implement send_failure_email() in backup script
- Configure Synology SMTP settings
- Test failure notification

### Task 9: End-to-End Testing
- Run full backup cycle (3 rotations)
- Test GUI restore workflow
- Verify all components integrated

### Task 10: Deploy to Production
- Merge feature/backup-system to master
- Deploy via existing Git deployment workflow
- Verify cron configured
- Document deployment

---

## Execution Options

Ready to execute this plan. Choose approach:

**1. Subagent-Driven (this session)** - Fast iteration with review between tasks

**2. Parallel Session (separate)** - Batch execution with checkpoints

Which approach?
