<?php

require_once 'auth_check.php';
require_once 'version.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSR Call Activity - Circulation Dashboard</title>

    <!-- Tailwind CSS -->
    <link rel="stylesheet" href="assets/output.css?v=20251206">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        * { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
    </style>
</head>
<body class="bg-gray-50">

    <!-- Header -->
    <header class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <a href="settings.php" class="text-gray-600 hover:text-gray-900 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                    </a>
                    <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
                        <svg class="w-7 h-7 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                        </svg>
                        CSR Call Activity
                    </h1>
                </div>
                <p class="text-sm text-gray-500 hidden sm:block">Outgoing, received, and missed calls per CSR -- rolling 60-day window</p>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Data as of -->
        <p id="lastUpdated" class="text-sm text-gray-500 mb-6">Loading...</p>

        <!-- Summary Table -->
        <div id="tableSkeleton" class="bg-white rounded-lg shadow overflow-hidden mb-8">
            <div class="p-6 space-y-4">
                <div class="h-4 bg-gray-200 rounded animate-pulse w-1/3"></div>
                <div class="h-10 bg-gray-200 rounded animate-pulse"></div>
                <div class="h-10 bg-gray-200 rounded animate-pulse"></div>
                <div class="h-10 bg-gray-200 rounded animate-pulse"></div>
            </div>
        </div>

        <div id="tableContainer" class="hidden">
            <div class="overflow-x-auto bg-white rounded-lg shadow">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th rowspan="2" class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider align-bottom">CSR Name</th>
                            <th colspan="3" class="px-2 py-2 bg-blue-50 text-center text-xs font-medium text-blue-700 uppercase tracking-wider border-b border-blue-200">Outgoing</th>
                            <th colspan="3" class="px-2 py-2 bg-green-50 text-center text-xs font-medium text-green-700 uppercase tracking-wider border-b border-green-200">Received</th>
                            <th colspan="3" class="px-2 py-2 bg-red-50 text-center text-xs font-medium text-red-700 uppercase tracking-wider border-b border-red-200" title="Business hours only: M-F 8am-5pm ET">Missed *</th>
                            <th rowspan="2" class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider align-bottom">Total</th>
                        </tr>
                        <tr>
                            <th class="px-3 py-2 bg-blue-50 text-center text-xs font-medium text-blue-600">Ext</th>
                            <th class="px-3 py-2 bg-blue-50 text-center text-xs font-medium text-blue-600">Other</th>
                            <th class="px-3 py-2 bg-blue-50 text-center text-xs font-medium text-blue-700 font-bold">All</th>
                            <th class="px-3 py-2 bg-green-50 text-center text-xs font-medium text-green-600">Ext</th>
                            <th class="px-3 py-2 bg-green-50 text-center text-xs font-medium text-green-600">Other</th>
                            <th class="px-3 py-2 bg-green-50 text-center text-xs font-medium text-green-700 font-bold">All</th>
                            <th class="px-3 py-2 bg-red-50 text-center text-xs font-medium text-red-600">Ext</th>
                            <th class="px-3 py-2 bg-red-50 text-center text-xs font-medium text-red-600">Other</th>
                            <th class="px-3 py-2 bg-red-50 text-center text-xs font-medium text-red-700 font-bold">All</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody" class="bg-white divide-y divide-gray-200">
                    </tbody>
                </table>
            </div>
        </div>

        <p id="tableFootnote" class="hidden text-xs text-gray-400 mt-2 ml-1">* Missed calls counted during business hours only (M–F, 8 AM – 5 PM ET)</p>

        <div id="tableEmpty" class="hidden bg-white rounded-lg shadow p-8 text-center text-gray-500">
            No call data available for the last 60 days.
        </div>

        <!-- Insight Cards -->
        <div id="insightCards" class="hidden grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">

            <!-- Callback Rate -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center gap-3 mb-1">
                    <div class="w-10 h-10 rounded-lg bg-indigo-100 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">Subscriber Callback Rate</h3>
                        <p class="text-xs text-gray-500">Called back within 48 hrs of outbound call</p>
                    </div>
                </div>
                <div id="callbackStats" class="mt-4 space-y-4"></div>
            </div>

            <!-- Workload Ratio -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center gap-3 mb-1">
                    <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">Outreach Coverage</h3>
                        <p class="text-xs text-gray-500">Calls to subscribers vs. expiring 0–4 weeks</p>
                    </div>
                </div>
                <div id="workloadStats" class="mt-4 space-y-3"></div>
            </div>

        </div>

        <!-- Hero Chart: Calls to Known Subscribers -->
        <div class="mt-8 mb-2">
            <h2 class="text-lg font-semibold text-gray-900">Calls to Known Subscribers</h2>
            <p class="text-sm text-gray-500">Outbound calls matched to a subscriber phone number on file (floor count)</p>
            <p id="heroDateRange" class="text-xs text-gray-400 mt-1"></p>
        </div>

        <div id="heroSkeleton" class="animate-pulse h-48 bg-gray-200 rounded-lg mb-8"></div>

        <div id="heroContainer" class="hidden mb-8">
            <canvas id="heroChart"></canvas>
        </div>

        <!-- Detail Chart: All Call Activity -->
        <h2 class="text-lg font-semibold text-gray-900 mt-4 mb-1">All Call Activity</h2>
        <p id="detailDateRange" class="text-xs text-gray-400 mb-4"></p>

        <div id="chartSkeleton" class="animate-pulse h-64 bg-gray-200 rounded-lg"></div>

        <div id="chartContainer" class="hidden">
            <canvas id="csrChart"></canvas>
        </div>

        <div id="chartEmpty" class="hidden bg-white rounded-lg shadow p-8 text-center text-gray-500">
            No weekly data available.
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
                    &copy; <?php echo date('Y'); ?> Edwards Group Holdings, Inc. ESOP
                </div>
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            fetch('api/csr_report.php')
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (!data.success) {
                        showError(data.error || 'Failed to load report data');
                        return;
                    }

                    // Update last updated
                    var lastUpdatedEl = document.getElementById('lastUpdated');
                    if (data.last_updated) {
                        lastUpdatedEl.textContent = 'Data as of: ' + data.last_updated;
                    } else {
                        lastUpdatedEl.textContent = 'No data imported yet';
                    }

                    // Build summary table
                    document.getElementById('tableSkeleton').classList.add('hidden');
                    if (data.summary.length === 0) {
                        document.getElementById('tableEmpty').classList.remove('hidden');
                    } else {
                        document.getElementById('tableContainer').classList.remove('hidden');
                        document.getElementById('tableFootnote').classList.remove('hidden');
                        buildTable(data.summary);
                    }

                    // Build insight cards
                    if (data.callbacks && data.callbacks.length > 0 && data.workload && data.workload.length > 0) {
                        document.getElementById('insightCards').classList.remove('hidden');
                        buildInsightCards(data.callbacks, data.workload);
                    }

                    // Show date range
                    if (data.date_range && data.date_range.earliest) {
                        var fmt = function(d) { return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }); };
                        var rangeText = fmt(data.date_range.earliest) + ' – ' + fmt(data.date_range.latest);
                        document.getElementById('heroDateRange').textContent = rangeText;
                        document.getElementById('detailDateRange').textContent = rangeText;
                    }

                    // Build hero chart (subscriber calls)
                    document.getElementById('heroSkeleton').classList.add('hidden');
                    if (data.subscriber_calls && data.subscriber_calls.length > 0) {
                        document.getElementById('heroContainer').classList.remove('hidden');
                        buildHeroChart(data.subscriber_calls, data.summary);
                    }

                    // Build detail chart
                    document.getElementById('chartSkeleton').classList.add('hidden');
                    if (data.weekly.length === 0) {
                        document.getElementById('chartEmpty').classList.remove('hidden');
                    } else {
                        document.getElementById('chartContainer').classList.remove('hidden');
                        buildChart(data.weekly, data.summary);
                    }
                })
                .catch(function (error) {
                    showError('Network error: ' + error.message);
                });
        });

        function showError(message) {
            document.getElementById('tableSkeleton').innerHTML =
                '<div class="p-6 text-center text-red-600">' + message + '</div>';
            document.getElementById('chartSkeleton').innerHTML =
                '<div class="p-6 text-center text-red-600">' + message + '</div>';
        }

        // All table values are integers from our own API — safe for innerHTML
        function buildTable(summary) {
            var tbody = document.getElementById('tableBody');
            var totals = { placed_ext: 0, placed_other: 0, placed: 0, received_ext: 0, received_other: 0, received: 0, missed_ext: 0, missed_other: 0, missed: 0, total: 0 };

            summary.forEach(function (csr) {
                totals.placed_ext += csr.placed_ext;
                totals.placed_other += csr.placed_other;
                totals.placed += csr.placed;
                totals.received_ext += csr.received_ext;
                totals.received_other += csr.received_other;
                totals.received += csr.received;
                totals.missed_ext += csr.missed_ext;
                totals.missed_other += csr.missed_other;
                totals.missed += csr.missed;
                totals.total += csr.total;

                var row = document.createElement('tr');
                var cell = function(val, cls) { return '<td class="px-3 py-4 whitespace-nowrap text-sm text-center ' + cls + '">' + (val || '-') + '</td>'; };
                row.innerHTML =
                    '<td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">' + csr.name + '</td>' +
                    cell(csr.placed_ext, 'text-blue-400') +
                    cell(csr.placed_other, 'text-blue-600') +
                    cell(csr.placed, 'font-semibold text-blue-800') +
                    cell(csr.received_ext, 'text-green-400') +
                    cell(csr.received_other, 'text-green-600') +
                    cell(csr.received, 'font-semibold text-green-800') +
                    cell(csr.missed_ext, 'text-red-400') +
                    cell(csr.missed_other, 'text-red-600') +
                    cell(csr.missed, 'font-semibold text-red-800') +
                    '<td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">' + csr.total + '</td>';
                tbody.appendChild(row);
            });

            // Totals row
            var totalsRow = document.createElement('tr');
            totalsRow.className = 'bg-gray-50';
            var bold = function(val, cls) { return '<td class="px-3 py-4 whitespace-nowrap text-sm text-center font-bold ' + cls + '">' + val + '</td>'; };
            totalsRow.innerHTML =
                '<td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">Total</td>' +
                bold(totals.placed_ext, 'text-blue-400') +
                bold(totals.placed_other, 'text-blue-600') +
                bold(totals.placed, 'text-blue-800') +
                bold(totals.received_ext, 'text-green-400') +
                bold(totals.received_other, 'text-green-600') +
                bold(totals.received, 'text-green-800') +
                bold(totals.missed_ext, 'text-red-400') +
                bold(totals.missed_other, 'text-red-600') +
                bold(totals.missed, 'text-red-800') +
                '<td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">' + totals.total + '</td>';
            tbody.appendChild(totalsRow);
        }

        // All values are integers/strings from our own API — safe for innerHTML
        function buildInsightCards(callbacks, workload) {
            // Callback rate card — per-CSR rows with progress bars
            var cbContainer = document.getElementById('callbackStats');
            var cbHTML = '';
            callbacks.forEach(function (cb) {
                var barWidth = Math.max(cb.rate_pct, 3);
                var barColor = cb.rate_pct >= 20 ? 'bg-indigo-500' : 'bg-indigo-300';
                cbHTML +=
                    '<div class="bg-gray-50 rounded-lg p-3">' +
                    '<div class="flex items-center justify-between mb-2">' +
                    '<span class="text-sm font-medium text-gray-700">' + cb.name + '</span>' +
                    '<span class="text-lg font-bold text-indigo-600">' + cb.rate_pct + '<span class="text-xs font-normal text-gray-400">%</span></span>' +
                    '</div>' +
                    '<div class="w-full bg-gray-200 rounded-full h-2.5">' +
                    '<div class="' + barColor + ' h-2.5 rounded-full transition-all" style="width:' + barWidth + '%"></div>' +
                    '</div>' +
                    '<div class="flex justify-between mt-1.5">' +
                    '<span class="text-xs text-gray-400">' + cb.got_callback + ' of ' + cb.placed_to_subs + ' unique numbers called back</span>' +
                    '</div>' +
                    '</div>';
            });
            cbContainer.innerHTML = cbHTML;

            // Workload ratio card — per-CSR with large metric and context bar
            var wlContainer = document.getElementById('workloadStats');
            var wlHTML = '';
            var expCount = workload[0].expiring_count;
            workload.forEach(function (wl) {
                // Visual: what fraction of expiring subs have been called
                var coveragePct = expCount > 0 ? Math.min(Math.round((wl.calls_to_subs / expCount) * 100), 100) : 0;
                var barColor = coveragePct >= 10 ? 'bg-amber-500' : 'bg-amber-300';
                wlHTML +=
                    '<div class="bg-gray-50 rounded-lg p-3">' +
                    '<div class="flex items-center justify-between mb-2">' +
                    '<span class="text-sm font-medium text-gray-700">' + wl.name + '</span>' +
                    '<span class="text-lg font-bold text-amber-600">' + wl.calls_to_subs + ' <span class="text-xs font-normal text-gray-400">calls</span></span>' +
                    '</div>' +
                    '<div class="w-full bg-gray-200 rounded-full h-2.5">' +
                    '<div class="' + barColor + ' h-2.5 rounded-full transition-all" style="width:' + Math.max(coveragePct, 2) + '%"></div>' +
                    '</div>' +
                    '<div class="flex justify-between mt-1.5">' +
                    '<span class="text-xs text-gray-400">' + coveragePct + '% of ' + expCount + ' expiring subscribers contacted</span>' +
                    '</div>' +
                    '</div>';
            });
            wlContainer.innerHTML = wlHTML;
        }

        function buildHeroChart(subscriberCalls, summary) {
            // Get unique week starts
            var weekStarts = [];
            var weekLabels = [];
            subscriberCalls.forEach(function (row) {
                if (weekStarts.indexOf(row.week_start) === -1) {
                    weekStarts.push(row.week_start);
                    var d = new Date(row.week_start + 'T00:00:00');
                    weekLabels.push('Week of ' + d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
                }
            });

            // Get unique CSRs
            var csrs = [];
            summary.forEach(function (csr) {
                csrs.push({ name: csr.name, group: csr.group });
            });

            // CSR colors: bold for subscriber calls, muted for other
            var csrColors = [
                { subscriber: 'rgba(37, 99, 235, 0.9)',  other: 'rgba(191, 219, 254, 0.6)' },
                { subscriber: 'rgba(5, 150, 105, 0.9)',  other: 'rgba(167, 243, 208, 0.6)' },
                { subscriber: 'rgba(168, 85, 247, 0.9)', other: 'rgba(221, 214, 254, 0.6)' }
            ];

            var datasets = [];
            csrs.forEach(function (csr, idx) {
                var colors = csrColors[idx % csrColors.length];

                // Subscriber calls (bold)
                var subData = weekStarts.map(function (ws) {
                    var match = subscriberCalls.find(function (row) {
                        return row.week_start === ws && row.group === csr.group;
                    });
                    return match ? match.to_subscriber : 0;
                });
                datasets.push({
                    label: csr.name + ' — To Subscribers',
                    data: subData,
                    backgroundColor: colors.subscriber,
                    borderColor: colors.subscriber.replace('0.9', '1'),
                    borderWidth: 1,
                    stack: csr.group
                });

                // Other outbound (muted)
                var otherData = weekStarts.map(function (ws) {
                    var match = subscriberCalls.find(function (row) {
                        return row.week_start === ws && row.group === csr.group;
                    });
                    return match ? match.to_other : 0;
                });
                datasets.push({
                    label: csr.name + ' — Other Outbound',
                    data: otherData,
                    backgroundColor: colors.other,
                    borderColor: colors.other.replace('0.6', '0.8'),
                    borderWidth: 1,
                    stack: csr.group
                });
            });

            var ctx = document.getElementById('heroChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: { labels: weekLabels, datasets: datasets },
                options: {
                    responsive: true,
                    scales: {
                        x: { stacked: true },
                        y: { stacked: true, beginAtZero: true, title: { display: true, text: 'Outbound Calls' } }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { boxWidth: 14, padding: 12 }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.raw;
                                }
                            }
                        }
                    }
                }
            });
        }

        function buildChart(weekly, summary) {
            // Get unique week starts in order
            var weekStarts = [];
            var weekLabels = [];
            weekly.forEach(function (row) {
                if (weekStarts.indexOf(row.week_start) === -1) {
                    weekStarts.push(row.week_start);
                    var d = new Date(row.week_start + 'T00:00:00');
                    weekLabels.push(d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
                }
            });

            // Get unique CSRs
            var csrs = [];
            summary.forEach(function (csr) {
                csrs.push({ name: csr.name, group: csr.group });
            });

            // Build datasets: for each CSR, 6 stacked layers (ext/other for placed, received, missed)
            // Darker shade = 4-digit extension (internal), lighter shade = other (external)
            var datasets = [];
            var layerConfig = [
                { key: 'placed_ext',     label: 'Placed (Ext)',    color: 'rgba(37, 99, 235, 0.85)'  },
                { key: 'placed_other',   label: 'Placed (Other)',  color: 'rgba(147, 197, 253, 0.8)' },
                { key: 'received_ext',   label: 'Received (Ext)',  color: 'rgba(22, 163, 74, 0.85)'  },
                { key: 'received_other', label: 'Received (Other)',color: 'rgba(134, 239, 172, 0.8)' },
                { key: 'missed_ext',     label: 'Missed (Ext)',    color: 'rgba(220, 38, 38, 0.85)'  },
                { key: 'missed_other',   label: 'Missed (Other)', color: 'rgba(252, 165, 165, 0.7)' }
            ];

            csrs.forEach(function (csr) {
                layerConfig.forEach(function (layer) {
                    var dataPoints = weekStarts.map(function (ws) {
                        var match = weekly.find(function (row) {
                            return row.week_start === ws && row.group === csr.group;
                        });
                        return match ? match[layer.key] : 0;
                    });

                    datasets.push({
                        label: csr.name + ' - ' + layer.label,
                        data: dataPoints,
                        backgroundColor: layer.color,
                        stack: csr.group
                    });
                });
            });

            var ctx = document.getElementById('csrChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: weekLabels,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    scales: {
                        x: {
                            stacked: true
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 15
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.raw;
                                }
                            }
                        }
                    }
                }
            });
        }
    </script>

</body>
</html>
