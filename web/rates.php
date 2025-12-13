<?php

/**
 * Rate Management System
 * Allows users to classify rates as Market, Legacy, or Ignored
 * Integrates with rates.csv and AllSubscriberReport for active subscriber tracking
 */

// Start session FIRST before any output
session_start();

// Handle AJAX save requests BEFORE any includes to prevent output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_flags') {
    // Set JSON header immediately
    header('Content-Type: application/json');

    // Suppress HTML error output for AJAX
    ini_set('display_errors', '0');
    error_reporting(E_ALL);

    require_once 'config.php';
    require_once 'auth_check.php';
    require_once __DIR__ . '/includes/database.php';
// Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    try {
        $pdo = getDatabase();

// Validate required POST parameters
        $required_params = ['paper_code', 'zone', 'rate_name', 'subscription_length', 'rate_amount'];
        foreach ($required_params as $param) {
            if (!isset($_POST[$param]) || trim($_POST[$param]) === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => "Missing required parameter: $param"]);
                exit;
            }
        }

        // Sanitize and validate inputs
        $paper_code = trim($_POST['paper_code']);
        $zone = trim($_POST['zone']);
        $rate_name = trim($_POST['rate_name']);
        $subscription_length = trim($_POST['subscription_length']);
        $rate_amount = floatval($_POST['rate_amount']);
// Validate paper_code format (2-3 uppercase letters)
        if (!preg_match('/^[A-Z]{2,3}$/', $paper_code)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid paper code format']);
            exit;
        }

        // Validate rate amount is positive
        if ($rate_amount <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Rate amount must be positive']);
            exit;
        }

        $is_legacy = isset($_POST['is_legacy']) && $_POST['is_legacy'] === 'true';
        $is_ignored = isset($_POST['is_ignored']) && $_POST['is_ignored'] === 'true';
        $is_special = isset($_POST['is_special']) && $_POST['is_special'] === 'true';
// Upsert rate flag
        $sql = "INSERT INTO rate_flags
                (paper_code, zone, rate_name, subscription_length, rate_amount, is_legacy, is_ignored, is_special)
                VALUES (:paper_code, :zone, :rate_name, :subscription_length, :rate_amount, :is_legacy, :is_ignored, :is_special)
                ON DUPLICATE KEY UPDATE
                    is_legacy = VALUES(is_legacy),
                    is_ignored = VALUES(is_ignored),
                    is_special = VALUES(is_special),
                    updated_at = CURRENT_TIMESTAMP";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'paper_code' => $paper_code,
            'zone' => $zone,
            'rate_name' => $rate_name,
            'subscription_length' => $subscription_length,
            'rate_amount' => $rate_amount,
            'is_legacy' => $is_legacy ? 1 : 0,
            'is_ignored' => $is_ignored ? 1 : 0,
            'is_special' => $is_special ? 1 : 0
        ]);
// Debug: Return what was saved
        echo json_encode([
            'success' => true,
            'debug' => [
                'zone' => $zone,
                'rate_amount' => $rate_amount,
                'is_legacy' => $is_legacy ? 1 : 0,
                'is_ignored' => $is_ignored ? 1 : 0,
                'is_special' => $is_special ? 1 : 0
            ]
        ]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Load includes for normal page rendering (not AJAX)
require_once 'config.php';
require_once 'auth_check.php';
require_once __DIR__ . '/includes/database.php';

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize variables before try-catch
$rate_flags = [];
$subscriber_counts = [];
$snapshot_date = null;
$error_message = null;

// Connect to database for reading
try {
    $pdo = getDatabase();

// Get latest snapshot date
    $stmt = $pdo->query("SELECT MAX(snapshot_date) as latest_date FROM subscriber_snapshots");
    $latest = $stmt->fetch();
    $snapshot_date = $latest['latest_date'];
// Get subscriber counts by zone from latest snapshot
    // Note: subscriber_snapshots.rate_name actually contains the zone code
    $stmt = $pdo->prepare("
        SELECT
            rate_name as zone,
            COUNT(*) as subscriber_count,
            AVG(ABS(last_payment_amount)) as avg_rate
        FROM subscriber_snapshots
        WHERE snapshot_date = :snapshot_date
          AND rate_name IS NOT NULL
          AND rate_name != ''
        GROUP BY rate_name
    ");
    $stmt->execute(['snapshot_date' => $snapshot_date]);
    $subscriber_counts = [];
    while ($row = $stmt->fetch()) {
        $subscriber_counts[$row['zone']] = $row;
    }

    // Get existing rate flags
    $stmt = $pdo->query("SELECT * FROM rate_flags");
    $rate_flags = [];
    while ($row = $stmt->fetch()) {
    // Format rate_amount to 2 decimals for consistent key matching
        $formatted_rate = number_format((float)$row['rate_amount'], 2, '.', '');
        $key = $row['paper_code'] . '_' . $row['zone'] . '_' . $row['subscription_length'] . '_' . $formatted_rate;
        $rate_flags[$key] = $row;
    }
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// Read and parse rates.csv
$csv_path = __DIR__ . '/rates.csv';
$rates = [];
$seen_rates = []; // Track unique rate combinations to avoid duplicates
if (file_exists($csv_path)) {
    $handle = fopen($csv_path, 'r');
    $headers = fgetcsv($handle);
    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) >= 9) {
            $paper = trim($row[1]);
            $length = trim($row[3]);
            $len_type = trim($row[4]);
            $zone = trim($row[5]);
            $rate = floatval(trim($row[8]));
        // Skip $0 rates
            if ($rate > 0) {
                $sub_length = $length . ' ' . $len_type;
                $subscriber_count = isset($subscriber_counts[$zone]) ? $subscriber_counts[$zone]['subscriber_count'] : 0;

                // Skip rates with zero subscribers - no need to manage unused rates
                if ($subscriber_count > 0) {
                    // Create unique key to prevent duplicates
                    $formatted_rate = number_format($rate, 2, '.', '');
                    $unique_key = $paper . '_' . $sub_length . '_' . $zone . '_' . $formatted_rate;

                    // Only add if not already seen
                    if (!isset($seen_rates[$unique_key])) {
                        $seen_rates[$unique_key] = true;
                        $rates[] = [
                            'description' => trim($row[0]),
                            'paper_code' => $paper,
                            'subscription_length' => $sub_length,
                            'zone' => $zone,
                            'rate' => $rate,
                            'subscriber_count' => $subscriber_count,
                            'is_legacy' => false,
                            'is_ignored' => false,
                            'is_special' => false
                        ];
                    }
                }
            }
        }
    }
    fclose($handle);
}

// Find market rates (maximum for each paper + subscription length)
$market_rates = [];
foreach ($rates as $rate) {
    $key = $rate['paper_code'] . '_' . $rate['subscription_length'];
    if (!isset($market_rates[$key]) || $rate['rate'] > $market_rates[$key]) {
        $market_rates[$key] = $rate['rate'];
    }
}

// Group rates by paper
$by_paper = [];
foreach ($rates as &$rate) {
    $paper = $rate['paper_code'];
    if (!isset($by_paper[$paper])) {
        $by_paper[$paper] = [];
    }

    // Check for existing manual flags first - format rate to 2 decimals for consistent matching
    $formatted_rate = number_format((float)$rate['rate'], 2, '.', '');
    $flag_key = $rate['paper_code'] . '_' . $rate['zone'] . '_' . $rate['subscription_length'] . '_' . $formatted_rate;
    $has_flags = isset($rate_flags[$flag_key]);

    // Get manual flag values
    $is_legacy_flagged = $has_flags ? (bool)$rate_flags[$flag_key]['is_legacy'] : false;
    $is_ignored_flagged = $has_flags ? (bool)$rate_flags[$flag_key]['is_ignored'] : false;
    $is_special_flagged = $has_flags ? (bool)$rate_flags[$flag_key]['is_special'] : false;

    // Auto-detect market rate ONLY if not manually flagged
    $market_key = $rate['paper_code'] . '_' . $rate['subscription_length'];
    $is_highest_price = ($rate['rate'] == $market_rates[$market_key]);

    // A rate is "market" only if it's highest price AND not manually flagged as anything else
    $is_market = $is_highest_price && !$is_legacy_flagged && !$is_special_flagged && !$is_ignored_flagged;

    $rate['is_market'] = $is_market;
    $rate['is_legacy'] = $is_legacy_flagged;
    $rate['is_ignored'] = $is_ignored_flagged;
    $rate['is_special'] = $is_special_flagged;
    $rate['market_rate'] = $market_rates[$market_key];
    $by_paper[$paper][] = $rate;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Management - Circulation Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .rate-row:hover { background-color: #f9fafb; }
        .checkbox-cell { min-width: 80px; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Rate Management</h1>
                    <p class="text-gray-600 mt-1">Classify rates as Market, Legacy, or Ignored</p>
                </div>
                <a href="index.php" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    ← Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-8">
            <?php
            $total_rates = count($rates);
            $active_rates = count(array_filter($rates, function ($r) {
                return $r['subscriber_count'] > 0;
            }));
            $legacy_rates = count(array_filter($rates, function ($r) {
                return $r['is_legacy'];
            }));
            $ignored_rates = count(array_filter($rates, function ($r) {
                return $r['is_ignored'];
            }));
            $special_rates = count(array_filter($rates, function ($r) {
                return $r['is_special'];
            }));
            $market_rate_count = count($market_rates);
            ?>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm text-gray-600">Total Rates</div>
                <div class="text-2xl font-bold text-gray-900"><?= $total_rates ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm text-gray-600">Active (in use)</div>
                <div class="text-2xl font-bold text-green-600"><?= $active_rates ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm text-gray-600">Market Rates</div>
                <div class="text-2xl font-bold text-blue-600"><?= $market_rate_count ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm text-gray-600">Legacy</div>
                <div class="text-2xl font-bold text-orange-600"><?= $legacy_rates ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm text-gray-600">Special</div>
                <div class="text-2xl font-bold text-purple-600"><?= $special_rates ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm text-gray-600">Ignored</div>
                <div class="text-2xl font-bold text-gray-600"><?= $ignored_rates ?></div>
            </div>
        </div>

        <!-- Alerts for Ignored Rates with Subscribers -->
        <?php
        $ignored_with_subs = array_filter($rates, function ($r) {
            return $r['is_ignored'] && $r['subscriber_count'] > 0;
        });

        if (count($ignored_with_subs) > 0) :
            ?>
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-yellow-700 font-semibold">
                        Warning: <?= count($ignored_with_subs) ?> ignored rate(s) have active subscribers
                    </p>
                    <div class="mt-2 text-sm text-yellow-700">
                        <ul class="list-disc list-inside space-y-1">
                            <?php foreach (array_slice($ignored_with_subs, 0, 5) as $rate) :
                                ?>
                            <li><?= htmlspecialchars($rate['description']) ?> (<?= $rate['zone'] ?>) - <?= $rate['subscriber_count'] ?> subscribers</li>
                                <?php
                            endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
            <?php
        endif; ?>

        <!-- Debug Info (remove after testing) -->
        <?php if (isset($_GET['debug'])) :
            ?>
        <div class="bg-gray-100 border border-gray-300 rounded-lg p-4 mb-6">
            <h3 class="font-semibold text-gray-900 mb-2">Debug Info</h3>
            <div class="text-xs font-mono">
                <?php if ($error_message) :
                    ?>
                    <div class="text-red-600"><strong>Error:</strong> <?= htmlspecialchars($error_message) ?></div>
                    <?php
                else :
                    ?>
                    <strong>Total flags in database:</strong> <?= count($rate_flags) ?><br>
                    <strong>Snapshot date:</strong> <?= htmlspecialchars($snapshot_date) ?><br>
                    <strong>Flag keys (all <?= count($rate_flags) ?>):</strong>
                    <ul class="list-disc list-inside mt-2 max-h-64 overflow-y-auto">
                    <?php if (count($rate_flags) > 0) :
                        ?>
                        <?php foreach (array_keys($rate_flags) as $key) :
                            ?>
                            <li><?= htmlspecialchars($key) ?> → Legacy: <?= $rate_flags[$key]['is_legacy'] ?>, Ignored: <?= $rate_flags[$key]['is_ignored'] ?></li>
                            <?php
                        endforeach; ?>
                        <?php
                    else :
                        ?>
                        <li>No flags saved yet</li>
                        <?php
                    endif; ?>
                    </ul>
                    <?php
                endif; ?>
            </div>
        </div>
            <?php
        endif; ?>

        <!-- Legend -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <h3 class="font-semibold text-blue-900 mb-2">How to Use</h3>
            <ul class="text-sm text-blue-800 space-y-1">
                <li><strong>Market Rate</strong> = Highest rate for each subscription length (cannot be changed)</li>
                <li><strong>Legacy</strong> = Check to mark rate as legacy (auto-checked if below market rate)</li>
                <li><strong>Special</strong> = Legitimate rate excluded from opportunity calculations (e.g. out-of-county mail)</li>
                <li><strong>Ignore</strong> = Check to exclude from all calculations</li>
                <li><strong>Subscriber Count</strong> = Number of active subscribers on this rate from latest AllSubscriberReport</li>
                <li class="mt-2 pt-2 border-t border-blue-300"><em>Changes save automatically when you click checkboxes</em></li>
            </ul>
        </div>

        <!-- Rates by Paper -->
        <?php foreach ($by_paper as $paper => $paper_rates) :
// Sort by subscription length then rate descending
            usort($paper_rates, function ($a, $b) {

                if ($a['subscription_length'] === $b['subscription_length']) {
                    return $b['rate'] <=> $a['rate'];
                }
                return strcmp($a['subscription_length'], $b['subscription_length']);
            });
            ?>
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($paper) ?></h2>
                <p class="text-sm text-gray-600"><?= count($paper_rates) ?> rates</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rate Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Length</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Zone</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Rate</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Subscribers</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase checkbox-cell">Legacy</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase checkbox-cell">Special</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase checkbox-cell">Ignore</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php
                        $current_length = null;
                        $row_index = 0;
// Use counter for truly unique IDs
                        foreach ($paper_rates as $rate) :
// Visual separator between subscription lengths
                            if ($current_length !== null && $current_length !== $rate['subscription_length']) {
                                echo '<tr class="bg-gray-50"><td colspan="7" class="h-2"></td></tr>';
                            }
                            $current_length = $rate['subscription_length'];
                            $row_class = '';
                            if ($rate['is_ignored']) {
                                $row_class = 'bg-gray-100';
                            } elseif ($rate['is_special']) {
                                $row_class = 'bg-purple-50';
                            } elseif ($rate['is_market']) {
                                $row_class = 'bg-green-50';
                            } elseif ($rate['is_legacy']) {
                                $row_class = 'bg-orange-50';
                            }

                            // Use paper code + row index for truly unique IDs
                            $unique_id = $paper . '_' . $row_index++;
                            ?>
                        <tr class="rate-row <?= $row_class ?>"
                            data-paper="<?= htmlspecialchars($rate['paper_code']) ?>"
                            data-zone="<?= htmlspecialchars($rate['zone']) ?>"
                            data-rate-name="<?= htmlspecialchars($rate['description']) ?>"
                            data-length="<?= htmlspecialchars($rate['subscription_length']) ?>"
                            data-amount="<?= number_format($rate['rate'], 2, '.', '') ?>">
                            <td class="px-6 py-3 text-sm text-gray-900"><?= htmlspecialchars($rate['description']) ?></td>
                            <td class="px-6 py-3 text-sm text-gray-700"><?= htmlspecialchars($rate['subscription_length']) ?></td>
                            <td class="px-6 py-3 text-sm text-gray-700 font-mono"><?= htmlspecialchars($rate['zone']) ?></td>
                            <td class="px-6 py-3 text-sm text-right font-semibold text-gray-900">
                                $<?= number_format($rate['rate'], 2) ?>
                                <?php if ($rate['is_market']) :
                                    ?>
                                <span class="ml-2 text-xs text-green-600">(Market)</span>
                                    <?php
                                endif; ?>
                            </td>
                            <td class="px-6 py-3 text-sm text-center">
                                <?php if ($rate['subscriber_count'] > 0) :
                                    ?>
                                <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded font-semibold"><?= $rate['subscriber_count'] ?></span>
                                    <?php
                                else :
                                    ?>
                                <span class="text-gray-400">0</span>
                                    <?php
                                endif; ?>
                            </td>
                            <td class="px-6 py-3 text-center checkbox-cell">
                                <input type="checkbox"
                                       id="legacy_<?= $unique_id ?>"
                                       class="legacy-checkbox w-4 h-4 text-orange-600 rounded focus:ring-orange-500"
                                       <?= $rate['is_legacy'] ? 'checked' : '' ?>
                                       onchange="saveRateFlag(this, 'legacy')">
                            </td>
                            <td class="px-6 py-3 text-center checkbox-cell">
                                <input type="checkbox"
                                       id="special_<?= $unique_id ?>"
                                       class="special-checkbox w-4 h-4 text-purple-600 rounded focus:ring-purple-500"
                                       <?= $rate['is_special'] ? 'checked' : '' ?>
                                       onchange="saveRateFlag(this, 'special')">
                            </td>
                            <td class="px-6 py-3 text-center checkbox-cell">
                                <input type="checkbox"
                                       id="ignore_<?= $unique_id ?>"
                                       class="ignore-checkbox w-4 h-4 text-gray-600 rounded focus:ring-gray-500"
                                       <?= $rate['is_ignored'] ? 'checked' : '' ?>
                                       onchange="saveRateFlag(this, 'ignore')">
                            </td>
                        </tr>
                            <?php
                        endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
            <?php
        endforeach; ?>
    </div>

    <script>
    function saveRateFlag(checkbox, type) {
        console.log('saveRateFlag called:', type, checkbox.checked);

        const row = checkbox.closest('tr');
        const paper_code = row.dataset.paper;
        const zone = row.dataset.zone;
        const rate_name = row.dataset.rateName;
        const subscription_length = row.dataset.length;
        const rate_amount = row.dataset.amount;

        console.log('Saving rate flag:', {
            paper_code, zone, rate_name, subscription_length, rate_amount
        });

        const isLegacy = type === 'legacy' ? checkbox.checked : row.querySelector('.legacy-checkbox').checked;
        const isSpecial = type === 'special' ? checkbox.checked : row.querySelector('.special-checkbox').checked;
        const isIgnored = type === 'ignore' ? checkbox.checked : row.querySelector('.ignore-checkbox').checked;

        console.log('Flag values:', { isLegacy, isSpecial, isIgnored });

        // Visual feedback
        const originalBg = row.className;
        row.classList.add('bg-blue-100');

        fetch('rates.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'save_flags',
                csrf_token: '<?= $_SESSION['csrf_token'] ?>',
                paper_code: paper_code,
                zone: zone,
                rate_name: rate_name,
                subscription_length: subscription_length,
                rate_amount: rate_amount,
                is_legacy: isLegacy,
                is_special: isSpecial,
                is_ignored: isIgnored
            })
        })
        .then(response => response.json())
        .then(data => {
            console.log('✅ Save response:', data);
            if (data.success) {
                // Update row styling immediately - NO PAGE RELOAD
                row.className = 'rate-row';
                if (isIgnored) {
                    row.classList.add('bg-gray-100');
                } else if (isSpecial) {
                    row.classList.add('bg-purple-50');
                } else if (isLegacy) {
                    row.classList.add('bg-orange-50');
                } else {
                    // Check if this is a market rate
                    const marketLabel = row.querySelector('span.text-green-600');
                    if (marketLabel) {
                        row.classList.add('bg-green-50');
                    }
                }

                // Show success feedback
                const successMsg = document.createElement('span');
                successMsg.className = 'text-green-600 text-xs ml-2';
                successMsg.textContent = '✓ Saved';
                checkbox.parentElement.appendChild(successMsg);
                setTimeout(() => successMsg.remove(), 2000);

                console.log('✅ Checkbox state saved successfully');
            } else {
                console.error('❌ Save failed:', data.error);
                alert('Error saving: ' + data.error);
                checkbox.checked = !checkbox.checked;
                row.className = originalBg;
            }
        })
        .catch(error => {
            console.error('Save error:', error);
            alert('Error saving: ' + error.message);
            checkbox.checked = !checkbox.checked;
            row.className = originalBg;
        });
    }
    </script>
</body>
</html>
