<?php
/**
 * Admin Audit Endpoint - Data Provenance Viewer
 * Shows which uploads own which weeks
 * Debugging/troubleshooting tool for softBackfill system
 */

require_once 'auth_check.php';

// Database configuration
$db_config = [
    'host' => getenv('DB_HOST') ?: 'database',
    'port' => getenv('DB_PORT') ?: 3306,
    'database' => getenv('DB_NAME') ?: 'circulation_dashboard',
    'username' => getenv('DB_USER') ?: 'circ_dash',
    'password' => getenv('DB_PASSWORD') ?: 'Barnaby358@Jones!',
];

try {
    $dsn = "mysql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $db_config['username'], $db_config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Get audit summary grouped by week
    $stmt = $pdo->query("
        SELECT
            week_num,
            year,
            snapshot_date,
            source_filename,
            source_date,
            is_backfilled,
            backfill_weeks,
            COUNT(*) as num_papers,
            SUM(total_active) as total_subscribers
        FROM daily_snapshots
        WHERE paper_code != 'FN'
        GROUP BY week_num, year, snapshot_date, source_filename, source_date, is_backfilled, backfill_weeks
        ORDER BY year DESC, week_num DESC
    ");
    $weeks = $stmt->fetchAll();

    // Get unique sources
    $source_stmt = $pdo->query("
        SELECT DISTINCT
            source_filename,
            source_date,
            COUNT(DISTINCT week_num, year) as weeks_owned,
            MIN(week_num) as first_week,
            MAX(week_num) as last_week
        FROM daily_snapshots
        WHERE source_filename IS NOT NULL
        GROUP BY source_filename, source_date
        ORDER BY source_date DESC
    ");
    $sources = $source_stmt->fetchAll();

} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Audit - Data Provenance</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto p-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">🔍 Data Provenance Audit</h1>
                    <p class="text-gray-600 mt-2">SoftBackfill system - which uploads own which weeks</p>
                </div>
                <a href="index.php" class="text-blue-600 hover:text-blue-700 text-sm">← Back to Dashboard</a>
            </div>
        </div>

        <!-- Upload Sources Summary -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">📄 Upload Sources</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Source File</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Source Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Weeks Owned</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Week Range</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($sources as $source): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900"><?= htmlspecialchars($source['source_filename']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?= date('M j, Y', strtotime($source['source_date'])) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= $source['weeks_owned'] ?> weeks</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">Week <?= $source['first_week'] ?> - <?= $source['last_week'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Week-by-Week Breakdown -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h2 class="text-xl font-semibold mb-4">📅 Week-by-Week Provenance</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Week</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Snapshot Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Source</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Backfill</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Papers</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subscribers</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($weeks as $week): ?>
                        <tr class="<?= $week['is_backfilled'] ? 'bg-amber-50' : '' ?>">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                Week <?= $week['week_num'] ?>, <?= $week['year'] ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <?= date('M j, Y', strtotime($week['snapshot_date'])) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs font-mono text-gray-600">
                                <?= $week['source_filename'] ? htmlspecialchars(substr($week['source_filename'], 0, 30)) . '...' : 'N/A' ?>
                                <?php if ($week['source_date']): ?>
                                <div class="text-xs text-gray-500 mt-1">from <?= date('M j', strtotime($week['source_date'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($week['is_backfilled']): ?>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                    Backfilled
                                </span>
                                <?php else: ?>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Real Data
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <?php if ($week['is_backfilled']): ?>
                                <?= $week['backfill_weeks'] ?> weeks
                                <?php else: ?>
                                -
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= $week['num_papers'] ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= number_format($week['total_subscribers']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Legend -->
        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h3 class="text-sm font-semibold text-blue-900 mb-2">📖 How to Read This</h3>
            <ul class="text-sm text-blue-800 space-y-1">
                <li><strong>Real Data:</strong> Week has data from a CSV uploaded on/near that week's date</li>
                <li><strong>Backfilled:</strong> Week uses data from a later CSV upload (approximate/estimated)</li>
                <li><strong>Backfill Weeks:</strong> How many weeks back this data was backfilled (0 = real data)</li>
                <li><strong>Source:</strong> Which CSV file created this week's data</li>
            </ul>
        </div>
    </div>
</body>
</html>
