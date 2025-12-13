<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Structure Diagnostic - All Rates from CSV</title>
    <link rel="stylesheet" href="assets/output.css">
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Rate Structure Diagnostic</h1>
        <p class="text-gray-600 mb-6">Showing all rates from rates.csv with market rate identification</p>

        <?php
        // Read and parse rates.csv
        $csv_path = __DIR__ . '/rates.csv';

        if (!file_exists($csv_path)) {
            echo '<div class="bg-red-50 border border-red-200 rounded-lg p-4">';
            echo '<p class="text-red-800">Error: rates.csv not found at: ' . htmlspecialchars($csv_path) . '</p>';
            echo '</div>';
            exit;
        }

        $rates = [];
        $handle = fopen($csv_path, 'r');

        // Read header row
        $headers = fgetcsv($handle);

        // Read all rates
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) >= 9) {
                $paper = trim($row[1]);
                $length = trim($row[3]);
                $len_type = trim($row[4]);
                $rate = floatval(trim($row[8]));

                // Skip $0 rates
                if ($rate > 0) {
                    $sub_length = $length . ' ' . $len_type;

                    $rates[] = [
                        'description' => trim($row[0]),
                        'paper_code' => $paper,
                        'subscription_length' => $sub_length,
                        'rate' => $rate,
                        'rate_name' => trim($row[0]),
                        'zone' => trim($row[5])
                    ];
                }
            }
        }
        fclose($handle);

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
        foreach ($rates as $rate) {
            $paper = $rate['paper_code'];
            if (!isset($by_paper[$paper])) {
                $by_paper[$paper] = [];
            }
            $by_paper[$paper][] = $rate;
        }

        // Display summary
        echo '<div class="grid grid-cols-4 gap-4 mb-8">';
        echo '<div class="bg-white rounded-lg shadow p-4">';
        echo '<div class="text-sm text-gray-600">Total Rates</div>';
        echo '<div class="text-2xl font-bold text-gray-900">' . count($rates) . '</div>';
        echo '</div>';

        echo '<div class="bg-white rounded-lg shadow p-4">';
        echo '<div class="text-sm text-gray-600">Papers</div>';
        echo '<div class="text-2xl font-bold text-gray-900">' . count($by_paper) . '</div>';
        echo '</div>';

        echo '<div class="bg-white rounded-lg shadow p-4">';
        echo '<div class="text-sm text-gray-600">Market Rates</div>';
        echo '<div class="text-2xl font-bold text-gray-900">' . count($market_rates) . '</div>';
        echo '</div>';

        $legacy_count = 0;
        foreach ($rates as $rate) {
            $key = $rate['paper_code'] . '_' . $rate['subscription_length'];
            if ($rate['rate'] < $market_rates[$key]) {
                $legacy_count++;
            }
        }

        echo '<div class="bg-white rounded-lg shadow p-4">';
        echo '<div class="text-sm text-gray-600">Legacy Rates</div>';
        echo '<div class="text-2xl font-bold text-orange-600">' . $legacy_count . '</div>';
        echo '</div>';
        echo '</div>';

        // Display rates by paper
        foreach ($by_paper as $paper => $paper_rates) {
            // Sort by subscription length then rate descending
            usort($paper_rates, function ($a, $b) {
                if ($a['subscription_length'] === $b['subscription_length']) {
                    return $b['rate'] <=> $a['rate'];
                }
                return strcmp($a['subscription_length'], $b['subscription_length']);
            });

            echo '<div class="bg-white rounded-lg shadow mb-6">';
            echo '<div class="px-6 py-4 border-b border-gray-200">';
            echo '<h2 class="text-xl font-bold text-gray-900">' . htmlspecialchars($paper) . '</h2>';
            echo '<p class="text-sm text-gray-600">' . count($paper_rates) . ' rates</p>';
            echo '</div>';

            echo '<div class="overflow-x-auto">';
            echo '<table class="min-w-full divide-y divide-gray-200">';
            echo '<thead class="bg-gray-50">';
            echo '<tr>';
            echo '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rate Name</th>';
            echo '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Length</th>';
            echo '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Zone</th>';
            echo '<th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Rate</th>';
            echo '<th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Type</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody class="bg-white divide-y divide-gray-200">';

            $current_length = null;
            foreach ($paper_rates as $rate) {
                $key = $rate['paper_code'] . '_' . $rate['subscription_length'];
                $is_market = ($rate['rate'] == $market_rates[$key]);
                $is_legacy = ($rate['rate'] < $market_rates[$key]);

                // Add visual separator between subscription lengths
                if ($current_length !== null && $current_length !== $rate['subscription_length']) {
                    echo '<tr class="bg-gray-50"><td colspan="5" class="h-2"></td></tr>';
                }
                $current_length = $rate['subscription_length'];

                $row_class = $is_market ? 'bg-green-50' : ($is_legacy ? 'bg-orange-50' : '');

                echo '<tr class="' . $row_class . '">';
                echo '<td class="px-6 py-3 text-sm text-gray-900">' . htmlspecialchars($rate['description']) . '</td>';
                echo '<td class="px-6 py-3 text-sm text-gray-700">' . htmlspecialchars($rate['subscription_length']) . '</td>';
                echo '<td class="px-6 py-3 text-sm text-gray-700">' . htmlspecialchars($rate['zone']) . '</td>';
                echo '<td class="px-6 py-3 text-sm text-right font-semibold text-gray-900">$' . number_format($rate['rate'], 2) . '</td>';
                echo '<td class="px-6 py-3 text-center text-xs">';

                if ($is_market) {
                    echo '<span class="px-2 py-1 bg-green-100 text-green-800 rounded font-semibold">MARKET</span>';
                } elseif ($is_legacy) {
                    $gap = $market_rates[$key] - $rate['rate'];
                    echo '<span class="px-2 py-1 bg-orange-100 text-orange-800 rounded font-semibold">LEGACY (-$' . number_format($gap, 2) . ')</span>';
                }

                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
            echo '</div>';
            echo '</div>';
        }
        ?>

        <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h3 class="font-semibold text-blue-900 mb-2">Legend</h3>
            <ul class="text-sm text-blue-800 space-y-1">
                <li><span class="inline-block w-4 h-4 bg-green-100 border border-green-200 mr-2"></span><strong>MARKET</strong> = Highest rate for this paper + subscription length</li>
                <li><span class="inline-block w-4 h-4 bg-orange-100 border border-orange-200 mr-2"></span><strong>LEGACY</strong> = Below market rate (shows gap amount)</li>
                <li><span class="inline-block w-4 h-4 bg-white border border-gray-200 mr-2"></span>No highlight = At market rate (no gap)</li>
            </ul>
        </div>
    </div>
</body>
</html>
