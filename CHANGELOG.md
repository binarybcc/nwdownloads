# Changelog

All notable changes to this project will be documented in this file. See [standard-version](https://github.com/conventional-changelog/standard-version) for commit guidelines.

## [2.1.0](https://github.com/binarybcc/nwdownloads/compare/v2.0.0...v2.1.0) (2025-12-24)

### Features

- **db:** Migration Safety System & Operational Scripts ([#31](https://github.com/binarybcc/nwdownloads/issues/31)) ([23c678a](https://github.com/binarybcc/nwdownloads/commit/23c678aa504aaad93d69d9da154c765c694ac1a7))
- **tooling:** add automated versioning with standard-version ([32c01d4](https://github.com/binarybcc/nwdownloads/commit/32c01d411ef6f88acf161d086332a014076eea3a))
- **upload:** consolidate renewals into unified upload interface ([e32aae5](https://github.com/binarybcc/nwdownloads/commit/e32aae5be7fa4978af45a848045c999f0c3c51ee))

### Bug Fixes

- **database:** remove column positioning from migration script ([335ac66](https://github.com/binarybcc/nwdownloads/commit/335ac660961deb2874a7a63d4c044586e8a74791))

### Documentation

- Add CHANGELOG.md and version engineering standards ([ce3d5e1](https://github.com/binarybcc/nwdownloads/commit/ce3d5e11a48955623f67cc9551042123c38e0c10))

### Maintenance

- add package.json for version tracking and tooling ([265e33c](https://github.com/binarybcc/nwdownloads/commit/265e33c611bf675c0d7063378ee9a549626bc8b8))
- add project configuration and documentation ([e355036](https://github.com/binarybcc/nwdownloads/commit/e355036ccd874ff31297b502e0a3ef9fc176d1d3))
- **hooks:** add pre-commit hooks with husky and commitlint ([2a6a027](https://github.com/binarybcc/nwdownloads/commit/2a6a027133832562aef7665324b29f026d2e4d23))

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

_The following untracked files need review:_

- Migration scripts in `database/migrations/`
- New utility scripts in `scripts/`
- Diagnostic tools in `web/`

---

## Version History (Pre-Changelog)

_Note: This changelog was initialized on 2025-12-23. Previous development
history can be found in git log. Future changes will be documented here
following conventional changelog format._
