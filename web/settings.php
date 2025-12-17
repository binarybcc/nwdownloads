<?php

/**
 * Settings Hub - Circulation Dashboard
 * Central location for all system configuration and management
 */

require_once 'auth_check.php';
require_once 'version.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Circulation Dashboard</title>

    <!-- Tailwind CSS -->
    <link rel="stylesheet" href="assets/output.css?v=20251206">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        * {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        .settings-card {
            transition: all 200ms ease;
        }

        .settings-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-gray-50">

    <!-- Header -->
    <header class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <a href="index.php" class="text-gray-600 hover:text-gray-900 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                    </a>
                    <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
                        <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Settings
                    </h1>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-gray-600">
                        Logged in as <strong><?php echo htmlspecialchars($_SESSION['user']); ?></strong>
                    </span>
                    <a href="logout.php"
                       class="text-red-600 hover:text-red-700 transition"
                       title="Sign out">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Page Description -->
        <div class="mb-8">
            <p class="text-gray-600">Configure system settings, manage rates, and customize dashboard behavior.</p>
        </div>

        <!-- Settings Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

            <!-- Rate Management Card -->
            <a href="rates.php" class="settings-card bg-white rounded-lg shadow-md p-6 cursor-pointer border-2 border-transparent hover:border-blue-200">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Rate Management</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Classify subscription rates as Market, Legacy, or Ignored. Control revenue opportunity calculations.
                </p>
                <div class="flex items-center gap-2 text-xs text-blue-600 font-medium">
                    <span>Configure Rates</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                    </svg>
                </div>
            </a>

            <!-- File Processing Card -->
            <a href="file_processing.php" class="settings-card bg-white rounded-lg shadow-md p-6 cursor-pointer border-2 border-transparent hover:border-purple-200">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">File Processing</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Configure automated file processing notifications and view processing history logs.
                </p>
                <div class="flex items-center gap-2 text-xs text-purple-600 font-medium">
                    <span>View Processing Settings</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                    </svg>
                </div>
            </a>

            <!-- Cache Management Card -->
            <div id="cacheManagementCard" class="settings-card bg-white rounded-lg shadow-md p-6 cursor-pointer border-2 border-transparent hover:border-green-200">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                    </div>
                    <div id="cacheStatus" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-600">
                        Loading...
                    </div>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Cache Management</h3>
                <p class="text-sm text-gray-600 mb-4">
                    View cache statistics and manually clear cached dashboard data to force fresh API responses.
                </p>
                <div id="cacheStats" class="text-xs text-gray-500 mb-4">
                    <div class="flex justify-between mb-1">
                        <span>Cache Files:</span>
                        <span id="cacheFileCount">--</span>
                    </div>
                    <div class="flex justify-between mb-1">
                        <span>Size:</span>
                        <span id="cacheSize">--</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Age:</span>
                        <span id="cacheAge">--</span>
                    </div>
                </div>
                <button onclick="clearCache()" class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                    Clear Cache Now
                </button>
            </div>

            <!-- User Management Card (Placeholder) -->
            <div class="settings-card bg-white rounded-lg shadow-md p-6 opacity-60 border-2 border-transparent">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-600">
                        COMING SOON
                    </span>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">User Management</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Manage user accounts, permissions, and access levels for the circulation dashboard.
                </p>
            </div>

            <!-- Dashboard Preferences Card (Placeholder) -->
            <div class="settings-card bg-white rounded-lg shadow-md p-6 opacity-60 border-2 border-transparent">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                            </svg>
                        </div>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-600">
                        COMING SOON
                    </span>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Dashboard Preferences</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Customize dashboard appearance, default views, and notification preferences.
                </p>
            </div>

            <!-- Data Sources Card (Placeholder) -->
            <div class="settings-card bg-white rounded-lg shadow-md p-6 opacity-60 border-2 border-transparent">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path>
                            </svg>
                        </div>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-600">
                        COMING SOON
                    </span>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Data Sources</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Configure data import schedules, file upload locations, and data validation rules.
                </p>
            </div>

            <!-- Report Templates Card (Placeholder) -->
            <div class="settings-card bg-white rounded-lg shadow-md p-6 opacity-60 border-2 border-transparent">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-600">
                        COMING SOON
                    </span>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Report Templates</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Create custom report templates, schedule automated exports, and manage email distribution.
                </p>
            </div>

            <!-- System Information Card (Placeholder) -->
            <div class="settings-card bg-white rounded-lg shadow-md p-6 opacity-60 border-2 border-transparent">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-600">
                        COMING SOON
                    </span>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">System Information</h3>
                <p class="text-sm text-gray-600 mb-4">
                    View system version, database status, recent updates, and maintenance logs.
                </p>
            </div>

        </div>

    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex justify-between items-center text-sm text-gray-500">
                <div>
                    <span class="font-medium text-gray-900">Circulation Dashboard</span> <?php echo VERSION_STRING; ?>
                </div>
                <div>
                    © <?php echo date('Y'); ?> Edwards Group Holdings, Inc. ESOP
                </div>
            </div>
        </div>
    </footer>

    <!-- Cache Management JavaScript -->
    <script>
        // Load cache statistics on page load
        async function loadCacheStats() {
            try {
                const response = await fetch('api/cache_management.php?action=stats');
                const data = await response.json();

                if (data.success) {
                    // Update status badge
                    const statusBadge = document.getElementById('cacheStatus');
                    if (data.file_count > 0) {
                        statusBadge.className = 'inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-green-100 text-green-800';
                        statusBadge.textContent = 'ACTIVE';
                    } else {
                        statusBadge.className = 'inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-600';
                        statusBadge.textContent = 'EMPTY';
                    }

                    // Update statistics
                    document.getElementById('cacheFileCount').textContent = data.file_count;
                    document.getElementById('cacheSize').textContent =
                        data.total_size_mb > 0 ? data.total_size_mb + ' MB' : '< 0.01 MB';

                    if (data.oldest_file_age_hours < 1) {
                        document.getElementById('cacheAge').textContent = '< 1 hour';
                    } else if (data.oldest_file_age_days >= 1) {
                        document.getElementById('cacheAge').textContent = data.oldest_file_age_days + ' days';
                    } else {
                        document.getElementById('cacheAge').textContent = Math.round(data.oldest_file_age_hours) + ' hours';
                    }
                }
            } catch (error) {
                console.error('Failed to load cache stats:', error);
                document.getElementById('cacheStatus').textContent = 'ERROR';
            }
        }

        // Clear cache
        async function clearCache() {
            if (!confirm('Clear all cached dashboard data? This will force fresh API responses on the next page load.')) {
                return;
            }

            const button = event.target;
            button.disabled = true;
            button.textContent = 'Clearing...';

            try {
                const formData = new FormData();
                formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');

                const response = await fetch('api/cache_management.php?action=clear', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    alert(`✓ Cache cleared successfully!\n\nCleared ${data.cleared_count} cache file(s).\n\nNext API request will regenerate fresh data.`);
                    loadCacheStats(); // Reload stats
                } else {
                    alert('✗ Failed to clear cache: ' + data.error);
                }
            } catch (error) {
                alert('✗ Failed to clear cache: ' + error.message);
            } finally {
                button.disabled = false;
                button.textContent = 'Clear Cache Now';
            }
        }

        // Load stats on page load
        document.addEventListener('DOMContentLoaded', loadCacheStats);

        // Refresh stats every 30 seconds
        setInterval(loadCacheStats, 30000);
    </script>

</body>
</html>
