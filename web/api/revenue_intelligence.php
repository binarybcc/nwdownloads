<?php
/**
 * Revenue Intelligence API
 * Provides expiration risk, legacy rate analysis, and revenue per subscriber metrics
 *
 * Features:
 * - Expiration Risk Dashboard: Subscribers expiring in 0-4, 5-8, 9-12 weeks
 * - Legacy Rate Gap Analysis: Identify revenue opportunities from low-rate subscribers
 * - Revenue Per Subscriber: ARPU by delivery type and business unit
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Check if requesting subscriber list
if (isset($_GET['action']) && $_GET['action'] === 'subscribers') {
    handleSubscriberListRequest();
    exit();
}

// Check if requesting per-paper metrics
if (isset($_GET['action']) && $_GET['action'] === 'by_paper') {
    handleByPaperRequest();
    exit();
}

// Check if requesting sweet spot analysis
if (isset($_GET['action']) && $_GET['action'] === 'sweet_spot') {
    handleSweetSpotAnalysis();
    exit();
}

// Database configuration
$db_host = getenv('DB_HOST') ?: 'database';
$db_port = getenv('DB_PORT') ?: '3306';
$db_name = getenv('DB_NAME') ?: 'circulation_dashboard';
$db_user = getenv('DB_USER') ?: 'circ_dash';
$db_pass = getenv('DB_PASSWORD') ?: 'Barnaby358@Jones!';
$db_socket = getenv('DB_SOCKET') ?: '';

try {
    // Connect to database
    if ($db_socket && $db_socket !== '') {
        $dsn = "mysql:unix_socket=$db_socket;dbname=$db_name";
    } else {
        $dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name";
    }

    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Get latest snapshot date
    $stmt = $pdo->query("SELECT MAX(snapshot_date) as latest_date FROM subscriber_snapshots");
    $latest = $stmt->fetch();
    $snapshot_date = $latest['latest_date'];

    // Get expiration risk data
    $expiration_risk = getExpirationRisk($pdo, $snapshot_date);

    // Get legacy rate analysis
    $legacy_rate_analysis = getLegacyRateAnalysis($pdo, $snapshot_date);

    // Get revenue per subscriber metrics
    $revenue_metrics = getRevenueMetrics($pdo, $snapshot_date);

    // Return combined response
    echo json_encode([
        'success' => true,
        'snapshot_date' => $snapshot_date,
        'expiration_risk' => $expiration_risk,
        'legacy_rate_analysis' => $legacy_rate_analysis,
        'revenue_metrics' => $revenue_metrics,
        'generated_at' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

/**
 * Calculate expiration risk buckets
 * Returns subscribers and revenue at risk for each time period
 */
function getExpirationRisk($pdo, $snapshot_date) {
    $sql = "
        SELECT
          CASE
            WHEN DATEDIFF(paid_thru, CURDATE()) BETWEEN 0 AND 28 THEN '0-4 weeks'
            WHEN DATEDIFF(paid_thru, CURDATE()) BETWEEN 29 AND 56 THEN '5-8 weeks'
            WHEN DATEDIFF(paid_thru, CURDATE()) BETWEEN 57 AND 84 THEN '9-12 weeks'
            WHEN DATEDIFF(paid_thru, CURDATE()) < 0 THEN 'Expired'
            ELSE '13+ weeks'
          END as risk_bucket,
          COUNT(*) as subscriber_count,
          SUM(ABS(last_payment_amount)) as revenue_at_risk,
          AVG(ABS(last_payment_amount)) as avg_payment,
          business_unit
        FROM subscriber_snapshots
        WHERE snapshot_date = :snapshot_date
          AND paid_thru IS NOT NULL
        GROUP BY risk_bucket, business_unit
        ORDER BY
          business_unit,
          CASE risk_bucket
            WHEN 'Expired' THEN 0
            WHEN '0-4 weeks' THEN 1
            WHEN '5-8 weeks' THEN 2
            WHEN '9-12 weeks' THEN 3
            ELSE 4
          END
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['snapshot_date' => $snapshot_date]);
    $results = $stmt->fetchAll();

    // Organize by risk bucket with business unit breakdown
    $buckets = [
        'Expired' => [],
        '0-4 weeks' => [],
        '5-8 weeks' => [],
        '9-12 weeks' => [],
        '13+ weeks' => []
    ];

    $totals = [
        'Expired' => ['subscribers' => 0, 'revenue' => 0],
        '0-4 weeks' => ['subscribers' => 0, 'revenue' => 0],
        '5-8 weeks' => ['subscribers' => 0, 'revenue' => 0],
        '9-12 weeks' => ['subscribers' => 0, 'revenue' => 0],
        '13+ weeks' => ['subscribers' => 0, 'revenue' => 0]
    ];

    foreach ($results as $row) {
        $bucket = $row['risk_bucket'];
        $buckets[$bucket][] = [
            'business_unit' => $row['business_unit'],
            'subscribers' => (int)$row['subscriber_count'],
            'revenue_at_risk' => (float)$row['revenue_at_risk'],
            'avg_payment' => (float)$row['avg_payment']
        ];

        $totals[$bucket]['subscribers'] += (int)$row['subscriber_count'];
        $totals[$bucket]['revenue'] += (float)$row['revenue_at_risk'];
    }

    return [
        'by_bucket' => $buckets,
        'totals' => $totals
    ];
}

/**
 * Analyze legacy rate opportunities
 * Identifies subscribers on rates < $100/year and calculates revenue gap
 */
function getLegacyRateAnalysis($pdo, $snapshot_date) {
    $sql = "
        SELECT
          business_unit,
          paper_code,
          COUNT(*) as total_subscribers,
          COUNT(CASE WHEN ABS(last_payment_amount) < 100 THEN 1 END) as legacy_rate_count,
          AVG(CASE WHEN ABS(last_payment_amount) < 100 THEN ABS(last_payment_amount) END) as avg_legacy_rate,
          COUNT(CASE WHEN ABS(last_payment_amount) >= 100 THEN 1 END) as market_rate_count,
          AVG(CASE WHEN ABS(last_payment_amount) >= 100 THEN ABS(last_payment_amount) END) as avg_market_rate
        FROM subscriber_snapshots
        WHERE snapshot_date = :snapshot_date
          AND last_payment_amount IS NOT NULL
          AND last_payment_amount != 0
        GROUP BY business_unit, paper_code
        ORDER BY business_unit, paper_code
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['snapshot_date' => $snapshot_date]);
    $results = $stmt->fetchAll();

    $by_business_unit = [];
    $totals = [
        'legacy_rate_subs' => 0,
        'avg_legacy_rate' => 0,
        'market_rate' => 169.99, // Current market rate from strategic plan
        'monthly_revenue_gap' => 0,
        'annual_opportunity' => 0
    ];

    $legacy_sum = 0;
    $legacy_count = 0;

    foreach ($results as $row) {
        $bu = $row['business_unit'];
        if (!isset($by_business_unit[$bu])) {
            $by_business_unit[$bu] = [];
        }

        $legacy_count_bu = (int)$row['legacy_rate_count'];
        $avg_legacy = (float)$row['avg_legacy_rate'];

        // Calculate revenue gap for this paper
        if ($legacy_count_bu > 0 && $avg_legacy > 0) {
            $monthly_gap = $legacy_count_bu * (($totals['market_rate'] - $avg_legacy) / 12);
            $annual_gap = $monthly_gap * 12;
        } else {
            $monthly_gap = 0;
            $annual_gap = 0;
        }

        $by_business_unit[$bu][] = [
            'paper_code' => $row['paper_code'],
            'total_subscribers' => (int)$row['total_subscribers'],
            'legacy_rate_count' => $legacy_count_bu,
            'avg_legacy_rate' => $avg_legacy,
            'market_rate_count' => (int)$row['market_rate_count'],
            'avg_market_rate' => (float)$row['avg_market_rate'],
            'monthly_revenue_gap' => $monthly_gap,
            'annual_opportunity' => $annual_gap
        ];

        $totals['legacy_rate_subs'] += $legacy_count_bu;
        $legacy_sum += $avg_legacy * $legacy_count_bu;
        $legacy_count += $legacy_count_bu;
        $totals['monthly_revenue_gap'] += $monthly_gap;
    }

    // Calculate weighted average legacy rate
    if ($legacy_count > 0) {
        $totals['avg_legacy_rate'] = $legacy_sum / $legacy_count;
    }

    $totals['annual_opportunity'] = $totals['monthly_revenue_gap'] * 12;

    return [
        'by_business_unit' => $by_business_unit,
        'totals' => $totals
    ];
}

/**
 * Calculate revenue per subscriber metrics
 * ARPU (Average Revenue Per User) by delivery type and business unit
 */
function getRevenueMetrics($pdo, $snapshot_date) {
    // By delivery type
    $sql_delivery = "
        SELECT
          delivery_type,
          COUNT(*) as subscriber_count,
          AVG(ABS(last_payment_amount)) as arpu,
          SUM(ABS(last_payment_amount)) as total_annual_revenue
        FROM subscriber_snapshots
        WHERE snapshot_date = :snapshot_date
          AND last_payment_amount IS NOT NULL
          AND last_payment_amount != 0
        GROUP BY delivery_type
        ORDER BY subscriber_count DESC
    ";

    $stmt = $pdo->prepare($sql_delivery);
    $stmt->execute(['snapshot_date' => $snapshot_date]);
    $by_delivery = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // By business unit
    $sql_bu = "
        SELECT
          business_unit,
          COUNT(*) as subscriber_count,
          AVG(ABS(last_payment_amount)) as arpu,
          SUM(ABS(last_payment_amount)) as total_annual_revenue,
          AVG(ABS(last_payment_amount)) / 12 as mrr_per_sub
        FROM subscriber_snapshots
        WHERE snapshot_date = :snapshot_date
          AND last_payment_amount IS NOT NULL
          AND last_payment_amount != 0
        GROUP BY business_unit
        ORDER BY business_unit
    ";

    $stmt = $pdo->prepare($sql_bu);
    $stmt->execute(['snapshot_date' => $snapshot_date]);
    $by_business_unit = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals
    $total_subs = 0;
    $total_revenue = 0;

    foreach ($by_business_unit as $row) {
        $total_subs += (int)$row['subscriber_count'];
        $total_revenue += (float)$row['total_annual_revenue'];
    }

    $overall_arpu = $total_subs > 0 ? $total_revenue / $total_subs : 0;
    $overall_mrr = $total_revenue / 12;

    return [
        'by_delivery_type' => $by_delivery,
        'by_business_unit' => $by_business_unit,
        'totals' => [
            'total_subscribers' => $total_subs,
            'total_annual_revenue' => $total_revenue,
            'overall_arpu' => $overall_arpu,
            'overall_mrr' => $overall_mrr,
            'mrr_per_subscriber' => $overall_arpu / 12
        ]
    ];
}

/**
 * Handle subscriber list request by expiration bucket
 * Returns detailed list of subscribers in a specific expiration risk category
 */
function handleSubscriberListRequest() {
    header('Content-Type: application/json');

    try {
        // Get bucket parameter
        if (!isset($_GET['bucket'])) {
            throw new Exception('Missing bucket parameter');
        }

        $bucket = $_GET['bucket'];

        // Valid buckets
        $valid_buckets = ['Expired', '0-4 weeks', '5-8 weeks', '9-12 weeks'];
        if (!in_array($bucket, $valid_buckets)) {
            throw new Exception('Invalid bucket parameter');
        }

        // Database configuration
        $db_host = getenv('DB_HOST') ?: 'database';
        $db_port = getenv('DB_PORT') ?: '3306';
        $db_name = getenv('DB_NAME') ?: 'circulation_dashboard';
        $db_user = getenv('DB_USER') ?: 'circ_dash';
        $db_pass = getenv('DB_PASSWORD') ?: 'Barnaby358@Jones!';
        $db_socket = getenv('DB_SOCKET') ?: '';

        // Connect to database
        if ($db_socket && $db_socket !== '') {
            $dsn = "mysql:unix_socket=$db_socket;dbname=$db_name";
        } else {
            $dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name";
        }

        $pdo = new PDO($dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        // Get latest snapshot date
        $stmt = $pdo->query("SELECT MAX(snapshot_date) as latest_date FROM subscriber_snapshots");
        $latest = $stmt->fetch();
        $snapshot_date = $latest['latest_date'];

        // Build WHERE clause based on bucket
        $where_clause = '';
        switch ($bucket) {
            case 'Expired':
                $where_clause = 'DATEDIFF(paid_thru, CURDATE()) < 0';
                break;
            case '0-4 weeks':
                $where_clause = 'DATEDIFF(paid_thru, CURDATE()) BETWEEN 0 AND 28';
                break;
            case '5-8 weeks':
                $where_clause = 'DATEDIFF(paid_thru, CURDATE()) BETWEEN 29 AND 56';
                break;
            case '9-12 weeks':
                $where_clause = 'DATEDIFF(paid_thru, CURDATE()) BETWEEN 57 AND 84';
                break;
        }

        // Fetch subscribers
        $sql = "
            SELECT
                sub_num as account_id,
                name as subscriber_name,
                paper_code,
                paper_name,
                business_unit,
                paid_thru as expiration_date,
                DATEDIFF(paid_thru, CURDATE()) as days_until_expiration,
                delivery_type,
                last_payment_amount,
                phone,
                email,
                CONCAT_WS(', ',
                    NULLIF(address, ''),
                    NULLIF(city_state_postal, '')
                ) as mailing_address,
                route,
                rate_name as current_rate,
                subscription_length,
                ABS(last_payment_amount) as rate_amount
            FROM subscriber_snapshots
            WHERE snapshot_date = :snapshot_date
              AND paid_thru IS NOT NULL
              AND $where_clause
            ORDER BY paid_thru ASC, name ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['snapshot_date' => $snapshot_date]);
        $subscribers = $stmt->fetchAll();

        // Return response
        echo json_encode([
            'success' => true,
            'bucket' => $bucket,
            'snapshot_date' => $snapshot_date,
            'count' => count($subscribers),
            'subscribers' => $subscribers
        ], JSON_PRETTY_PRINT);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Handle per-paper metrics request
 * Returns legacy rate opportunity and ARPU metrics grouped by paper
 */
function handleByPaperRequest() {
    header('Content-Type: application/json');

    try {
        // Database configuration
        $db_host = getenv('DB_HOST') ?: 'database';
        $db_port = getenv('DB_PORT') ?: '3306';
        $db_name = getenv('DB_NAME') ?: 'circulation_dashboard';
        $db_user = getenv('DB_USER') ?: 'circ_dash';
        $db_pass = getenv('DB_PASSWORD') ?: 'Barnaby358@Jones!';
        $db_socket = getenv('DB_SOCKET') ?: '';

        // Connect to database
        if ($db_socket && $db_socket !== '') {
            $dsn = "mysql:unix_socket=$db_socket;dbname=$db_name";
        } else {
            $dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name";
        }

        $pdo = new PDO($dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        // Get latest snapshot date
        $stmt = $pdo->query("SELECT MAX(snapshot_date) as latest_date FROM subscriber_snapshots");
        $latest = $stmt->fetch();
        $snapshot_date = $latest['latest_date'];

        // Get per-paper metrics with same-length rate comparisons
        $sql = "
            SELECT
                s.paper_code,
                s.paper_name,
                s.business_unit,
                COUNT(*) as total_subscribers,

                -- Legacy rate opportunity (user-controlled + auto-detection)
                -- Priority: 1) User flagged as legacy, 2) Auto-detected (below market rate)
                -- Excludes: Special rates (is_special = 1)
                COUNT(CASE
                    WHEN (rf.is_special IS NULL OR rf.is_special = 0)
                         AND (rf.is_legacy = 1
                              OR (rf.is_legacy IS NULL AND r.market_rate IS NOT NULL AND ABS(s.last_payment_amount) < r.market_rate))
                    THEN 1
                END) as legacy_rate_count,

                AVG(CASE
                    WHEN (rf.is_special IS NULL OR rf.is_special = 0)
                         AND (rf.is_legacy = 1
                              OR (rf.is_legacy IS NULL AND r.market_rate IS NOT NULL AND ABS(s.last_payment_amount) < r.market_rate))
                    THEN ABS(s.last_payment_amount)
                END) as avg_legacy_rate,

                -- Revenue gap (user-flagged legacy + auto-detected, excluding special rates)
                SUM(CASE
                    WHEN (rf.is_special IS NULL OR rf.is_special = 0)
                         AND (rf.is_legacy = 1
                              OR (rf.is_legacy IS NULL AND r.market_rate IS NOT NULL AND ABS(s.last_payment_amount) < r.market_rate))
                    THEN r.market_rate - ABS(s.last_payment_amount)
                    ELSE 0
                END) as annual_revenue_gap,

                -- Overall ARPU (annualized for comparison)
                AVG(r.annualized_rate) as overall_arpu_annualized,
                AVG(ABS(s.last_payment_amount)) as overall_arpu_actual,
                SUM(r.annualized_rate) as total_annual_revenue_potential,

                -- ARPU by delivery type (annualized)
                AVG(CASE WHEN s.delivery_type = 'MAIL' THEN r.annualized_rate END) as arpu_mail,
                COUNT(CASE WHEN s.delivery_type = 'MAIL' THEN 1 END) as count_mail,

                AVG(CASE WHEN s.delivery_type = 'CARR' THEN r.annualized_rate END) as arpu_carrier,
                COUNT(CASE WHEN s.delivery_type = 'CARR' THEN 1 END) as count_carrier,

                AVG(CASE WHEN s.delivery_type = 'INTE' THEN r.annualized_rate END) as arpu_digital,
                COUNT(CASE WHEN s.delivery_type = 'INTE' THEN 1 END) as count_digital,

                -- Subscription length distribution
                GROUP_CONCAT(DISTINCT s.subscription_length ORDER BY s.subscription_length SEPARATOR ',') as subscription_lengths,

                -- Count by subscription length (for sweet spot analysis)
                COUNT(CASE WHEN s.subscription_length LIKE '%M' THEN 1 END) as monthly_count,
                COUNT(CASE WHEN s.subscription_length LIKE '%Y' THEN 1 END) as yearly_count,
                COUNT(CASE WHEN s.subscription_length LIKE '%W' THEN 1 END) as weekly_count

            FROM subscriber_snapshots s
            LEFT JOIN rate_structure r
                ON s.paper_code = r.paper_code
                AND s.subscription_length = r.subscription_length
            LEFT JOIN rate_flags rf
                ON s.rate_name = rf.zone
            WHERE s.snapshot_date = :snapshot_date
              AND s.last_payment_amount IS NOT NULL
              AND s.last_payment_amount != 0
              AND (rf.is_ignored IS NULL OR rf.is_ignored = 0)  -- Exclude ignored rates
            GROUP BY s.paper_code, s.paper_name, s.business_unit
            ORDER BY s.business_unit, s.paper_code
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['snapshot_date' => $snapshot_date]);
        $papers = $stmt->fetchAll();

        // Format response with proper data types and null handling
        $formatted_papers = [];
        foreach ($papers as $paper) {
            $formatted_papers[] = [
                'paper_code' => $paper['paper_code'],
                'paper_name' => $paper['paper_name'],
                'business_unit' => $paper['business_unit'],
                'total_subscribers' => (int)$paper['total_subscribers'],

                // Legacy rate opportunity (same-length comparisons)
                'legacy_rate_count' => (int)$paper['legacy_rate_count'],
                'avg_legacy_rate' => $paper['avg_legacy_rate'] ? (float)$paper['avg_legacy_rate'] : null,
                'annual_revenue_gap' => $paper['annual_revenue_gap'] ? (float)$paper['annual_revenue_gap'] : 0,

                // Overall ARPU (showing both annualized for comparison and actual)
                'overall_arpu_annualized' => $paper['overall_arpu_annualized'] ? (float)$paper['overall_arpu_annualized'] : 0,
                'overall_arpu_actual' => $paper['overall_arpu_actual'] ? (float)$paper['overall_arpu_actual'] : 0,
                'total_annual_revenue_potential' => (float)$paper['total_annual_revenue_potential'],

                // ARPU by delivery type (annualized)
                'arpu_mail' => $paper['arpu_mail'] ? (float)$paper['arpu_mail'] : null,
                'count_mail' => (int)$paper['count_mail'],

                'arpu_carrier' => $paper['arpu_carrier'] ? (float)$paper['arpu_carrier'] : null,
                'count_carrier' => (int)$paper['count_carrier'],

                'arpu_digital' => $paper['arpu_digital'] ? (float)$paper['arpu_digital'] : null,
                'count_digital' => (int)$paper['count_digital'],

                // Subscription length distribution
                'subscription_lengths' => $paper['subscription_lengths'] ?? '',
                'monthly_count' => (int)$paper['monthly_count'],
                'yearly_count' => (int)$paper['yearly_count'],
                'weekly_count' => (int)$paper['weekly_count']
            ];
        }

        // Return response
        echo json_encode([
            'success' => true,
            'snapshot_date' => $snapshot_date,
            'papers' => $formatted_papers,
            'generated_at' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Sweet Spot Analysis - Optimize subscription length mix
 * Calculates cash flow, profit margin, stability, and admin efficiency metrics
 */
function handleSweetSpotAnalysis() {
    header('Content-Type: application/json');

    try {
        // Database configuration
        $db_host = getenv('DB_HOST') ?: 'database';
        $db_port = getenv('DB_PORT') ?: '3306';
        $db_name = getenv('DB_NAME') ?: 'circulation_dashboard';
        $db_user = getenv('DB_USER') ?: 'circ_dash';
        $db_pass = getenv('DB_PASSWORD') ?: 'Barnaby358@Jones!';
        $db_socket = getenv('DB_SOCKET') ?: '';

        // Connect to database
        if ($db_socket && $db_socket !== '') {
            $dsn = "mysql:unix_socket=$db_socket;dbname=$db_name";
        } else {
            $dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name";
        }

        $pdo = new PDO($dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        // Get latest snapshot date
        $stmt = $pdo->query("SELECT MAX(snapshot_date) as latest_date FROM subscriber_snapshots");
        $latest = $stmt->fetch();
        $snapshot_date = $latest['latest_date'];

        // Get detailed subscription length breakdown per paper
        $sql = "
            SELECT
                s.paper_code,
                s.paper_name,
                s.business_unit,
                s.subscription_length,
                COUNT(*) as subscriber_count,
                AVG(ABS(s.last_payment_amount)) as avg_rate,
                AVG(r.annualized_rate) as avg_annualized_rate,
                SUM(ABS(s.last_payment_amount)) as total_revenue,
                SUM(r.annualized_rate) as total_annualized_revenue,

                -- Calculate renewal frequency (times per year)
                CASE
                    WHEN s.subscription_length LIKE '%W' THEN 52.0 / CAST(SUBSTRING_INDEX(s.subscription_length, 'W', 1) AS DECIMAL(10,2))
                    WHEN s.subscription_length LIKE '%M' THEN 12.0 / CAST(SUBSTRING_INDEX(s.subscription_length, 'M', 1) AS DECIMAL(10,2))
                    WHEN s.subscription_length LIKE '%Y' THEN 1.0 / CAST(SUBSTRING_INDEX(s.subscription_length, 'Y', 1) AS DECIMAL(10,2))
                    WHEN s.subscription_length LIKE '%D' THEN 365.0 / CAST(SUBSTRING_INDEX(s.subscription_length, 'D', 1) AS DECIMAL(10,2))
                    ELSE 1.0
                END as renewals_per_year

            FROM subscriber_snapshots s
            LEFT JOIN rate_structure r
                ON s.paper_code = r.paper_code
                AND s.subscription_length = r.subscription_length
            LEFT JOIN rate_flags rf
                ON s.rate_name = rf.zone
            WHERE s.snapshot_date = :snapshot_date
              AND s.last_payment_amount IS NOT NULL
              AND s.last_payment_amount != 0
              AND (rf.is_ignored IS NULL OR rf.is_ignored = 0)  -- Exclude ignored rates
            GROUP BY s.paper_code, s.paper_name, s.business_unit, s.subscription_length
            ORDER BY s.business_unit, s.paper_code, s.subscription_length
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['snapshot_date' => $snapshot_date]);
        $length_data = $stmt->fetchAll();

        // Organize by paper and calculate sweet spot metrics
        $papers_analysis = [];
        $current_paper = null;
        $paper_data = [];

        foreach ($length_data as $row) {
            $paper_code = $row['paper_code'];

            // Start new paper analysis
            if ($current_paper !== $paper_code) {
                if ($current_paper !== null) {
                    $papers_analysis[] = calculateSweetSpotMetrics($paper_data);
                }
                $current_paper = $paper_code;
                $paper_data = [
                    'paper_code' => $row['paper_code'],
                    'paper_name' => $row['paper_name'],
                    'business_unit' => $row['business_unit'],
                    'lengths' => []
                ];
            }

            // Add length data
            $paper_data['lengths'][] = [
                'subscription_length' => $row['subscription_length'],
                'subscriber_count' => (int)$row['subscriber_count'],
                'avg_rate' => (float)$row['avg_rate'],
                'avg_annualized_rate' => (float)($row['avg_annualized_rate'] ?? 0),
                'total_revenue' => (float)$row['total_revenue'],
                'total_annualized_revenue' => (float)($row['total_annualized_revenue'] ?? 0),
                'renewals_per_year' => (float)$row['renewals_per_year']
            ];
        }

        // Process last paper
        if ($current_paper !== null) {
            $papers_analysis[] = calculateSweetSpotMetrics($paper_data);
        }

        // Calculate overall metrics by aggregating all papers
        $overall_metrics = calculateOverallSweetSpotMetrics($length_data);

        // Return response
        echo json_encode([
            'success' => true,
            'snapshot_date' => $snapshot_date,
            'metrics' => $overall_metrics,
            'papers' => $papers_analysis,
            'generated_at' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Calculate sweet spot optimization metrics for a paper
 */
function calculateSweetSpotMetrics($paper_data) {
    $lengths = $paper_data['lengths'];
    $total_subscribers = array_sum(array_column($lengths, 'subscriber_count'));

    // Initialize scores
    $cash_flow_score = 0;
    $profit_margin_score = 0;
    $stability_score = 0;
    $admin_efficiency_score = 0;

    // Calculate weighted metrics
    $total_renewals_per_year = 0;
    $total_annualized_revenue = 0;
    $max_annualized_rate = 0;
    $min_annualized_rate = PHP_FLOAT_MAX;

    foreach ($lengths as $length) {
        $count = $length['subscriber_count'];
        $pct = $total_subscribers > 0 ? $count / $total_subscribers : 0;
        $renewals = $length['renewals_per_year'];
        $annualized = $length['avg_annualized_rate'];

        // Cash flow: Higher renewal frequency = better cash flow
        // Weight: renewals_per_year * subscriber_percentage
        $cash_flow_score += $renewals * $pct;

        // Profit margin: Higher annualized rate = better margin
        $total_annualized_revenue += $length['total_annualized_revenue'];
        $max_annualized_rate = max($max_annualized_rate, $annualized);
        $min_annualized_rate = min($min_annualized_rate, $annualized);

        // Stability: Longer commitments = more stable
        // Inverse of renewal frequency (yearly = stable, weekly = volatile)
        $commitment_months = 12.0 / $renewals;
        $stability_score += $commitment_months * $pct;

        // Admin efficiency: Fewer renewals = less overhead
        $total_renewals_per_year += $count * $renewals;
    }

    // Normalize scores to 0-100 scale
    $cash_flow_normalized = min(100, ($cash_flow_score / 52) * 100); // Max is 52 (all weekly)
    $profit_margin_normalized = $total_subscribers > 0
        ? ($total_annualized_revenue / $total_subscribers / 300) * 100  // Assume $300 is excellent ARPU
        : 0;
    $stability_normalized = min(100, ($stability_score / 12) * 100); // Max is 12 (all yearly)
    $admin_efficiency_normalized = max(0, 100 - ($total_renewals_per_year / $total_subscribers / 12) * 100); // Fewer renewals = higher score

    // Calculate overall "Sweet Spot Score" (weighted average)
    // Weights: Cash Flow 30%, Profit Margin 40%, Stability 20%, Admin Efficiency 10%
    $sweet_spot_score = (
        $cash_flow_normalized * 0.30 +
        $profit_margin_normalized * 0.40 +
        $stability_normalized * 0.20 +
        $admin_efficiency_normalized * 0.10
    );

    // Generate recommendations
    $recommendations = generateRecommendations(
        $lengths,
        $total_subscribers,
        $cash_flow_normalized,
        $profit_margin_normalized,
        $stability_normalized,
        $admin_efficiency_normalized
    );

    return [
        'paper_code' => $paper_data['paper_code'],
        'paper_name' => $paper_data['paper_name'],
        'business_unit' => $paper_data['business_unit'],
        'total_subscribers' => $total_subscribers,
        'subscription_lengths' => $lengths,

        // Core metrics
        'metrics' => [
            'cash_flow_score' => round($cash_flow_normalized, 1),
            'profit_margin_score' => round($profit_margin_normalized, 1),
            'stability_score' => round($stability_normalized, 1),
            'admin_efficiency_score' => round($admin_efficiency_normalized, 1),
            'sweet_spot_score' => round($sweet_spot_score, 1)
        ],

        // Detailed stats
        'stats' => [
            'avg_renewals_per_subscriber_per_year' => round($total_renewals_per_year / max(1, $total_subscribers), 2),
            'total_annualized_revenue' => round($total_annualized_revenue, 2),
            'avg_annualized_arpu' => round($total_annualized_revenue / max(1, $total_subscribers), 2),
            'annualized_rate_range' => [
                'min' => round($min_annualized_rate, 2),
                'max' => round($max_annualized_rate, 2),
                'spread_pct' => $min_annualized_rate > 0
                    ? round((($max_annualized_rate - $min_annualized_rate) / $min_annualized_rate) * 100, 1)
                    : 0
            ]
        ],

        'recommendations' => $recommendations
    ];
}

/**
 * Generate actionable recommendations based on metrics
 */
function generateRecommendations($lengths, $total_subscribers, $cash_flow, $profit, $stability, $admin) {
    $recommendations = [];

    // Find dominant subscription length
    $dominant = null;
    $dominant_pct = 0;
    foreach ($lengths as $length) {
        $pct = ($length['subscriber_count'] / $total_subscribers) * 100;
        if ($pct > $dominant_pct) {
            $dominant_pct = $pct;
            $dominant = $length;
        }
    }

    // Cash flow optimization
    if ($cash_flow < 40) {
        $recommendations[] = [
            'type' => 'cash_flow',
            'priority' => 'high',
            'message' => sprintf(
                'Cash flow can be improved by promoting shorter subscription lengths. Consider marketing %s subscriptions with auto-renewal.',
                count($lengths) > 1 ? 'monthly' : 'shorter'
            )
        ];
    }

    // Profit margin optimization
    if ($profit < 50) {
        $recommendations[] = [
            'type' => 'profit_margin',
            'priority' => 'high',
            'message' => 'Profit margin is below target. Consider increasing rates on shorter subscription lengths to improve annualized revenue.'
        ];
    }

    // Stability vs cash flow balance
    if ($stability > 70 && $cash_flow < 30) {
        $recommendations[] = [
            'type' => 'balance',
            'priority' => 'medium',
            'message' => 'Strong stability but weak cash flow. Consider adding monthly auto-renew option to balance predictability with cash flow.'
        ];
    } elseif ($cash_flow > 70 && $stability < 30) {
        $recommendations[] = [
            'type' => 'balance',
            'priority' => 'medium',
            'message' => 'Strong cash flow but low stability. Offer incentives for longer commitments (e.g., 10% off annual vs monthly).'
        ];
    }

    // Admin efficiency
    if ($admin < 50) {
        $recommendations[] = [
            'type' => 'admin_efficiency',
            'priority' => 'low',
            'message' => sprintf(
                'High renewal overhead detected (%.1f renewals/subscriber/year). Promote auto-renewal to reduce administrative burden.',
                $lengths[0]['renewals_per_year'] ?? 0
            )
        ];
    }

    // Diversification
    if (count($lengths) == 1) {
        $recommendations[] = [
            'type' => 'diversification',
            'priority' => 'medium',
            'message' => 'Limited subscription options. Consider adding multiple length tiers to appeal to different customer segments.'
        ];
    } elseif ($dominant_pct > 80) {
        $recommendations[] = [
            'type' => 'diversification',
            'priority' => 'low',
            'message' => sprintf(
                '%.0f%% of subscribers on %s subscriptions. Diversify by promoting alternative lengths.',
                $dominant_pct,
                $dominant['subscription_length']
            )
        ];
    }

    return $recommendations;
}

/**
 * Calculate overall sweet spot metrics across all papers
 */
function calculateOverallSweetSpotMetrics($length_data) {
    if (empty($length_data)) {
        return [
            'sweet_spot_score' => 0,
            'cash_flow_score' => 0,
            'profit_margin_score' => 0,
            'stability_score' => 0,
            'admin_efficiency_score' => 0,
            'avg_renewals_per_subscriber' => 0,
            'annualized_revenue' => 0,
            'min_rate' => 0,
            'max_rate' => 0,
            'recommendations' => []
        ];
    }

    // Aggregate all subscription lengths across all papers
    $total_subscribers = 0;
    $total_renewals = 0;
    $total_annualized_revenue = 0;
    $cash_flow_score = 0;
    $stability_score = 0;
    $max_annualized_rate = 0;
    $min_annualized_rate = PHP_FLOAT_MAX;

    foreach ($length_data as $row) {
        $count = (int)$row['subscriber_count'];
        $renewals = (float)$row['renewals_per_year'];
        $annualized = (float)($row['avg_annualized_rate'] ?? 0);

        $total_subscribers += $count;
        $total_renewals += ($renewals * $count);
        $total_annualized_revenue += ($annualized * $count);

        // Track min/max rates
        if ($annualized > 0) {
            $max_annualized_rate = max($max_annualized_rate, $annualized);
            $min_annualized_rate = min($min_annualized_rate, $annualized);
        }
    }

    // Calculate weighted average metrics
    if ($total_subscribers > 0) {
        foreach ($length_data as $row) {
            $count = (int)$row['subscriber_count'];
            $pct = $count / $total_subscribers;
            $renewals = (float)$row['renewals_per_year'];
            $annualized = (float)($row['avg_annualized_rate'] ?? 0);

            // Cash Flow Score: Higher renewal frequency = better cash flow
            $cash_flow_score += $renewals * $pct;

            // Stability Score: Longer commitments = more stable
            $commitment_months = 12.0 / max($renewals, 0.001); // Avoid division by zero
            $stability_score += $commitment_months * $pct;
        }
    }

    // Normalize scores to 0-100 scale
    // Cash flow: 52 renewals/year (weekly) = 100, 1 renewal/year (annual) = ~2
    $cash_flow_normalized = min(100, ($cash_flow_score / 52) * 100);

    // Stability: 12 months commitment = 100, 0.02 months (weekly) = ~0.2
    $stability_normalized = min(100, ($stability_score / 12) * 100);

    // Profit Margin: Higher annualized rates = better margins
    $avg_annualized_revenue_per_sub = $total_subscribers > 0 ? $total_annualized_revenue / $total_subscribers : 0;
    $profit_margin_normalized = $max_annualized_rate > 0
        ? min(100, ($avg_annualized_revenue_per_sub / $max_annualized_rate) * 100)
        : 0;

    // Admin Efficiency: Fewer renewals = less overhead
    $avg_renewals_per_subscriber = $total_subscribers > 0 ? $total_renewals / $total_subscribers : 0;
    $admin_efficiency_normalized = max(0, min(100, ((52 - $avg_renewals_per_subscriber) / 51) * 100));

    // Overall Sweet Spot Score (weighted average)
    // Weights: Cash Flow 30%, Profit Margin 40%, Stability 20%, Admin Efficiency 10%
    $sweet_spot_score = (
        $cash_flow_normalized * 0.30 +
        $profit_margin_normalized * 0.40 +
        $stability_normalized * 0.20 +
        $admin_efficiency_normalized * 0.10
    );

    // Generate recommendations based on overall metrics
    $recommendations = generateOverallRecommendations(
        $cash_flow_normalized,
        $profit_margin_normalized,
        $stability_normalized,
        $admin_efficiency_normalized,
        $length_data,
        $total_subscribers
    );

    return [
        'sweet_spot_score' => round($sweet_spot_score, 2),
        'cash_flow_score' => round($cash_flow_normalized, 2),
        'profit_margin_score' => round($profit_margin_normalized, 2),
        'stability_score' => round($stability_normalized, 2),
        'admin_efficiency_score' => round($admin_efficiency_normalized, 2),
        'avg_renewals_per_subscriber' => round($avg_renewals_per_subscriber, 2),
        'annualized_revenue' => round($avg_annualized_revenue_per_sub, 2),
        'min_rate' => round($min_annualized_rate < PHP_FLOAT_MAX ? $min_annualized_rate : 0, 2),
        'max_rate' => round($max_annualized_rate, 2),
        'recommendations' => $recommendations
    ];
}

/**
 * Generate overall recommendations based on aggregate metrics
 */
function generateOverallRecommendations($cash_flow, $profit_margin, $stability, $admin_efficiency, $length_data, $total_subscribers) {
    $recommendations = [];

    // Cash flow recommendations
    if ($cash_flow < 40) {
        $recommendations[] = [
            'type' => 'cash_flow',
            'priority' => 'high',
            'message' => 'Cash flow can be improved by promoting shorter subscription lengths (monthly/quarterly) to increase renewal frequency.'
        ];
    }

    // Profit margin recommendations
    if ($profit_margin < 60) {
        $recommendations[] = [
            'type' => 'profit',
            'priority' => 'high',
            'message' => 'Profit margins can be improved by migrating subscribers to higher-value subscription tiers or longer commitments at premium rates.'
        ];
    }

    // Stability recommendations
    if ($stability < 50) {
        $recommendations[] = [
            'type' => 'stability',
            'priority' => 'medium',
            'message' => 'Revenue stability can be improved by promoting annual subscriptions to reduce churn risk and renewal overhead.'
        ];
    }

    // Admin efficiency recommendations
    if ($admin_efficiency < 50) {
        $recommendations[] = [
            'type' => 'efficiency',
            'priority' => 'medium',
            'message' => 'Administrative efficiency can be improved by consolidating subscribers into quarterly or annual billing cycles.'
        ];
    }

    // Check for balanced distribution
    $length_counts = [];
    foreach ($length_data as $row) {
        $type = 'other';
        if (strpos($row['subscription_length'], 'M') !== false) {
            $type = 'monthly';
        } elseif (strpos($row['subscription_length'], 'Y') !== false || $row['subscription_length'] === '52W') {
            $type = 'yearly';
        } elseif (strpos($row['subscription_length'], 'W') !== false) {
            $type = 'weekly';
        }
        $length_counts[$type] = ($length_counts[$type] ?? 0) + (int)$row['subscriber_count'];
    }

    // Check if one type dominates
    foreach ($length_counts as $type => $count) {
        $pct = ($count / $total_subscribers) * 100;
        if ($pct > 75) {
            $recommendations[] = [
                'type' => 'diversity',
                'priority' => 'low',
                'message' => sprintf(
                    '%.0f%% of subscribers are on %s subscriptions. Consider diversifying subscription mix for balanced cash flow and stability.',
                    $pct,
                    $type
                )
            ];
            break;
        }
    }

    return $recommendations;
}
