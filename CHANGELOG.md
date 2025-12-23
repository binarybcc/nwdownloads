# Changelog

All notable changes to NWDownloads Dashboard will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2025-12-23

### Changed
- **BREAKING**: Refactored upload code paths to eliminate divergent implementations (#30)
- Moved minimum backfill date to class constant for maintainability

### Fixed
- Upload interface regression and upload blocking logic
- SoftBackfill logic to respect real vs backfilled data
- File processing to use Unix socket for production database
- Minimum backfill date to allow Nov 24 uploads

### Added
- Automated file processing system (Phase 1, 2, 3)
- Automated backup and restore system (#27)
- Technical debt tracking documentation
- Detailed logging for upload troubleshooting

### Documentation
- Added CRITICAL tech debt documentation for divergent upload code paths
- Clarified SoftBackfill logic for real vs backfilled data
- Added backup system design with security measures

---

## [Unreleased]

### To Be Categorized
*The following untracked files need review:*
- Migration scripts in `database/migrations/`
- New utility scripts in `scripts/`
- Diagnostic tools in `web/`

---

## Version History (Pre-Changelog)

*Note: This changelog was initialized on 2025-12-23. Previous development
history can be found in git log. Future changes will be documented here
following conventional changelog format.*
