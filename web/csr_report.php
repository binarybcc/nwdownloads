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
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CSR Name</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Outgoing</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Received</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Missed</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody" class="bg-white divide-y divide-gray-200">
                    </tbody>
                </table>
            </div>
        </div>

        <div id="tableEmpty" class="hidden bg-white rounded-lg shadow p-8 text-center text-gray-500">
            No call data available for the last 60 days.
        </div>

        <!-- Chart -->
        <h2 class="text-lg font-semibold text-gray-900 mt-8 mb-4">Weekly Call Volume</h2>

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
                        buildTable(data.summary);
                    }

                    // Build chart
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

        function buildTable(summary) {
            var tbody = document.getElementById('tableBody');
            var totalPlaced = 0;
            var totalReceived = 0;
            var totalMissed = 0;
            var totalAll = 0;

            summary.forEach(function (csr) {
                totalPlaced += csr.placed;
                totalReceived += csr.received;
                totalMissed += csr.missed;
                totalAll += csr.total;

                var row = document.createElement('tr');
                row.innerHTML =
                    '<td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">' + csr.name + '</td>' +
                    '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">' + csr.placed + '</td>' +
                    '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">' + csr.received + '</td>' +
                    '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">' + csr.missed + '</td>' +
                    '<td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">' + csr.total + '</td>';
                tbody.appendChild(row);
            });

            // Totals row
            var totalsRow = document.createElement('tr');
            totalsRow.className = 'bg-gray-50';
            totalsRow.innerHTML =
                '<td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">Total</td>' +
                '<td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">' + totalPlaced + '</td>' +
                '<td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">' + totalReceived + '</td>' +
                '<td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">' + totalMissed + '</td>' +
                '<td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">' + totalAll + '</td>';
            tbody.appendChild(totalsRow);
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

            // Build datasets: for each CSR, 3 stacked datasets (placed, received, missed)
            var datasets = [];
            var directionColors = {
                placed:   'rgba(59, 130, 246, 0.7)',
                received: 'rgba(34, 197, 94, 0.7)',
                missed:   'rgba(239, 68, 68, 0.5)'
            };

            csrs.forEach(function (csr) {
                ['placed', 'received', 'missed'].forEach(function (direction) {
                    var dataPoints = weekStarts.map(function (ws) {
                        var match = weekly.find(function (row) {
                            return row.week_start === ws && row.group === csr.group;
                        });
                        return match ? match[direction] : 0;
                    });

                    datasets.push({
                        label: csr.name + ' - ' + direction.charAt(0).toUpperCase() + direction.slice(1),
                        data: dataPoints,
                        backgroundColor: directionColors[direction],
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
                        }
                    }
                }
            });
        }
    </script>

</body>
</html>
