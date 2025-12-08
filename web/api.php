<?php

/**
 * Circulation Dashboard API - Phase 2
 * Advanced comparisons, drill-down, analytics, forecasting
 * Author: Claude Code
 * Date: 2025-12-01
 */

// Require authentication
require_once 'auth_check.php';
// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
// CORS headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
// Database configuration
$db_config = [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'port' => getenv('DB_PORT') ?: 3306,
    'database' => getenv('DB_NAME') ?: 'circulation_dashboard',
    'username' => getenv('DB_USER') ?: 'circ_dash',
    'password' => getenv('DB_PASSWORD') ?: 'Barnaby358@Jones!',
    'socket' => getenv('DB_SOCKET') !== false ? getenv('DB_SOCKET') : '/run/mysqld/mysqld10.sock',
];

/**
 * Connect to database
 */
function connectDB($config)
{

    try {
        if (empty($config['socket']) || !file_exists($config['socket'])) {
            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4";
        } else {
            $dsn = "mysql:unix_socket={$config['socket']};dbname={$config['database']};charset=utf8mb4";
        }
        $pdo = new PDO($dsn, $config['username'], $config['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        sendError('Database connection failed: ' . $e->getMessage());
        exit;
    }
}

/**
 * Get week boundaries (Sunday-Saturday) for a given date
 */
function getWeekBoundaries($date)
{

    $dt = new DateTime($date);
    $dayOfWeek = (int)$dt->format('w');
// 0=Sunday, 6=Saturday

    // Find Sunday of this week
    $sunday = clone $dt;
    $sunday->modify('-' . $dayOfWeek . ' days');
// Find Saturday of this week
    $saturday = clone $sunday;
    $saturday->modify('+6 days');
// Calculate week number from the actual date (not Sunday) to match upload.php logic
    // This ensures API and upload.php use the same week numbering
    $week_num = (int)$dt->format('W');
    $year = (int)$dt->format('Y');
    return [
        'start' => $sunday->format('Y-m-d'),
        'end' => $saturday->format('Y-m-d'),
        'week_num' => $week_num,
        'year' => $year
    ];
}

/**
 * Get Saturday date for a week (our snapshot day)
 */
function getSaturdayForWeek($date)
{

    $boundaries = getWeekBoundaries($date);
    return $boundaries['end'];
}

/**
 * Get data range available in database
 */
function getDataRange($pdo)
{

    $stmt = $pdo->query("
        SELECT
            MIN(snapshot_date) as min_date,
            MAX(snapshot_date) as max_date,
            COUNT(DISTINCT snapshot_date) as total_snapshots
        FROM daily_snapshots
        WHERE paper_code != 'FN'
    ");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get the most recent snapshot date (any day of week)
 * With week-based system, snapshots can be on any day (Monday, Saturday, etc.)
 */
function getMostRecentCompleteSaturday($pdo)
{

    // Get the most recent snapshot date (any day of week)
    $stmt = $pdo->query("
        SELECT snapshot_date
        FROM daily_snapshots
        WHERE paper_code != 'FN'
        GROUP BY snapshot_date
        ORDER BY snapshot_date DESC
        LIMIT 1
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['snapshot_date'] : date('Y-m-d');
}

/**
 * Calculate trend direction from 12-week data
 */
function calculateTrendDirection($trend)
{

    if (count($trend) < 8) {
        return 'stable';
    }

    // Calculate 4-week moving averages
    $recentWeeks = array_slice($trend, -4);
    $olderWeeks = array_slice($trend, 0, 8);
    $recentAvg = array_sum(array_column($recentWeeks, 'total_active')) / count($recentWeeks);
    $olderAvg = array_sum(array_column($olderWeeks, 'total_active')) / count($olderWeeks);
    $changePercent = $olderAvg > 0 ? (($recentAvg - $olderAvg) / $olderAvg) * 100 : 0;
    if ($changePercent > 2) {
        return 'growing';
    }
    if ($changePercent < -2) {
        return 'declining';
    }
    return 'stable';
}

/**
 * Get business unit comparison data (YoY and Previous Week)
 * Updated to use week-based queries with fallback for missing weeks
 */
function getBusinessUnitComparison($pdo, $unitName, $currentDate, $currentData)
{
    $boundaries = getWeekBoundaries($currentDate);
    $week_num = $boundaries['week_num'];
    $year = $boundaries['year'];

    $comparison = [
        'yoy' => null,
        'previous_week' => null,
        'trend_direction' => 'stable'
    ];

    // Year-over-year comparison (same week number last year)
    $lastYearDate = date('Y-m-d', strtotime($currentDate . ' -1 year'));
    $lastYearWeek = getWeekBoundaries($lastYearDate);

    $stmt = $pdo->prepare("
        SELECT
            SUM(total_active) as total,
            SUM(deliverable) as deliverable,
            SUM(mail_delivery) as mail,
            SUM(digital_only) as digital,
            SUM(carrier_delivery) as carrier
        FROM daily_snapshots
        WHERE business_unit = ?
          AND paper_code != 'FN'
          AND week_num = ?
          AND year = ?
    ");
    $stmt->execute([$unitName, $lastYearWeek['week_num'], $lastYearWeek['year']]);
    $yoyData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($yoyData && $yoyData['total'] > 0) {
        $comparison['yoy'] = [
            'total' => (int)$yoyData['total'],
            'change' => $currentData['total'] - (int)$yoyData['total'],
            'change_percent' => round((($currentData['total'] - (int)$yoyData['total']) / (int)$yoyData['total']) * 100, 1)
        ];
    }

    // Previous week comparison with fallback for missing weeks
    $targetWeekNum = $week_num - 1;
    $targetYear = $year;

    // Handle year boundary
    if ($targetWeekNum < 1) {
        $targetWeekNum = 52;
        $targetYear--;
    }

    // Try to get data for previous week
    $stmt = $pdo->prepare("
        SELECT
            SUM(total_active) as total,
            SUM(deliverable) as deliverable
        FROM daily_snapshots
        WHERE business_unit = ?
          AND paper_code != 'FN'
          AND week_num = ?
          AND year = ?
    ");
    $stmt->execute([$unitName, $targetWeekNum, $targetYear]);
    $prevWeekData = $stmt->fetch(PDO::FETCH_ASSOC);

    // If no data for previous week, fall back to most recent week with data
    if (!$prevWeekData || $prevWeekData['total'] == 0) {
        $stmt = $pdo->prepare("
            SELECT
                SUM(total_active) as total,
                SUM(deliverable) as deliverable
            FROM daily_snapshots
            WHERE business_unit = ?
              AND paper_code != 'FN'
              AND (year < ? OR (year = ? AND week_num < ?))
            GROUP BY week_num, year
            ORDER BY year DESC, week_num DESC
            LIMIT 1
        ");
        $stmt->execute([$unitName, $year, $year, $week_num]);
        $prevWeekData = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($prevWeekData && $prevWeekData['total'] > 0) {
        $comparison['previous_week'] = [
            'total' => (int)$prevWeekData['total'],
            'change' => $currentData['total'] - (int)$prevWeekData['total'],
            'change_percent' => round((($currentData['total'] - (int)$prevWeekData['total']) / (int)$prevWeekData['total']) * 100, 1)
        ];
    }

    // Get 12-week trend for trend direction
    $trendStart = date('Y-m-d', strtotime($currentDate . ' -84 days'));
    $stmt = $pdo->prepare("
        SELECT
            snapshot_date,
            SUM(total_active) as total_active
        FROM daily_snapshots
        WHERE business_unit = ?
          AND paper_code != 'FN'
          AND snapshot_date >= ?
          AND snapshot_date <= ?
          AND DAYOFWEEK(snapshot_date) = 7
        GROUP BY snapshot_date
        ORDER BY snapshot_date ASC
    ");
    $stmt->execute([$unitName, $trendStart, $saturday]);
    $trend = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $comparison['trend_direction'] = calculateTrendDirection($trend);
    return $comparison;
}

/**
 * Get business unit detail with 12-week trend and paper breakdown
 */
function getBusinessUnitDetail($pdo, $unitName, $date = null)
{

    $saturday = getSaturdayForWeek($date ?? date('Y-m-d'));
// Get papers for this unit
    $stmt = $pdo->prepare("
        SELECT DISTINCT paper_code, paper_name
        FROM daily_snapshots
        WHERE business_unit = ?
          AND paper_code != 'FN'
        ORDER BY paper_name
    ");
    $stmt->execute([$unitName]);
    $papers = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Get 12-week trend for this unit
    $trendStart = date('Y-m-d', strtotime($saturday . ' -84 days'));
    $stmt = $pdo->prepare("
        SELECT
            snapshot_date,
            SUM(total_active) as total_active,
            SUM(deliverable) as deliverable,
            SUM(on_vacation) as on_vacation,
            SUM(mail_delivery) as mail,
            SUM(digital_only) as digital,
            SUM(carrier_delivery) as carrier
        FROM daily_snapshots
        WHERE business_unit = ?
          AND paper_code != 'FN'
          AND snapshot_date >= ?
          AND snapshot_date <= ?
          AND DAYOFWEEK(snapshot_date) = 7
        GROUP BY snapshot_date
        ORDER BY snapshot_date ASC
    ");
    $stmt->execute([$unitName, $trendStart, $saturday]);
    $trend = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Get current data for each paper
    $stmt = $pdo->prepare("
        SELECT
            paper_code,
            paper_name,
            total_active as total,
            on_vacation,
            deliverable,
            mail_delivery as mail,
            digital_only as digital,
            carrier_delivery as carrier
        FROM daily_snapshots
        WHERE business_unit = ?
          AND paper_code != 'FN'
          AND snapshot_date = ?
        ORDER BY total_active DESC
    ");
    $stmt->execute([$unitName, $saturday]);
    $paperDetails = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $paperDetails[$row['paper_code']] = [
            'name' => $row['paper_name'],
            'total' => (int)$row['total'],
            'on_vacation' => (int)$row['on_vacation'],
            'deliverable' => (int)$row['deliverable'],
            'mail' => (int)$row['mail'],
            'digital' => (int)$row['digital'],
            'carrier' => (int)$row['carrier']
        ];
    }

    return [
        'unit_name' => $unitName,
        'papers' => $papers,
        'trend' => array_map(function ($row) {

            return [
                'snapshot_date' => $row['snapshot_date'],
                'total_active' => (int)$row['total_active'],
                'deliverable' => (int)$row['deliverable'],
                'on_vacation' => (int)$row['on_vacation'],
                'mail' => (int)$row['mail'],
                'digital' => (int)$row['digital'],
                'carrier' => (int)$row['carrier']
            ];
        }, $trend),
        'paper_details' => $paperDetails
    ];
}

/**
 * Simple linear regression forecast for next week
 */
function forecastNextWeek($trend)
{
    // Filter out NULL weeks (weeks with no data)
    $validWeeks = array_filter($trend, function($week) {
        return $week['total_active'] !== null;
    });

    $n = count($validWeeks);
    if ($n < 4) {
        return null;
    // Not enough data
    }

    $sumX = 0;
    $sumY = 0;
    $sumXY = 0;
    $sumX2 = 0;
    // Re-index array so $i starts at 0
    $validWeeks = array_values($validWeeks);
    foreach ($validWeeks as $i => $week) {
        $x = $i + 1;
        $y = $week['total_active'];
        $sumX += $x;
        $sumY += $y;
        $sumXY += $x * $y;
        $sumX2 += $x * $x;
    }

    $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
    $intercept = ($sumY - $slope * $sumX) / $n;
// Forecast next week (x = n + 1)
    $forecast = round($slope * ($n + 1) + $intercept);
    $lastActual = $validWeeks[$n - 1]['total_active'];
// Calculate confidence based on variance
    $variance = 0;
    foreach ($validWeeks as $i => $week) {
        $predicted = $slope * ($i + 1) + $intercept;
        $variance += pow($week['total_active'] - $predicted, 2);
    }
    $stdDev = sqrt($variance / $n);
    $avgValue = $sumY / $n;
    $confidenceRatio = $stdDev / $avgValue;
    $confidence = 'medium';
    if ($confidenceRatio < 0.02) {
        $confidence = 'high';
    } elseif ($confidenceRatio > 0.05) {
        $confidence = 'low';
    }

    return [
        'value' => $forecast,
        'change' => $forecast - $lastActual,
        'change_percent' => $lastActual > 0 ? round((($forecast - $lastActual) / $lastActual) * 100, 1) : 0,
        'confidence' => $confidence
    ];
}

/**
 * Detect anomalies in trend data
 */
function detectAnomalies($trend)
{
    // Filter out NULL weeks (weeks with no data)
    $validWeeks = array_filter($trend, function($week) {
        return $week['total_active'] !== null;
    });

    if (count($validWeeks) < 4) {
        return [];
    }

    $values = array_column($validWeeks, 'total_active');
    $mean = array_sum($values) / count($values);
    $variance = 0;
    foreach ($values as $value) {
        $variance += pow($value - $mean, 2);
    }
    $stdDev = sqrt($variance / count($values));
    $anomalies = [];
    foreach ($validWeeks as $i => $week) {
        $zScore = $stdDev > 0 ? ($week['total_active'] - $mean) / $stdDev : 0;
        if (abs($zScore) > 2) {
            $anomalies[] = [
                'date' => $week['snapshot_date'],
                'value' => $week['total_active'],
                'z_score' => round($zScore, 2),
                'severity' => abs($zScore) > 3 ? 'high' : 'medium'
            ];
        }
    }

    return $anomalies;
}

/**
 * Find strongest/weakest performers
 */
function findPerformers($by_business_unit, $comparisons)
{

    $performers = [
        'strongest' => null,
        'weakest' => null
    ];
    $maxChange = -PHP_INT_MAX;
    $minChange = PHP_INT_MAX;
    foreach ($by_business_unit as $unitName => $data) {
        if (!isset($comparisons[$unitName]['yoy'])) {
            continue;
        }

        $change = $comparisons[$unitName]['yoy']['change_percent'];
        if ($change > $maxChange) {
            $maxChange = $change;
            $performers['strongest'] = [
                'unit' => $unitName,
                'change' => $comparisons[$unitName]['yoy']['change'],
                'change_percent' => $change
            ];
        }

        if ($change < $minChange) {
            $minChange = $change;
            $performers['weakest'] = [
            'unit' => $unitName,
            'change' => $comparisons[$unitName]['yoy']['change'],
            'change_percent' => $change
            ];
        }
    }

    return $performers;
}

/**
 * Get enhanced overview with Phase 2 features
 */
function getOverviewEnhanced($pdo, $params)
{

    // Parse parameters
    // If no date provided, use most recent complete Saturday instead of today
    $requestedDate = $params['date'] ?? getMostRecentCompleteSaturday($pdo);
    $compareMode = $params['compare'] ?? 'yoy';
// yoy, previous, none

    // Get week boundaries for requested date
    $week = getWeekBoundaries($requestedDate);
    $week_num = $week['week_num'];
    $year = $week['year'];
// Query by week_num and year (not snapshot_date) for week-based system
    // Include source tracking for backfill indicators
    $stmt = $pdo->prepare("
        SELECT
            snapshot_date,
            week_num,
            year,
            SUM(total_active) as total_active,
            SUM(on_vacation) as on_vacation,
            SUM(deliverable) as deliverable,
            SUM(mail_delivery) as mail,
            SUM(carrier_delivery) as carrier,
            SUM(digital_only) as digital,
            MAX(source_filename) as source_filename,
            MAX(source_date) as source_date,
            MAX(is_backfilled) as is_backfilled,
            MAX(backfill_weeks) as backfill_weeks
        FROM daily_snapshots
        WHERE paper_code != 'FN'
          AND week_num = ?
          AND year = ?
        GROUP BY snapshot_date, week_num, year
    ");
    $stmt->execute([$week_num, $year]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
// Check if this week has data
    $has_data = ($current !== false);
// If no data for this week, don't fall back - return structured empty response
    if (!$has_data) {
// Return empty state with metadata
        return [
            'has_data' => false,
            'week' => [
                'week_num' => $week_num,
                'year' => $year,
                'label' => "Week {$week_num}, {$year}",
                'date_range' => date('M j', strtotime($week['start'])) . ' - ' . date('M j, Y', strtotime($week['end']))
            ],
            'message' => "No snapshot uploaded for Week {$week_num}",
            'explanation' => "Upload a CSV to add data for this week.",
            'current' => null,
            'comparison' => null,
            'by_business_unit' => [],
            'business_unit_comparisons' => [],
            'by_edition' => [],
            'data_range' => getDataRange($pdo),
            'analytics' => [
                'forecast' => null,
                'seasonality' => [],
                'growth_rate' => null,
                'retention_health' => null
            ]
        ];
    }

    // Get comparison data
    $comparison = null;
    $comparison_message = null;
    if ($compareMode === 'yoy') {
    // Year-over-year comparison (same week number last year)
        $lastYearDate = date('Y-m-d', strtotime($current['snapshot_date'] . ' -1 year'));
        $lastYearWeek = getWeekBoundaries($lastYearDate);
        $lastYearSaturday = $lastYearWeek['end'];
    // Find business units that exist in BOTH current and last year
        // This ensures apples-to-apples comparison when business units are added/removed
        $stmt = $pdo->prepare("
            SELECT DISTINCT curr.business_unit
            FROM (
                SELECT DISTINCT business_unit
                FROM daily_snapshots
                WHERE snapshot_date = ? AND paper_code != 'FN'
            ) curr
            INNER JOIN (
                SELECT DISTINCT business_unit
                FROM daily_snapshots
                WHERE snapshot_date = ? AND paper_code != 'FN'
            ) prev
            ON curr.business_unit = prev.business_unit
        ");
        $stmt->execute([$current['snapshot_date'], $lastYearSaturday]);
        $commonUnits = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($commonUnits)) {
            $placeholders = str_repeat('?,', count($commonUnits) - 1) . '?';
            // Get last year data for common business units only
                    $stmt = $pdo->prepare("
                SELECT
                    snapshot_date,
                    SUM(total_active) as total_active,
                    SUM(on_vacation) as on_vacation,
                    SUM(deliverable) as deliverable
                FROM daily_snapshots
                WHERE paper_code != 'FN'
                  AND snapshot_date = ?
                  AND business_unit IN ($placeholders)
                GROUP BY snapshot_date
            ");
            $params = array_merge([$lastYearSaturday], $commonUnits);
            $stmt->execute($params);
            $compareData = $stmt->fetch(PDO::FETCH_ASSOC);
            // Get current year data for common business units only
                    $stmt = $pdo->prepare("
                SELECT
                    SUM(total_active) as total_active,
                    SUM(on_vacation) as on_vacation,
                    SUM(deliverable) as deliverable
                FROM daily_snapshots
                WHERE paper_code != 'FN'
                  AND snapshot_date = ?
                  AND business_unit IN ($placeholders)
            ");
            $params = array_merge([$current['snapshot_date']], $commonUnits);
            $stmt->execute($params);
            $currentComparable = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($compareData && $currentComparable) {
                    $comparison = [
                                    'type' => 'yoy',
                                    'label' => 'Year-over-Year',
                                    'period' => [
                                        'start' => $lastYearWeek['start'],
                                        'end' => $lastYearWeek['end'],
                                        'week_num' => $lastYearWeek['week_num'],
                                        'year' => $lastYearWeek['year'],
                                        'label' => "Week {$lastYearWeek['week_num']}, {$lastYearWeek['year']}"
                                    ],
                                    'data' => [
                                        'total_active' => (int)$compareData['total_active'],
                                        'on_vacation' => (int)$compareData['on_vacation'],
                                        'deliverable' => (int)$compareData['deliverable'],
                                    ],
                                    'changes' => [
                                        'total_active' => (int)$currentComparable['total_active'] - (int)$compareData['total_active'],
                                        'total_active_percent' => $compareData['total_active'] > 0 ?
                                            round((((int)$currentComparable['total_active'] - (int)$compareData['total_active']) / (int)$compareData['total_active']) * 100, 2) : 0,
                                        'on_vacation' => (int)$currentComparable['on_vacation'] - (int)$compareData['on_vacation'],
                                        'deliverable' => (int)$currentComparable['deliverable'] - (int)$compareData['deliverable'],
                                    ]
                    ];
            }
        } else {
            // No historical data available for YoY comparison
            $comparison_message = "Year-over-year comparison unavailable (no {$lastYearWeek['year']} data)";
        }
    } elseif ($compareMode === 'previous') {
    // Previous week comparison with fallback to most recent week with data
        $targetWeekNum = $week_num - 1;
        $targetYear = $year;
    // Handle year boundary
        if ($targetWeekNum < 1) {
            $targetWeekNum = 52;
// Assume 52 weeks (adjust for 53-week years if needed)
            $targetYear--;
        }

        // Try to get data for previous week
        $stmt = $pdo->prepare("
            SELECT
                snapshot_date,
                week_num,
                year,
                SUM(total_active) as total_active,
                SUM(on_vacation) as on_vacation,
                SUM(deliverable) as deliverable
            FROM daily_snapshots
            WHERE paper_code != 'FN'
              AND week_num = ?
              AND year = ?
            GROUP BY snapshot_date, week_num, year
        ");
        $stmt->execute([$targetWeekNum, $targetYear]);
        $compareData = $stmt->fetch(PDO::FETCH_ASSOC);
    // If no data for previous week, fall back to most recent week with data
        if (!$compareData) {
            $stmt = $pdo->prepare("
                SELECT
                    snapshot_date,
                    week_num,
                    year,
                    SUM(total_active) as total_active,
                    SUM(on_vacation) as on_vacation,
                    SUM(deliverable) as deliverable
                FROM daily_snapshots
                WHERE paper_code != 'FN'
                  AND (year < ? OR (year = ? AND week_num < ?))
                GROUP BY snapshot_date, week_num, year
                ORDER BY year DESC, week_num DESC
                LIMIT 1
            ");
            $stmt->execute([$year, $year, $week_num]);
            $compareData = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($compareData) {
            $prevWeekBoundaries = getWeekBoundaries($compareData['snapshot_date']);
            $isFallback = ($compareData['week_num'] != $targetWeekNum);
            $comparison = [
                'type' => 'previous',
                'label' => $isFallback ?
                    "Week {$compareData['week_num']} (Week {$targetWeekNum} not available)" :
                    'Previous Week',
                'is_fallback' => $isFallback,
                'period' => [
                    'start' => $prevWeekBoundaries['start'],
                    'end' => $prevWeekBoundaries['end'],
                    'week_num' => (int)$compareData['week_num'],
                    'year' => (int)$compareData['year'],
                    'label' => "Week {$compareData['week_num']}, {$compareData['year']}"
                ],
                'data' => [
                    'total_active' => (int)$compareData['total_active'],
                    'on_vacation' => (int)$compareData['on_vacation'],
                    'deliverable' => (int)$compareData['deliverable'],
                ],
                'changes' => [
                    'total_active' => (int)$current['total_active'] - (int)$compareData['total_active'],
                    'total_active_percent' => $compareData['total_active'] > 0 ?
                        round((((int)$current['total_active'] - (int)$compareData['total_active']) / (int)$compareData['total_active']) * 100, 2) : 0,
                    'on_vacation' => (int)$current['on_vacation'] - (int)$compareData['on_vacation'],
                    'deliverable' => (int)$current['deliverable'] - (int)$compareData['deliverable'],
                ]
            ];
        }
    }

    // Get 12-week trend with null values for missing weeks
    $trend = [];
    $startWeekNum = $week_num - 11;
    $startYear = $year;
// Handle year boundary
    if ($startWeekNum < 1) {
        $weeksNeeded = abs($startWeekNum) + 1;
        $startYear--;
        $startWeekNum = 52 - $weeksNeeded + 1;
    }

    // Build array of all 12 weeks (with nulls for missing data)
    for ($i = 0; $i < 12; $i++) {
        $currentWeekNum = $startWeekNum + $i;
        $currentYear = $startYear;
// Handle year boundary within loop
        if ($currentWeekNum > 52) {
            $currentWeekNum = $currentWeekNum - 52;
            $currentYear++;
        }

        // Try to get data for this week
        $stmt = $pdo->prepare("
            SELECT
                snapshot_date,
                week_num,
                year,
                SUM(total_active) as total_active,
                SUM(on_vacation) as on_vacation,
                SUM(deliverable) as deliverable
            FROM daily_snapshots
            WHERE paper_code != 'FN'
              AND week_num = ?
              AND year = ?
            GROUP BY snapshot_date, week_num, year
        ");
        $stmt->execute([$currentWeekNum, $currentYear]);
        $weekData = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($weekData) {
            $trend[] = [
                'snapshot_date' => $weekData['snapshot_date'],
                'week_num' => (int)$weekData['week_num'],
                'year' => (int)$weekData['year'],
                'total_active' => (int)$weekData['total_active'],
                'on_vacation' => (int)$weekData['on_vacation'],
                'deliverable' => (int)$weekData['deliverable']
            ];
        } else {
        // Insert null for missing week
            $trend[] = [
                'snapshot_date' => null,
                'week_num' => $currentWeekNum,
                'year' => $currentYear,
                'total_active' => null,
                'on_vacation' => null,
                'deliverable' => null
            ];
        }
    }

    // Get by business unit with comparisons (using week_num)
    $stmt = $pdo->prepare("
        SELECT
            business_unit,
            SUM(total_active) as total,
            SUM(on_vacation) as on_vacation,
            SUM(deliverable) as deliverable,
            SUM(mail_delivery) as mail,
            SUM(carrier_delivery) as carrier,
            SUM(digital_only) as digital
        FROM daily_snapshots
        WHERE paper_code != 'FN'
          AND week_num = ?
          AND year = ?
        GROUP BY business_unit
    ");
    $stmt->execute([$week_num, $year]);
    $by_business_unit = [];
    $unit_comparisons = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $unitData = [
            'total' => (int)$row['total'],
            'on_vacation' => (int)$row['on_vacation'],
            'deliverable' => (int)$row['deliverable'],
            'mail' => (int)$row['mail'],
            'carrier' => (int)$row['carrier'],
            'digital' => (int)$row['digital'],
        ];
        $by_business_unit[$row['business_unit']] = $unitData;
    // Get comparison for this unit (use current snapshot_date)
        $unit_comparisons[$row['business_unit']] = getBusinessUnitComparison($pdo, $row['business_unit'], $current['snapshot_date'], $unitData);
    }

    // Get by edition (using week_num)
    $stmt = $pdo->prepare("
        SELECT
            paper_code,
            paper_name,
            business_unit,
            total_active as total,
            on_vacation,
            deliverable,
            mail_delivery as mail,
            carrier_delivery as carrier,
            digital_only as digital
        FROM daily_snapshots
        WHERE paper_code != 'FN'
          AND week_num = ?
          AND year = ?
        ORDER BY total_active DESC
    ");
    $stmt->execute([$week_num, $year]);
    $by_edition = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $by_edition[$row['paper_code']] = [
            'name' => $row['paper_name'],
            'business_unit' => $row['business_unit'],
            'total' => (int)$row['total'],
            'on_vacation' => (int)$row['on_vacation'],
            'deliverable' => (int)$row['deliverable'],
            'mail' => (int)$row['mail'],
            'carrier' => (int)$row['carrier'],
            'digital' => (int)$row['digital'],
        ];
    }

    // Get data range
    $dataRange = getDataRange($pdo);
// Phase 2: Analytics
    $forecast = forecastNextWeek($trend);
    $anomalies = detectAnomalies($trend);
    $performers = findPerformers($by_business_unit, $unit_comparisons);
    // Prepare backfill metadata
    $backfill_info = [
        'is_backfilled' => (bool)($current['is_backfilled'] ?? false),
        'backfill_weeks' => (int)($current['backfill_weeks'] ?? 0),
        'source_date' => $current['source_date'] ?? null,
        'source_filename' => $current['source_filename'] ?? null
    ];

    return [
        'has_data' => true,
        'week' => [
            'type' => 'week',
            'start' => $week['start'],
            'end' => $week['end'],
            'week_num' => $week['week_num'],
            'year' => $week['year'],
            'label' => "Week {$week['week_num']}, {$week['year']}",
            'date_range' => date('M j', strtotime($week['start'])) . ' - ' . date('M j, Y', strtotime($week['end']))
        ],
        'current' => [
            'snapshot_date' => $current['snapshot_date'],
            'total_active' => (int)$current['total_active'],
            'on_vacation' => (int)$current['on_vacation'],
            'deliverable' => (int)$current['deliverable'],
            'mail' => (int)$current['mail'],
            'carrier' => (int)$current['carrier'],
            'digital' => (int)$current['digital'],
        ],
        'backfill' => $backfill_info,
        'comparison' => $comparison,
        'comparison_message' => $comparison_message,
        'trend' => array_map(function ($row) {

            // Preserve null values for missing weeks
            return [
                'snapshot_date' => $row['snapshot_date'],
                'week_num' => $row['week_num'],
                'year' => $row['year'],
                'total_active' => $row['total_active'], // null if missing
                'on_vacation' => $row['on_vacation'],
                'deliverable' => $row['deliverable'],
            ];
        }, $trend),
        'by_business_unit' => $by_business_unit,
        'business_unit_comparisons' => $unit_comparisons,  // PHASE 2: Business unit comparisons
        'by_edition' => $by_edition,
        'data_range' => [
            'min_date' => $dataRange['min_date'],
            'max_date' => $dataRange['max_date'],
            'total_snapshots' => (int)$dataRange['total_snapshots']
        ],
        // PHASE 2: Analytics
        'analytics' => [
            'forecast' => $forecast,
            'anomalies' => $anomalies,
            'performers' => $performers
        ]
    ];
}

/**
 * Get paper detail
 */
function getPaperDetail($pdo, $paperCode)
{

    $stmt = $pdo->prepare("
        SELECT *
        FROM daily_snapshots
        WHERE paper_code = ?
        ORDER BY snapshot_date DESC
        LIMIT 1
    ");
    $stmt->execute([$paperCode]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get detail panel data for business unit
 * Returns data for: delivery breakdown, expiration chart, rate distribution, subscription length
 */
function getDetailPanelData($pdo, $businessUnit, $snapshotDate)
{

    // Get paper codes for this business unit
    $papers_stmt = $pdo->prepare("
        SELECT DISTINCT paper_code, paper_name
        FROM daily_snapshots
        WHERE business_unit = ?
        ORDER BY paper_code
    ");
    $papers_stmt->execute([$businessUnit]);
    $papers = $papers_stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($papers)) {
        throw new Exception("No papers found for business unit: $businessUnit");
    }

    $paper_codes = array_column($papers, 'paper_code');
    $placeholders = str_repeat('?,', count($paper_codes) - 1) . '?';
// Smart data window logic:
    // Any upload is valid for the 7 days preceding its date
    // Find the most recent upload that covers the requested date
    // (i.e., uploaded within 7 days AFTER the requested date)
    $date_check = $pdo->prepare("
        SELECT snapshot_date
        FROM subscriber_snapshots
        WHERE paper_code IN ($placeholders)
          AND snapshot_date >= ?
          AND snapshot_date <= DATE_ADD(?, INTERVAL 7 DAY)
        ORDER BY snapshot_date DESC
        LIMIT 1
    ");
    $date_check->execute(array_merge($paper_codes, [$snapshotDate, $snapshotDate]));
    $actual_date_result = $date_check->fetch(PDO::FETCH_ASSOC);
    if ($actual_date_result && $actual_date_result['snapshot_date']) {
    // Found an upload within the 7-day window
        $actualSnapshotDate = $actual_date_result['snapshot_date'];
    } else {
    // No upload found in the 7-day window, try to find most recent before requested date
        $fallback_check = $pdo->prepare("
            SELECT MAX(snapshot_date) as snapshot_date
            FROM subscriber_snapshots
            WHERE paper_code IN ($placeholders)
              AND snapshot_date <= ?
        ");
        $fallback_check->execute(array_merge($paper_codes, [$snapshotDate]));
        $fallback_result = $fallback_check->fetch(PDO::FETCH_ASSOC);
        if ($fallback_result && $fallback_result['snapshot_date']) {
            $actualSnapshotDate = $fallback_result['snapshot_date'];
        } else {
            $actualSnapshotDate = $snapshotDate;
        // No data found, will return empty
        }
    }

    $snapshotDate = $actualSnapshotDate;
// Get comparison data for this business unit
    $comparison_data = getBusinessUnitComparison($pdo, $businessUnit, $snapshotDate, []);
    $response = [
        'business_unit' => $businessUnit,
        'snapshot_date' => $snapshotDate,
        'papers' => $papers,
        'comparison' => $comparison_data,
        'delivery_breakdown' => [],
        'expiration_chart' => [],
        'rate_distribution' => [],
        'subscription_length' => []
    ];
// 1. Current delivery breakdown (from daily_snapshots)
    $delivery_stmt = $pdo->prepare("
        SELECT
            SUM(total_active) as total_active,
            SUM(deliverable) as deliverable,
            SUM(mail_delivery) as mail_delivery,
            SUM(carrier_delivery) as carrier_delivery,
            SUM(digital_only) as digital_only,
            SUM(on_vacation) as on_vacation
        FROM daily_snapshots
        WHERE snapshot_date = ? AND paper_code IN ($placeholders)
    ");
    $delivery_stmt->execute(array_merge([$snapshotDate], $paper_codes));
    $delivery_data = $delivery_stmt->fetch(PDO::FETCH_ASSOC);
    if ($delivery_data) {
        $response['delivery_breakdown'] = [
            'total_active' => (int)$delivery_data['total_active'],
            'deliverable' => (int)$delivery_data['deliverable'],
            'mail_delivery' => (int)$delivery_data['mail_delivery'],
            'carrier_delivery' => (int)$delivery_data['carrier_delivery'],
            'digital_only' => (int)$delivery_data['digital_only'],
            'on_vacation' => (int)$delivery_data['on_vacation']
        ];
    }

    // 2. 4-week expiration chart data (exclude "Later" to avoid skewing)
    // IMPORTANT: Use snapshot_date (not CURDATE) for historical accuracy
    $expiration_stmt = $pdo->prepare("
        SELECT
            CASE
                WHEN paid_thru < ? THEN 'Past Due'
                WHEN paid_thru BETWEEN ? AND DATE_ADD(?, INTERVAL 7 DAY) THEN 'This Week'
                WHEN paid_thru BETWEEN DATE_ADD(?, INTERVAL 8 DAY) AND DATE_ADD(?, INTERVAL 14 DAY) THEN 'Next Week'
                WHEN paid_thru BETWEEN DATE_ADD(?, INTERVAL 15 DAY) AND DATE_ADD(?, INTERVAL 21 DAY) THEN 'Week +2'
            END as week_bucket,
            COUNT(*) as count
        FROM subscriber_snapshots
        WHERE snapshot_date = ?
            AND paper_code IN ($placeholders)
            AND paid_thru IS NOT NULL
            AND paid_thru <= DATE_ADD(?, INTERVAL 21 DAY)
        GROUP BY week_bucket
        ORDER BY
            CASE week_bucket
                WHEN 'Past Due' THEN 1
                WHEN 'This Week' THEN 2
                WHEN 'Next Week' THEN 3
                WHEN 'Week +2' THEN 4
            END
    ");
// Pass snapshot_date for each ? placeholder: 9 total (7 in CASE + 1 in WHERE + 1 in final condition)
    $expiration_stmt->execute(array_merge([$snapshotDate, $snapshotDate, $snapshotDate, $snapshotDate, $snapshotDate, $snapshotDate, $snapshotDate, $snapshotDate], $paper_codes, [$snapshotDate]));
    $expiration_data = $expiration_stmt->fetchAll(PDO::FETCH_ASSOC);
    $response['expiration_chart'] = array_map(function ($row) {

        return [
            'week_bucket' => $row['week_bucket'],
            'count' => (int)$row['count']
        ];
    }, $expiration_data);
// 3. Rate distribution (all rates with at least 1 subscriber)
    $rate_stmt = $pdo->prepare("
        SELECT
            rate_name,
            COUNT(*) as count
        FROM subscriber_snapshots
        WHERE snapshot_date = ?
            AND paper_code IN ($placeholders)
            AND rate_name IS NOT NULL
            AND rate_name != ''
        GROUP BY rate_name
        ORDER BY count DESC
    ");
    $rate_stmt->execute(array_merge([$snapshotDate], $paper_codes));
    $rate_data = $rate_stmt->fetchAll(PDO::FETCH_ASSOC);
    $response['rate_distribution'] = array_map(function ($row) {

        return [
            'rate_name' => $row['rate_name'],
            'count' => (int)$row['count']
        ];
    }, $rate_data);
// 4. Subscription length distribution (normalize 12 M and 1 Y)
    $length_stmt = $pdo->prepare("
        SELECT
            CASE
                WHEN subscription_length IN ('12 M', '12M', '1 Y', '1Y') THEN '12 M (1 Year)'
                ELSE subscription_length
            END as subscription_length,
            COUNT(*) as count
        FROM subscriber_snapshots
        WHERE snapshot_date = ?
            AND paper_code IN ($placeholders)
            AND subscription_length IS NOT NULL
            AND subscription_length != ''
        GROUP BY subscription_length
        ORDER BY count DESC
    ");
    $length_stmt->execute(array_merge([$snapshotDate], $paper_codes));
    $length_data = $length_stmt->fetchAll(PDO::FETCH_ASSOC);
    $response['subscription_length'] = array_map(function ($row) {

        return [
            'subscription_length' => $row['subscription_length'],
            'count' => (int)$row['count']
        ];
    }, $length_data);
    return $response;
}

/**
 * Get subscriber list for a specific metric
 * For context menu drill-down functionality
 *
 * @param PDO $pdo Database connection
 * @param array $params Query parameters
 * @return array Subscriber data
 */
function getSubscribers($pdo, $params)
{

    $businessUnit = $params['business_unit'] ?? '';
    $snapshotDate = $params['snapshot_date'] ?? date('Y-m-d');
    $metricType = $params['metric_type'] ?? '';
    $metricValue = $params['metric_value'] ?? '';
// Validate required parameters
    if (empty($businessUnit) || empty($metricType) || empty($metricValue)) {
        throw new Exception('Missing required parameters: business_unit, metric_type, metric_value');
    }

    // Get Saturday for requested date
    $saturday = getSaturdayForWeek($snapshotDate);
// Find nearest available snapshot
    $stmt = $pdo->prepare("
        SELECT snapshot_date
        FROM daily_snapshots
        WHERE business_unit = :business_unit
        AND snapshot_date <= :saturday
        ORDER BY snapshot_date DESC
        LIMIT 1
    ");
    $stmt->execute([
        ':business_unit' => $businessUnit,
        ':saturday' => $saturday
    ]);
    $actualDate = $stmt->fetchColumn();
    if (!$actualDate) {
        throw new Exception('No data available for this business unit and date');
    }

    // Query real subscriber data from subscriber_snapshots table
    $subscribers = [];
    switch ($metricType) {
        case 'expiration':
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       $subscribers = getExpirationSubscribers($pdo, $businessUnit, $actualDate, $metricValue);

            break;
        case 'rate':
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       $subscribers = getRateSubscribers($pdo, $businessUnit, $actualDate, $metricValue);

            break;
        case 'subscription_length':
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       $subscribers = getSubscriptionLengthSubscribers($pdo, $businessUnit, $actualDate, $metricValue);

            break;
        default:
            throw new Exception('Invalid metric_type: ' . $metricType);
    }

    return [
        'metric_type' => $metricType,
        'metric' => $metricValue,
        'count' => count($subscribers),
        'snapshot_date' => $actualDate,
        'requested_date' => $snapshotDate,
        'business_unit' => $businessUnit,
        'subscribers' => $subscribers
    ];
}

/**
 * Get subscribers by expiration bucket
 */
function getExpirationSubscribers($pdo, $businessUnit, $snapshotDate, $bucket)
{

    // Calculate date range for bucket
    $today = new DateTime($snapshotDate);
    $weekStart = clone $today;
    $weekEnd = clone $today;
    switch ($bucket) {
        case 'Past Due':
            // paid_thru < snapshot_date

                                                                                                                                                            $stmt = $pdo->prepare("
                SELECT
                    sub_num as account_id,
                    name as subscriber_name,
                    phone,
                    email,
                    CONCAT(COALESCE(address, ''), ', ', COALESCE(city_state_postal, '')) as mailing_address,
                    paper_code,
                    paper_name,
                    rate_name as current_rate,
                    last_payment_amount as rate_amount,
                    last_payment_amount,
                    payment_status as payment_method,
                    paid_thru as expiration_date,
                    delivery_type
                FROM subscriber_snapshots
                WHERE business_unit = :business_unit
                AND snapshot_date = :snapshot_date
                AND paid_thru < :snapshot_date
                ORDER BY paid_thru ASC
                LIMIT 1000
            ");
            $stmt->execute([
                ':business_unit' => $businessUnit,
                ':snapshot_date' => $snapshotDate,
            ]);

            break;
        case 'This Week':
            // paid_thru between snapshot_date and snapshot_date + 6 days

                                                                                                                                                            $weekEnd->modify('+6 days');
            $stmt = $pdo->prepare("
                SELECT
                    sub_num as account_id,
                    name as subscriber_name,
                    phone,
                    email,
                    CONCAT(COALESCE(address, ''), ', ', COALESCE(city_state_postal, '')) as mailing_address,
                    paper_code,
                    paper_name,
                    rate_name as current_rate,
                    last_payment_amount as rate_amount,
                    last_payment_amount,
                    payment_status as payment_method,
                    paid_thru as expiration_date,
                    delivery_type
                FROM subscriber_snapshots
                WHERE business_unit = :business_unit
                AND snapshot_date = :snapshot_date
                AND paid_thru >= :start_date
                AND paid_thru <= :end_date
                ORDER BY paid_thru ASC
                LIMIT 1000
            ");
            $stmt->execute([
                ':business_unit' => $businessUnit,
                ':snapshot_date' => $snapshotDate,
                ':start_date' => $snapshotDate,
                ':end_date' => $weekEnd->format('Y-m-d')
            ]);

            break;
        case 'Next Week':
            // paid_thru between snapshot_date + 7 and snapshot_date + 13 days

                                                                                                                                                            $weekStart->modify('+7 days');
            $weekEnd->modify('+13 days');
            $stmt = $pdo->prepare("
                SELECT
                    sub_num as account_id,
                    name as subscriber_name,
                    phone,
                    email,
                    CONCAT(COALESCE(address, ''), ', ', COALESCE(city_state_postal, '')) as mailing_address,
                    paper_code,
                    paper_name,
                    rate_name as current_rate,
                    last_payment_amount as rate_amount,
                    last_payment_amount,
                    payment_status as payment_method,
                    paid_thru as expiration_date,
                    delivery_type
                FROM subscriber_snapshots
                WHERE business_unit = :business_unit
                AND snapshot_date = :snapshot_date
                AND paid_thru >= :start_date
                AND paid_thru <= :end_date
                ORDER BY paid_thru ASC
                LIMIT 1000
            ");
            $stmt->execute([
                ':business_unit' => $businessUnit,
                ':snapshot_date' => $snapshotDate,
                ':start_date' => $weekStart->format('Y-m-d'),
                ':end_date' => $weekEnd->format('Y-m-d')
            ]);

            break;
        case 'Week +2':
            // paid_thru between snapshot_date + 14 and snapshot_date + 20 days

                                                                                                                                                            $weekStart->modify('+14 days');
            $weekEnd->modify('+20 days');
            $stmt = $pdo->prepare("
                SELECT
                    sub_num as account_id,
                    name as subscriber_name,
                    phone,
                    email,
                    CONCAT(COALESCE(address, ''), ', ', COALESCE(city_state_postal, '')) as mailing_address,
                    paper_code,
                    paper_name,
                    rate_name as current_rate,
                    last_payment_amount as rate_amount,
                    last_payment_amount,
                    payment_status as payment_method,
                    paid_thru as expiration_date,
                    delivery_type
                FROM subscriber_snapshots
                WHERE business_unit = :business_unit
                AND snapshot_date = :snapshot_date
                AND paid_thru >= :start_date
                AND paid_thru <= :end_date
                ORDER BY paid_thru ASC
                LIMIT 1000
            ");
            $stmt->execute([
                ':business_unit' => $businessUnit,
                ':snapshot_date' => $snapshotDate,
                ':start_date' => $weekStart->format('Y-m-d'),
                ':end_date' => $weekEnd->format('Y-m-d')
            ]);

            break;
        default:
            return [];
    }

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get subscribers by rate
 */
function getRateSubscribers($pdo, $businessUnit, $snapshotDate, $rateName)
{

    $stmt = $pdo->prepare("
        SELECT
            sub_num as account_id,
            name as subscriber_name,
            phone,
            email,
            CONCAT(COALESCE(address, ''), ', ', COALESCE(city_state_postal, '')) as mailing_address,
            paper_code,
            paper_name,
            rate_name as current_rate,
            last_payment_amount as rate_amount,
            last_payment_amount,
            payment_status as payment_method,
            paid_thru as expiration_date,
            delivery_type
        FROM subscriber_snapshots
        WHERE business_unit = :business_unit
        AND snapshot_date = :snapshot_date
        AND rate_name = :rate_name
        ORDER BY sub_num ASC
        LIMIT 1000
    ");
    $stmt->execute([
        ':business_unit' => $businessUnit,
        ':snapshot_date' => $snapshotDate,
        ':rate_name' => $rateName
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get subscribers by subscription length
 */
function getSubscriptionLengthSubscribers($pdo, $businessUnit, $snapshotDate, $length)
{

    // Use same normalization as detail panel to match aggregated labels
    $stmt = $pdo->prepare("
        SELECT
            sub_num as account_id,
            name as subscriber_name,
            phone,
            email,
            CONCAT(COALESCE(address, ''), ', ', COALESCE(city_state_postal, '')) as mailing_address,
            paper_code,
            paper_name,
            rate_name as current_rate,
            last_payment_amount as rate_amount,
            last_payment_amount,
            payment_status as payment_method,
            paid_thru as expiration_date,
            delivery_type
        FROM subscriber_snapshots
        WHERE business_unit = :business_unit
        AND snapshot_date = :snapshot_date
        AND (
            CASE
                WHEN subscription_length IN ('12 M', '12M', '1 Y', '1Y') THEN '12 M (1 Year)'
                ELSE subscription_length
            END
        ) = :length
        ORDER BY sub_num ASC
        LIMIT 1000
    ");
    $stmt->execute([
        ':business_unit' => $businessUnit,
        ':snapshot_date' => $snapshotDate,
        ':length' => $length
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * OLD FUNCTION - No longer used (kept for reference)
 * Use getExpirationSubscribers(), getRateSubscribers(), getSubscriptionLengthSubscribers() instead
 */
function generateMockSubscribers_DEPRECATED($businessUnit, $count, $metricType, $metricValue)
{

    $subscribers = [];
// State-specific data
    $stateData = [
        'South Carolina' => [
            'state' => 'SC',
            'cities' => ['Camden', 'Lugoff', 'Elgin', 'Westville'],
            'papers' => ['TJ' => 'The Journal'],
            'zip_prefix' => '290'
        ],
        'Michigan' => [
            'state' => 'MI',
            'cities' => ['Onaway', 'Millersburg', 'Tower', 'Rogers City'],
            'papers' => ['TA' => 'The Advertiser'],
            'zip_prefix' => '497'
        ],
        'Wyoming' => [
            'state' => 'WY',
            'cities' => ['Lander', 'Riverton', 'Thermopolis', 'Dubois'],
            'papers' => ['TJ' => 'The Journal', 'TR' => 'The Ranger', 'LJ' => 'The Lander Journal', 'WRN' => 'Wind River News'],
            'zip_prefix' => '825'
        ]
    ];
    $state = $stateData[$businessUnit] ?? $stateData['Wyoming'];
    $papers = array_keys($state['papers']);
    $firstNames = ['John', 'Mary', 'Robert', 'Patricia', 'Michael', 'Linda', 'William', 'Barbara', 'David', 'Elizabeth',
                   'James', 'Jennifer', 'Richard', 'Maria', 'Joseph', 'Susan', 'Thomas', 'Margaret', 'Charles', 'Dorothy'];
    $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez',
                  'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin'];
    $rates = ['Senior 6mo', 'Standard 12mo', 'Senior 12mo', 'Military 6mo', 'Student 3mo', 'Digital Only', 'Premium 12mo'];
    $paymentMethods = ['Check', 'Credit Card', 'Cash', 'Money Order', 'Auto-Pay'];
    $deliveryTypes = ['MAIL', 'CARR', 'INTE'];
// Limit to 1000 for performance
    $limit = min($count, 1000);

    for ($i = 0; $i < $limit; $i++) {
        $firstName = $firstNames[$i % count($firstNames)];
        $lastName = $lastNames[($i + 7) % count($lastNames)];
        $city = $state['cities'][$i % count($state['cities'])];
        $paperCode = $papers[$i % count($papers)];
        $paperName = $state['papers'][$paperCode];
// Generate expiration date based on metric type
        $expirationDate = date('Y-m-d', strtotime('+' . (($i % 30) - 10) . ' days'));
        if ($metricType === 'expiration') {
            switch ($metricValue) {
                case 'Past Due':
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             $expirationDate = date('Y-m-d', strtotime('-' . ($i % 14 + 1) . ' days'));

                    break;
                case 'This Week':
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             $expirationDate = date('Y-m-d', strtotime('+' . ($i % 7) . ' days'));

                    break;
                case 'Next Week':
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             $expirationDate = date('Y-m-d', strtotime('+' . (7 + $i % 7) . ' days'));

                    break;
                case 'Week +2':
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             $expirationDate = date('Y-m-d', strtotime('+' . (14 + $i % 7) . ' days'));

                    break;
            }
        }

        $accountId = strtoupper(substr($state['state'], 0, 2)) . '-' . str_pad((string)(10000 + $i), 5, '0', STR_PAD_LEFT);
        $rate = $rates[$i % count($rates)];
        $rateAmount = 25.00 + (($i % 10) * 5.00);
        $subscribers[] = [
            'account_id' => $accountId,
            'subscriber_name' => $firstName . ' ' . $lastName,
            'phone' => '(' . $state['zip_prefix'] . ') 555-' . str_pad((string)($i % 10000), 4, '0', STR_PAD_LEFT),
            'email' => strtolower($firstName . '.' . $lastName . '@example.com'),
            'mailing_address' => ($i * 100 + 100) . ' Main St, ' . $city . ', ' . $state['state'] . ' ' . $state['zip_prefix'] . str_pad((string)($i % 100), 2, '0', STR_PAD_LEFT),
            'paper_code' => $paperCode,
            'paper_name' => $paperName,
            'current_rate' => $rate,
            'rate_amount' => number_format($rateAmount, 2, '.', ''),
            'last_payment_amount' => number_format($rateAmount, 2, '.', ''),
            'payment_method' => $paymentMethods[$i % count($paymentMethods)],
            'expiration_date' => $expirationDate,
            'delivery_type' => $deliveryTypes[$i % count($deliveryTypes)]
        ];
    }

    return $subscribers;
}

/**
 * Get count for a specific metric at a snapshot date
 * Handles different metric types (expiration, rate, subscription_length)
 *
 * @param PDO $pdo Database connection
 * @param string $businessUnit Business unit
 * @param string $metricType Type of metric (expiration, rate, subscription_length)
 * @param string $metricValue Value of metric (e.g., 'Past Due', '12 M (1 Year)', etc.)
 * @param string $snapshotDate Snapshot date
 * @return int Count for this metric
 */
function getMetricCount($pdo, $businessUnit, $metricType, $metricValue, $snapshotDate)
{
    if ($metricType === 'expiration') {
        // Calculate expiration bucket from paid_thru date
        // Expiration buckets: "Past Due", "This Week", "Next Week", "Week +2", "Later"

        // Get the reference date for this snapshot (calculate week boundaries)
        $snapshotDt = new DateTime($snapshotDate);

        // Calculate week boundaries based on metric value
        $whereClause = '';
        if ($metricValue === 'Past Due') {
            $whereClause = "ss.paid_thru < :snapshot_date";
        } elseif ($metricValue === 'This Week') {
            $weekStart = clone $snapshotDt;
            $weekStart->modify('this week'); // Monday
            $weekEnd = clone $weekStart;
            $weekEnd->modify('+6 days'); // Sunday
            $whereClause = "ss.paid_thru BETWEEN :week_start AND :week_end";
        } elseif ($metricValue === 'Next Week') {
            $weekStart = clone $snapshotDt;
            $weekStart->modify('next week'); // Next Monday
            $weekEnd = clone $weekStart;
            $weekEnd->modify('+6 days'); // Next Sunday
            $whereClause = "ss.paid_thru BETWEEN :week_start AND :week_end";
        } elseif ($metricValue === 'Week +2') {
            $weekStart = clone $snapshotDt;
            $weekStart->modify('next week')->modify('+1 week'); // Week after next Monday
            $weekEnd = clone $weekStart;
            $weekEnd->modify('+6 days'); // Sunday
            $whereClause = "ss.paid_thru BETWEEN :week_start AND :week_end";
        } else { // "Later" or any other bucket
            $laterStart = clone $snapshotDt;
            $laterStart->modify('next week')->modify('+2 weeks'); // 3 weeks from now
            $whereClause = "ss.paid_thru >= :later_start";
        }

        $sql = "
            SELECT COUNT(*) as count
            FROM subscriber_snapshots ss
            WHERE ss.business_unit = :business_unit
            AND ss.snapshot_date = :snapshot_date
            AND $whereClause
        ";

        $stmt = $pdo->prepare($sql);
        $params = [
            ':business_unit' => $businessUnit,
            ':snapshot_date' => $snapshotDate
        ];

        // Add date range parameters based on metric
        if ($metricValue === 'This Week' || $metricValue === 'Next Week' || $metricValue === 'Week +2') {
            $params[':week_start'] = $weekStart->format('Y-m-d');
            $params[':week_end'] = $weekEnd->format('Y-m-d');
        } elseif ($metricValue === 'Later') {
            $params[':later_start'] = $laterStart->format('Y-m-d');
        }

        $stmt->execute($params);

    } elseif ($metricType === 'rate') {
        // Query subscriber_snapshots for rate distribution
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM subscriber_snapshots ss
            WHERE ss.business_unit = :business_unit
            AND ss.snapshot_date = :snapshot_date
            AND ss.rate_name = :metric_value
        ");
        $stmt->execute([
            ':business_unit' => $businessUnit,
            ':snapshot_date' => $snapshotDate,
            ':metric_value' => $metricValue
        ]);
    } elseif ($metricType === 'subscription_length') {
        // Query subscriber_snapshots for subscription length
        // Use same normalization as detail panel to match aggregated labels
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM subscriber_snapshots ss
            WHERE ss.business_unit = :business_unit
            AND ss.snapshot_date = :snapshot_date
            AND (
                CASE
                    WHEN ss.subscription_length IN ('12 M', '12M', '1 Y', '1Y') THEN '12 M (1 Year)'
                    ELSE ss.subscription_length
                END
            ) = :metric_value
        ");
        $stmt->execute([
            ':business_unit' => $businessUnit,
            ':snapshot_date' => $snapshotDate,
            ':metric_value' => $metricValue
        ]);
    } else {
        return 0;
    }

    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int)($result['count'] ?? 0);
}

/**
 * Get historical trend data for a specific metric
 * For trend chart visualization
 *
 * @param PDO $pdo Database connection
 * @param array $params Query parameters
 * @return array Trend data points
 */
function getHistoricalTrend($pdo, $params)
{

    $businessUnit = $params['business_unit'] ?? '';
    $metricType = $params['metric_type'] ?? '';
    $metricValue = $params['metric_value'] ?? '';
    $timeRange = $params['time_range'] ?? '12weeks';
    $endDate = $params['end_date'] ?? date('Y-m-d');
// Validate required parameters
    if (empty($businessUnit) || empty($metricType) || empty($metricValue)) {
        throw new Exception('Missing required parameters: business_unit, metric_type, metric_value');
    }

    // Parse time range
    $weeksMap = [
        '4weeks' => 4,
        '12weeks' => 12,
        '26weeks' => 26,
        '52weeks' => 52
    ];
    $numWeeks = $weeksMap[$timeRange] ?? 12;
// Get Saturday for end date
    $endSaturday = getSaturdayForWeek($endDate);
// Calculate start date
    $startDate = date('Y-m-d', strtotime($endSaturday . ' -' . ($numWeeks * 7) . ' days'));
// Get all snapshots in range
    $stmt = $pdo->prepare("
        SELECT DISTINCT snapshot_date
        FROM daily_snapshots
        WHERE business_unit = :business_unit
        AND snapshot_date BETWEEN :start_date AND :end_date
        ORDER BY snapshot_date ASC
    ");
    $stmt->execute([
        ':business_unit' => $businessUnit,
        ':start_date' => $startDate,
        ':end_date' => $endSaturday
    ]);
    $snapshotDates = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Query actual data for each snapshot
    $dataPoints = [];
    foreach ($snapshotDates as $index => $snapshotDate) {
        // Get count for this metric at this snapshot
        $count = getMetricCount($pdo, $businessUnit, $metricType, $metricValue, $snapshotDate);

        $prevValue = $index > 0 ? $dataPoints[$index - 1]['count'] : $count;
        $change = $count - $prevValue;
        $changePercent = $prevValue > 0 ? round(($change / $prevValue) * 100, 1) : 0;

        $dataPoints[] = [
            'snapshot_date' => $snapshotDate,
            'count' => $count,
            'change_from_previous' => $change,
            'change_percent' => $changePercent
        ];
    }

    return [
        'metric_type' => $metricType,
        'metric' => $metricValue,
        'time_range' => $timeRange,
        'business_unit' => $businessUnit,
        'start_date' => $startDate,
        'end_date' => $endSaturday,
        'data_points' => $dataPoints
    ];
}

function sendResponse($data)
{

    echo json_encode([
        'success' => true,
        'data' => $data
    ], JSON_PRETTY_PRINT);
}

function sendError($message)
{

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $message
    ], JSON_PRETTY_PRINT);
}

// Main execution
try {
    $pdo = connectDB($db_config);
    $action = $_GET['action'] ?? 'overview';
    switch ($action) {
        case 'overview':
                                                                                                                                                                                                                                                                 $params = [
                'date' => $_GET['date'] ?? null,
                'compare' => $_GET['compare'] ?? 'yoy',
                                                                                                                                                                                                                                                                 ];
                                                                                                                                                                                                                                                                 $data = getOverviewEnhanced($pdo, $params);
                                                                                                                                                                                                                                                                 sendResponse($data);

            break;
        case 'business_unit_detail':
                                                                                                                                                                                                                                                                 $unitName = $_GET['unit'] ?? '';
            $date = $_GET['date'] ?? null;
            if (empty($unitName)) {
                sendError('Business unit name is required');
                break;
            }
            $data = getBusinessUnitDetail($pdo, $unitName, $date);
            sendResponse($data);

            break;
        case 'paper':
                                                                                                                                                                                                                                                                 $paperCode = $_GET['code'] ?? '';
            if (empty($paperCode)) {
                sendError('Paper code is required');
                break;
            }
            $data = getPaperDetail($pdo, $paperCode);
            sendResponse($data);

            break;
        case 'data_range':
                                                                                                                                                                                                                                                                 $data = getDataRange($pdo);
            sendResponse($data);

            break;
        case 'detail_panel':
                                                                                                                                                                                                                                                                 $businessUnit = $_GET['business_unit'] ?? '';
            $snapshotDate = $_GET['snapshot_date'] ?? date('Y-m-d');
            if (empty($businessUnit)) {
                sendError('business_unit parameter is required');
                break;
            }
            $data = getDetailPanelData($pdo, $businessUnit, $snapshotDate);
            sendResponse($data);

            break;
        case 'get_subscribers':
                                                                                                                                                                                                                                                                 $params = [
                'business_unit' => $_GET['business_unit'] ?? '',
                'snapshot_date' => $_GET['snapshot_date'] ?? date('Y-m-d'),
                'metric_type' => $_GET['metric_type'] ?? '',
                'metric_value' => $_GET['metric_value'] ?? ''
                                                                                                                                                                                                                                                                 ];
                                                                                                                                                                                                                                                                 $data = getSubscribers($pdo, $params);
                                                                                                                                                                                                                                                                 sendResponse($data);

            break;
        case 'get_trend':
                                                                                                                                                                                                                                                                 $params = [
                'business_unit' => $_GET['business_unit'] ?? '',
                'metric_type' => $_GET['metric_type'] ?? '',
                'metric_value' => $_GET['metric_value'] ?? '',
                'time_range' => $_GET['time_range'] ?? '12weeks',
                'end_date' => $_GET['end_date'] ?? date('Y-m-d')
                                                                                                                                                                                                                                                                 ];
                                                                                                                                                                                                                                                                 $data = getHistoricalTrend($pdo, $params);
                                                                                                                                                                                                                                                                 sendResponse($data);

            break;
        default:
                                                                                                                                                                                                                                                                 sendError('Invalid action: ' . $action);

            break;
    }
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage());
}
