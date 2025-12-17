<?php

/**
 * File Processing Settings - Circulation Dashboard
 * Manage automated file processing notifications and view processing history
 */

require_once 'auth_check.php';
require_once 'version.php';
require_once __DIR__ . '/notifications/DashboardNotifier.php';

// Database connection
try {
    $pdo = new PDO(
        'mysql:host=database;port=3306;dbname=circulation_dashboard;charset=utf8mb4',
        getenv('DB_USER') ?: 'circ_dash',
        getenv('DB_PASSWORD') ?: 'Barnaby358@Jones!',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Load notification settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM notification_settings");
$settings = [];
foreach ($stmt->fetchAll() as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Get email addresses
$emailAddresses = json_decode($settings['email_addresses'] ?? '[]', true) ?: [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Processing Settings - Circulation Dashboard</title>

    <!-- Tailwind CSS -->
    <link rel="stylesheet" href="assets/output.css?v=20251206">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        * {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        .section-card {
            transition: box-shadow 200ms ease;
        }

        .section-card:hover {
            box-shadow: 0 12px 24px rgba(0,0,0,0.08);
        }
    </style>
</head>
<body class="bg-gray-50">

    <!-- Header -->
    <header class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <a href="settings.php" class="text-gray-600 hover:text-gray-900 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                    </a>
                    <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
                        <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        File Processing Settings
                    </h1>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-gray-600">
                        Logged in as <strong><?php echo htmlspecialchars($_SESSION['user']); ?></strong>
                    </span>
                    <a href="logout.php" class="text-red-600 hover:text-red-700 transition" title="Sign out">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <!-- Notification Settings Section -->
        <section id="notification-settings" class="section-card bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                        Notification Settings
                    </h2>
                    <p class="text-sm text-gray-600 mt-1">
                        Configure email notifications for file processing failures
                    </p>
                </div>
                <button onclick="saveSettings()" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                    Save Changes
                </button>
            </div>

            <div class="space-y-6">
                <!-- Email Recipients -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Email Recipients
                    </label>
                    <div id="emailList" class="space-y-2 mb-3">
                        <?php foreach ($emailAddresses as $index => $email): ?>
                            <div class="flex items-center gap-2">
                                <input type="email"
                                       class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm"
                                       value="<?php echo htmlspecialchars($email); ?>"
                                       data-email-index="<?php echo $index; ?>">
                                <button onclick="removeEmail(<?php echo $index; ?>)"
                                        class="text-red-600 hover:text-red-700 p-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button onclick="addEmail()" class="text-purple-600 hover:text-purple-700 text-sm font-medium flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Add Email Address
                    </button>
                    <p class="text-xs text-gray-500 mt-2">
                        Emails will be sent when automated file processing fails
                    </p>
                </div>

                <!-- Toggles -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                        <div>
                            <label class="text-sm font-medium text-gray-900">Failure Emails</label>
                            <p class="text-xs text-gray-500">Send email on processing failures</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="enableFailureEmails" class="sr-only peer"
                                   <?php echo ($settings['enable_failure_emails'] ?? 'true') === 'true' ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                        </label>
                    </div>

                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                        <div>
                            <label class="text-sm font-medium text-gray-900">Success Banners</label>
                            <p class="text-xs text-gray-500">Show dashboard success notifications</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="enableSuccessDashboard" class="sr-only peer"
                                   <?php echo ($settings['enable_success_dashboard'] ?? 'true') === 'true' ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                        </label>
                    </div>
                </div>
            </div>
        </section>

        <!-- Processing History Section -->
        <section id="processing-history" class="section-card bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Processing History
                    </h2>
                    <p class="text-sm text-gray-600 mt-1">
                        Recent file processing runs and their results
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <select id="statusFilter" onchange="loadHistory()" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">All Statuses</option>
                        <option value="completed">Completed</option>
                        <option value="failed">Failed</option>
                        <option value="processing">Processing</option>
                    </select>
                    <button onclick="loadHistory()" class="text-gray-600 hover:text-gray-900 p-2" title="Refresh">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- History Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date/Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Filename</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Records</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                        </tr>
                    </thead>
                    <tbody id="historyTableBody" class="bg-white divide-y divide-gray-200">
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                Loading...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

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

    <script>
        // Email management using safe DOM methods
        function addEmail() {
            const emailList = document.getElementById('emailList');
            const newIndex = emailList.children.length;

            const div = document.createElement('div');
            div.className = 'flex items-center gap-2';

            const input = document.createElement('input');
            input.type = 'email';
            input.className = 'flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm';
            input.placeholder = 'email@example.com';
            input.dataset.emailIndex = newIndex;

            const button = document.createElement('button');
            button.className = 'text-red-600 hover:text-red-700 p-2';
            button.onclick = () => removeEmail(newIndex);
            button.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>';

            div.appendChild(input);
            div.appendChild(button);
            emailList.appendChild(div);
        }

        function removeEmail(index) {
            const emailList = document.getElementById('emailList');
            const inputs = emailList.querySelectorAll('input[data-email-index]');
            if (inputs[index]) {
                inputs[index].closest('div').remove();
            }
        }

        // Save settings
        async function saveSettings() {
            const emailInputs = document.querySelectorAll('#emailList input[type="email"]');
            const emails = Array.from(emailInputs).map(input => input.value.trim()).filter(email => email !== '');

            const settings = {
                email_addresses: JSON.stringify(emails),
                enable_failure_emails: document.getElementById('enableFailureEmails').checked ? 'true' : 'false',
                enable_success_dashboard: document.getElementById('enableSuccessDashboard').checked ? 'true' : 'false'
            };

            try {
                const response = await fetch('api/save_notification_settings.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(settings)
                });

                const data = await response.json();

                if (data.success) {
                    alert('✓ Settings saved successfully!');
                } else {
                    alert('✗ Failed to save settings: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                alert('✗ Failed to save settings: ' + error.message);
            }
        }

        // Load processing history using safe DOM methods
        async function loadHistory() {
            const statusFilter = document.getElementById('statusFilter').value;
            const tbody = document.getElementById('historyTableBody');

            // Clear tbody
            tbody.textContent = '';
            const loadingRow = document.createElement('tr');
            const loadingCell = document.createElement('td');
            loadingCell.colSpan = 6;
            loadingCell.className = 'px-6 py-4 text-center text-sm text-gray-500';
            loadingCell.textContent = 'Loading...';
            loadingRow.appendChild(loadingCell);
            tbody.appendChild(loadingRow);

            try {
                const url = statusFilter
                    ? `api/get_processing_history.php?status=${encodeURIComponent(statusFilter)}`
                    : 'api/get_processing_history.php';

                const response = await fetch(url);
                const data = await response.json();

                tbody.textContent = ''; // Clear loading message

                if (data.success && data.history.length > 0) {
                    data.history.forEach(entry => {
                        const row = document.createElement('tr');
                        row.className = 'hover:bg-gray-50';

                        // Date/Time
                        const dateCell = document.createElement('td');
                        dateCell.className = 'px-6 py-4 whitespace-nowrap text-sm text-gray-900';
                        dateCell.textContent = entry.started_at;
                        row.appendChild(dateCell);

                        // Filename
                        const filenameCell = document.createElement('td');
                        filenameCell.className = 'px-6 py-4 text-sm text-gray-900';
                        filenameCell.textContent = entry.filename;
                        row.appendChild(filenameCell);

                        // Type
                        const typeCell = document.createElement('td');
                        typeCell.className = 'px-6 py-4 whitespace-nowrap text-sm text-gray-600';
                        typeCell.textContent = entry.file_type;
                        row.appendChild(typeCell);

                        // Status
                        const statusCell = document.createElement('td');
                        statusCell.className = 'px-6 py-4 whitespace-nowrap';
                        const statusBadge = document.createElement('span');
                        statusBadge.className = 'px-2 py-1 text-xs font-semibold rounded-full';
                        if (entry.status === 'completed') {
                            statusBadge.className += ' bg-green-100 text-green-800';
                            statusBadge.textContent = 'Completed';
                        } else if (entry.status === 'failed') {
                            statusBadge.className += ' bg-red-100 text-red-800';
                            statusBadge.textContent = 'Failed';
                        } else {
                            statusBadge.className += ' bg-yellow-100 text-yellow-800';
                            statusBadge.textContent = 'Processing';
                        }
                        statusCell.appendChild(statusBadge);
                        row.appendChild(statusCell);

                        // Records
                        const recordsCell = document.createElement('td');
                        recordsCell.className = 'px-6 py-4 whitespace-nowrap text-sm text-gray-600';
                        recordsCell.textContent = entry.records_processed || 0;
                        row.appendChild(recordsCell);

                        // Duration
                        const durationCell = document.createElement('td');
                        durationCell.className = 'px-6 py-4 whitespace-nowrap text-sm text-gray-600';
                        durationCell.textContent = entry.processing_duration_seconds ? entry.processing_duration_seconds + 's' : '--';
                        row.appendChild(durationCell);

                        tbody.appendChild(row);
                    });
                } else {
                    const emptyRow = document.createElement('tr');
                    const emptyCell = document.createElement('td');
                    emptyCell.colSpan = 6;
                    emptyCell.className = 'px-6 py-4 text-center text-sm text-gray-500';
                    emptyCell.textContent = 'No processing history found';
                    emptyRow.appendChild(emptyCell);
                    tbody.appendChild(emptyRow);
                }
            } catch (error) {
                tbody.textContent = '';
                const errorRow = document.createElement('tr');
                const errorCell = document.createElement('td');
                errorCell.colSpan = 6;
                errorCell.className = 'px-6 py-4 text-center text-sm text-red-600';
                errorCell.textContent = 'Error loading history';
                errorRow.appendChild(errorCell);
                tbody.appendChild(errorRow);
            }
        }

        // Load history on page load
        document.addEventListener('DOMContentLoaded', loadHistory);
    </script>

</body>
</html>
