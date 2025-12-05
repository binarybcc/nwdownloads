<?php
/**
 * Circulation Dashboard API - Phase 2
 * Advanced comparisons, drill-down, analytics, forecasting
 * Author: Claude Code
 * Date: 2025-12-01
 */

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
function connectDB($config) {
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
function getWeekBoundaries($date) {
    $dt = new DateTime($date);
    $dayOfWeek = (int)$dt->format('w'); // 0=Sunday, 6=Saturday

    // Find Sunday of this week
    $sunday = clone $dt;
    $sunday->modify('-' . $dayOfWeek . ' days');

    // Find Saturday of this week
    $saturday = clone $sunday;
    $saturday->modify('+6 days');

    return [
        'start' => $sunday->format('Y-m-d'),
        'end' => $saturday->format('Y-m-d'),
        'week_num' => (int)$sunday->format('W'),
        'year' => (int)$sunday->format('Y')
    ];
}

/**
 * Get Saturday date for a week (our snapshot day)
 */
function getSaturdayForWeek($date) {
    $boundaries = getWeekBoundaries($date);
    return $boundaries['end'];
}

/**
 * Get data range available in database
 */
function getDataRange($pdo) {
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
 * Get the most recent complete Saturday
 * A Saturday is considered "complete" if it has data for all active business units
 */
function getMostRecentCompleteSaturday($pdo) {
    // Get the most recent Saturday with data
    $stmt = $pdo->query("
        SELECT snapshot_date
        FROM daily_snapshots
        WHERE paper_code != 'FN'
          AND DAYOFWEEK(snapshot_date) = 7
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
function calculateTrendDirection($trend) {
    if (count($trend) < 8) {
        return 'stable';
    }

    // Calculate 4-week moving averages
    $recentWeeks = array_slice($trend, -4);
    $olderWeeks = array_slice($trend, 0, 8);

    $recentAvg = array_sum(array_column($recentWeeks, 'total_active')) / count($recentWeeks);
    $olderAvg = array_sum(array_column($olderWeeks, 'total_active')) / count($olderWeeks);

    $changePercent = $olderAvg > 0 ? (($recentAvg - $olderAvg) / $olderAvg) * 100 : 0;

    if ($changePercent > 2) return 'growing';
    if ($changePercent < -2) return 'declining';
    return 'stable';
}

/**
 * Get business unit comparison data (YoY and Previous Week)
 */
function getBusinessUnitComparison($pdo, $unitName, $currentDate, $currentData) {
    $saturday = getSaturdayForWeek($currentDate);

    // Year-over-year comparison
    $lastYearDate = date('Y-m-d', strtotime($saturday . ' -1 year'));
    $lastYearSaturday = getSaturdayForWeek($lastYearDate);

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
          AND snapshot_date = ?
    ");
    $stmt->execute([$unitName, $lastYearSaturday]);
    $yoyData = $stmt->fetch(PDO::FETCH_ASSOC);

    // Previous week comparison
    $prevWeekDate = date('Y-m-d', strtotime($saturday . ' -7 days'));
    $stmt->execute([$unitName, $prevWeekDate]);
    $prevWeekData = $stmt->fetch(PDO::FETCH_ASSOC);

    $comparison = [
        'yoy' => null,
        'previous_week' => null,
        'trend_direction' => 'stable'
    ];

    if ($yoyData && $yoyData['total'] > 0) {
        $comparison['yoy'] = [
            'total' => (int)$yoyData['total'],
            'change' => $currentData['total'] - (int)$yoyData['total'],
            'change_percent' => round((($currentData['total'] - (int)$yoyData['total']) / (int)$yoyData['total']) * 100, 1)
        ];
    }

    if ($prevWeekData && $prevWeekData['total'] > 0) {
        $comparison['previous_week'] = [
            'total' => (int)$prevWeekData['total'],
            'change' => $currentData['total'] - (int)$prevWeekData['total'],
            'change_percent' => round((($currentData['total'] - (int)$prevWeekData['total']) / (int)$prevWeekData['total']) * 100, 1)
        ];
    }

    // Get 12-week trend for trend direction
    $trendStart = date('Y-m-d', strtotime($saturday . ' -84 days'));
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
function getBusinessUnitDetail($pdo, $unitName, $date = null) {
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
        'trend' => array_map(function($row) {
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
function forecastNextWeek($trend) {
    $n = count($trend);
    if ($n < 4) {
        return null; // Not enough data
    }

    $sumX = 0;
    $sumY = 0;
    $sumXY = 0;
    $sumX2 = 0;

    foreach ($trend as $i => $week) {
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
    $lastActual = $trend[$n - 1]['total_active'];

    // Calculate confidence based on variance
    $variance = 0;
    foreach ($trend as $i => $week) {
        $predicted = $slope * ($i + 1) + $intercept;
        $variance += pow($week['total_active'] - $predicted, 2);
    }
    $stdDev = sqrt($variance / $n);
    $avgValue = $sumY / $n;
    $confidenceRatio = $stdDev / $avgValue;

    $confidence = 'medium';
    if ($confidenceRatio < 0.02) $confidence = 'high';
    elseif ($confidenceRatio > 0.05) $confidence = 'low';

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
function detectAnomalies($trend) {
    if (count($trend) < 4) {
        return [];
    }

    $values = array_column($trend, 'total_active');
    $mean = array_sum($values) / count($values);
    $variance = 0;
    foreach ($values as $value) {
        $variance += pow($value - $mean, 2);
    }
    $stdDev = sqrt($variance / count($values));

    $anomalies = [];
    foreach ($trend as $i => $week) {
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
function findPerformers($by_business_unit, $comparisons) {
    $performers = [
        'strongest' => null,
        'weakest' => null
    ];

    $maxChange = -PHP_INT_MAX;
    $minChange = PHP_INT_MAX;

    foreach ($by_business_unit as $unitName => $data) {
        if (!isset($comparisons[$unitName]['yoy'])) continue;

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
function getOverviewEnhanced($pdo, $params) {
    // Parse parameters
    // If no date provided, use most recent complete Saturday instead of today
    $requestedDate = $params['date'] ?? getMostRecentCompleteSaturday($pdo);
    $compareMode = $params['compare'] ?? 'yoy'; // yoy, previous, none

    // Get week boundaries for requested date
    $week = getWeekBoundaries($requestedDate);
    $saturday = $week['end'];

    // Get data for the Saturday of this week
    $stmt = $pdo->prepare("
        SELECT
            snapshot_date,
            SUM(total_active) as total_active,
            SUM(on_vacation) as on_vacation,
            SUM(deliverable) as deliverable,
            SUM(mail_delivery) as mail,
            SUM(carrier_delivery) as carrier,
            SUM(digital_only) as digital
        FROM daily_snapshots
        WHERE paper_code != 'FN'
          AND snapshot_date = ?
        GROUP BY snapshot_date
    ");
    $stmt->execute([$saturday]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    // If no data for exact Saturday, find nearest date
    if (!$current) {
        $stmt = $pdo->prepare("
            SELECT
                snapshot_date,
                SUM(total_active) as total_active,
                SUM(on_vacation) as on_vacation,
                SUM(deliverable) as deliverable,
                SUM(mail_delivery) as mail,
                SUM(carrier_delivery) as carrier,
                SUM(digital_only) as digital
            FROM daily_snapshots
            WHERE paper_code != 'FN'
              AND snapshot_date <= ?
            GROUP BY snapshot_date
            ORDER BY snapshot_date DESC
            LIMIT 1
        ");
        $stmt->execute([$saturday]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($current) {
            // Update week boundaries based on actual data date
            $week = getWeekBoundaries($current['snapshot_date']);
            $saturday = $week['end'];
        }
    }

    if (!$current) {
        sendError('No data available for requested date');
        exit;
    }

    // Get comparison data
    $comparison = null;
    if ($compareMode === 'yoy') {
        // Year-over-year comparison (same week number last year)
        $lastYearDate = date('Y-m-d', strtotime($saturday . ' -1 year'));
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
        $stmt->execute([$saturday, $lastYearSaturday]);
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
            $params = array_merge([$saturday], $commonUnits);
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
        }
    } elseif ($compareMode === 'previous') {
        // Previous week comparison
        $prevWeekDate = date('Y-m-d', strtotime($saturday . ' -7 days'));

        $stmt = $pdo->prepare("
            SELECT
                snapshot_date,
                SUM(total_active) as total_active,
                SUM(on_vacation) as on_vacation,
                SUM(deliverable) as deliverable
            FROM daily_snapshots
            WHERE paper_code != 'FN'
              AND snapshot_date = ?
            GROUP BY snapshot_date
        ");
        $stmt->execute([$prevWeekDate]);
        $compareData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($compareData) {
            $prevWeek = getWeekBoundaries($prevWeekDate);
            $comparison = [
                'type' => 'previous',
                'label' => 'Previous Week',
                'period' => [
                    'start' => $prevWeek['start'],
                    'end' => $prevWeek['end'],
                    'week_num' => $prevWeek['week_num'],
                    'year' => $prevWeek['year'],
                    'label' => "Week {$prevWeek['week_num']}, {$prevWeek['year']}"
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

    // Get 12-week trend (rolling 3 months)
    $trendStart = date('Y-m-d', strtotime($saturday . ' -84 days')); // 12 weeks back
    $stmt = $pdo->prepare("
        SELECT
            snapshot_date,
            SUM(total_active) as total_active,
            SUM(on_vacation) as on_vacation,
            SUM(deliverable) as deliverable
        FROM daily_snapshots
        WHERE paper_code != 'FN'
          AND snapshot_date >= ?
          AND snapshot_date <= ?
          AND DAYOFWEEK(snapshot_date) = 7
        GROUP BY snapshot_date
        ORDER BY snapshot_date ASC
    ");
    $stmt->execute([$trendStart, $saturday]);
    $trend = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get by business unit with comparisons
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
          AND snapshot_date = ?
        GROUP BY business_unit
    ");
    $stmt->execute([$saturday]);
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

        // Get comparison for this unit
        $unit_comparisons[$row['business_unit']] = getBusinessUnitComparison(
            $pdo,
            $row['business_unit'],
            $saturday,
            $unitData
        );
    }

    // Get by edition
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
          AND snapshot_date = ?
        ORDER BY total_active DESC
    ");
    $stmt->execute([$saturday]);
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

    return [
        'period' => [
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
        'comparison' => $comparison,
        'trend' => array_map(function($row) {
            return [
                'snapshot_date' => $row['snapshot_date'],
                'total_active' => (int)$row['total_active'],
                'on_vacation' => (int)$row['on_vacation'],
                'deliverable' => (int)$row['deliverable'],
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
function getPaperDetail($pdo, $paperCode) {
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
function getDetailPanelData($pdo, $businessUnit, $snapshotDate) {
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
            $actualSnapshotDate = $snapshotDate; // No data found, will return empty
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
    $expiration_stmt = $pdo->prepare("
        SELECT
            CASE
                WHEN paid_thru < CURDATE() THEN 'Past Due'
                WHEN paid_thru BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'This Week'
                WHEN paid_thru BETWEEN DATE_ADD(CURDATE(), INTERVAL 8 DAY) AND DATE_ADD(CURDATE(), INTERVAL 14 DAY) THEN 'Next Week'
                WHEN paid_thru BETWEEN DATE_ADD(CURDATE(), INTERVAL 15 DAY) AND DATE_ADD(CURDATE(), INTERVAL 21 DAY) THEN 'Week +2'
            END as week_bucket,
            COUNT(*) as count
        FROM subscriber_snapshots
        WHERE snapshot_date = ?
            AND paper_code IN ($placeholders)
            AND paid_thru IS NOT NULL
            AND paid_thru <= DATE_ADD(CURDATE(), INTERVAL 21 DAY)
        GROUP BY week_bucket
        ORDER BY
            CASE week_bucket
                WHEN 'Past Due' THEN 1
                WHEN 'This Week' THEN 2
                WHEN 'Next Week' THEN 3
                WHEN 'Week +2' THEN 4
            END
    ");
    $expiration_stmt->execute(array_merge([$snapshotDate], $paper_codes));
    $expiration_data = $expiration_stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['expiration_chart'] = array_map(function($row) {
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

    $response['rate_distribution'] = array_map(function($row) {
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

    $response['subscription_length'] = array_map(function($row) {
        return [
            'subscription_length' => $row['subscription_length'],
            'count' => (int)$row['count']
        ];
    }, $length_data);

    return $response;
}

function sendResponse($data) {
    echo json_encode([
        'success' => true,
        'data' => $data
    ], JSON_PRETTY_PRINT);
}

function sendError($message) {
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

        default:
            sendError('Invalid action: ' . $action);
            break;
    }

} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage());
}
?>
