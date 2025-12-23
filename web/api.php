<?php

/**
 * Circulation Dashboard API Router
 *
 * This file routes API requests to the appropriate handler.
 *
 * MIGRATION NOTE:
 * - Currently routes all requests to api/legacy.php (the original monolithic API)
 * - Future: Gradually migrate endpoints to modular api/endpoints/ files
 * - Shared functionality already extracted to api/shared/
 *
 * Directory Structure:
 * - api/shared/       - Shared functions (database, response, utils)
 * - api/endpoints/    - Individual endpoint modules (future)
 * - api/functions/    - Data-fetching functions (future)
 * - api/legacy.php    - Original monolithic API (current)
 */

// For now, delegate all requests to the legacy API
// This maintains 100% backward compatibility while we gradually migrate
require_once __DIR__ . '/api/legacy.php';
