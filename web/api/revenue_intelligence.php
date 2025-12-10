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
