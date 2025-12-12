<?php
/**
 * Unified Upload Page - Requires Authentication
 *
 * This page provides a tabbed interface for uploading different types of data:
 * - Subscriber data (All Subscriber Report)
 * - Vacation data (Subscribers On Vacation)
 * - Future: Rates, Events, etc.
 */

// Require authentication
require_once 'auth_check.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Upload - Circulation Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Tab styling */
        .tab-button {
            position: relative;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.2s;
            border-bottom: 2px solid transparent;
        }
        .tab-button:hover {
            background-color: rgba(59, 130, 246, 0.1);
        }
        .tab-button.active {
            color: #2563eb;
            border-bottom-color: #2563eb;
            background-color: rgba(59, 130, 246, 0.05);
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen p-8">
        <div class="max-w-5xl mx-auto">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <h1 class="text-3xl font-bold text-gray-900">📊 Data Upload Center</h1>
                <p class="text-gray-600 mt-2">Upload and manage circulation dashboard data</p>
                <a href="index.php" class="text-blue-600 hover:text-blue-700 text-sm mt-2 inline-block">← Back to Dashboard</a>
            </div>

            <!-- Tabbed Interface -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <!-- Tab Headers -->
                <div class="flex border-b border-gray-200 bg-gray-50">
                    <button
                        class="tab-button active"
                        data-tab="subscribers"
                        onclick="switchTab('subscribers')"
                    >
                        📄 Subscribers
                    </button>
                    <button
                        class="tab-button"
                        data-tab="vacations"
                        onclick="switchTab('vacations')"
                    >
                        🏖️ Vacations
                    </button>
                    <!-- Future tabs can be added here -->
                    <!--
                    <button class="tab-button" data-tab="rates" onclick="switchTab('rates')">
                        💵 Rates
                    </button>
                    -->
                </div>

                <!-- Tab Content -->
                <div class="p-6">
                    <!-- Subscribers Tab -->
                    <div id="subscribers-tab" class="tab-content active">
                        <h2 class="text-xl font-semibold mb-4">Upload All Subscriber Report</h2>

                        <form id="subscribersForm" class="space-y-4">
                            <!-- All Subscriber Report File -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    📄 All Subscriber Report CSV (required)
                                </label>
                                <input type="file"
                                       name="allsubscriber"
                                       id="subscribersFileInput"
                                       accept=".csv"
                                       required
                                       class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none p-2">
                                <p class="text-xs text-gray-500 mt-1">
                                    Format: AllSubscriberReport<strong>YYYYMMDDHHMMSS</strong>.csv
                                </p>
                                <p id="subscribersFileInfo" class="text-xs text-blue-600 mt-1 hidden"></p>
                            </div>

                            <!-- Submit Button -->
                            <div class="pt-4">
                                <button type="submit" id="subscribersUploadBtn"
                                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200">
                                    🚀 Upload and Process Subscriber Data
                                </button>
                            </div>
                        </form>

                        <!-- Progress/Results -->
                        <div id="subscribersProgress" class="mt-6 hidden">
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <div class="flex items-center">
                                    <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600 mr-3"></div>
                                    <span class="text-blue-800 font-medium" id="subscribersProgressText">Processing file...</span>
                                </div>
                                <div class="mt-2 text-sm text-blue-600" id="subscribersProgressDetails"></div>
                            </div>
                        </div>

                        <div id="subscribersResult" class="mt-6 hidden">
                            <!-- Success/error message will appear here -->
                        </div>

                        <!-- Instructions -->
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <h3 class="text-lg font-semibold mb-3">📋 How to Upload Subscriber Data</h3>
                            <ol class="list-decimal list-inside space-y-2 text-gray-700 text-sm">
                                <li>Run "All Subscriber Report" query in Newzware Ad-Hoc Query Builder</li>
                                <li>Export results as CSV (saves as <code class="bg-gray-100 px-1 py-0.5 rounded text-xs">AllSubscriberReportYYYYMMDDHHMMSS.csv</code>)</li>
                                <li>Click "Choose File" above and select the CSV file</li>
                                <li>Click "Upload and Process Subscriber Data"</li>
                                <li>Wait for processing (typically 10-30 seconds for ~8,000 rows)</li>
                                <li>Review the import summary</li>
                            </ol>

                            <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded text-sm">
                                <p class="text-blue-800 font-semibold mb-1">📊 How Upsert Works:</p>
                                <ul class="text-blue-700 space-y-1 list-disc list-inside text-xs">
                                    <li><strong>New data:</strong> Adds new weekly snapshots automatically</li>
                                    <li><strong>Existing data:</strong> Updates with latest subscriber counts</li>
                                    <li><strong>Date filter:</strong> Only imports data from January 1, 2025 onwards</li>
                                    <li><strong>Safe:</strong> Never deletes data, only adds or updates</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Vacations Tab -->
                    <div id="vacations-tab" class="tab-content">
                        <h2 class="text-xl font-semibold mb-4">Upload Vacation Data</h2>

                        <form id="vacationsForm" class="space-y-4">
                            <!-- Vacation CSV File -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    🏖️ Subscribers On Vacation CSV (required)
                                </label>
                                <input type="file"
                                       name="csv_file"
                                       id="vacationsFileInput"
                                       accept=".csv"
                                       required
                                       class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none p-2">
                                <p class="text-xs text-gray-500 mt-1">
                                    Format: SubscribersOnVacation<strong>[timestamp]</strong>.csv from Newzware
                                </p>
                                <p id="vacationsFileInfo" class="text-xs text-blue-600 mt-1 hidden"></p>
                            </div>

                            <!-- Submit Button -->
                            <div class="pt-4">
                                <button type="submit" id="vacationsUploadBtn"
                                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200">
                                    🚀 Upload and Process Vacation Data
                                </button>
                            </div>
                        </form>

                        <!-- Progress/Results -->
                        <div id="vacationsProgress" class="mt-6 hidden">
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <div class="flex items-center">
                                    <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600 mr-3"></div>
                                    <span class="text-blue-800 font-medium" id="vacationsProgressText">Processing file...</span>
                                </div>
                            </div>
                        </div>

                        <div id="vacationsResult" class="mt-6 hidden">
                            <!-- Success/error message will appear here -->
                        </div>

                        <!-- Instructions -->
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <h3 class="text-lg font-semibold mb-3">📋 How to Upload Vacation Data</h3>
                            <ol class="list-decimal list-inside space-y-2 text-gray-700 text-sm">
                                <li>Run "Subscribers On Vacation" query in Newzware</li>
                                <li>Export results as CSV with vacation start/end dates</li>
                                <li>Click "Choose File" above and select the CSV file</li>
                                <li>Click "Upload and Process Vacation Data"</li>
                                <li>Review the import summary</li>
                            </ol>

                            <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded text-sm">
                                <p class="text-blue-800 font-semibold mb-1">🏖️ Vacation Data Updates:</p>
                                <ul class="text-blue-700 space-y-1 list-disc list-inside text-xs">
                                    <li>Updates vacation start/end dates for existing subscribers</li>
                                    <li>Calculates vacation duration in weeks automatically</li>
                                    <li>Displays longest vacations on dashboard</li>
                                    <li>Safe: Only updates vacation fields, preserves all other data</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab switching functionality
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
        }

        // ============================================
        // SUBSCRIBERS UPLOAD FUNCTIONALITY
        // ============================================
        const subscribersForm = document.getElementById('subscribersForm');
        const subscribersUploadBtn = document.getElementById('subscribersUploadBtn');
        const subscribersFileInput = document.getElementById('subscribersFileInput');
        const subscribersFileInfo = document.getElementById('subscribersFileInfo');
        const subscribersProgress = document.getElementById('subscribersProgress');
        const subscribersProgressText = document.getElementById('subscribersProgressText');
        const subscribersResult = document.getElementById('subscribersResult');

        // Show file info when selected
        subscribersFileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                const sizeMB = (file.size / 1024 / 1024).toFixed(2);
                subscribersFileInfo.textContent = `Selected: ${file.name} (${sizeMB} MB)`;
                subscribersFileInfo.classList.remove('hidden');
            }
        });

        subscribersForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            // Show progress
            subscribersProgress.classList.remove('hidden');
            subscribersResult.classList.add('hidden');
            subscribersResult.innerHTML = '';
            subscribersUploadBtn.disabled = true;
            subscribersUploadBtn.classList.add('opacity-50', 'cursor-not-allowed');

            try {
                const formData = new FormData(subscribersForm);
                subscribersProgressText.textContent = 'Uploading file to server...';

                const response = await fetch('upload.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                subscribersProgress.classList.add('hidden');

                if (data.success) {
                    subscribersResult.innerHTML = `
                        <div class="bg-green-50 border border-green-200 rounded-lg p-6">
                            <h3 class="text-green-800 font-semibold text-lg mb-2">✅ Import Successful!</h3>
                            <div class="text-green-700 space-y-1 mb-4 text-sm">
                                <p><strong>Date Range:</strong> ${data.date_range}</p>
                                <p><strong>New Records Added:</strong> ${data.new_records}</p>
                                <p><strong>Existing Records Updated:</strong> ${data.updated_records}</p>
                                <p><strong>Total Records Processed:</strong> ${data.total_processed}</p>
                                <p><strong>Processing Time:</strong> ${data.processing_time}</p>
                            </div>
                            <div class="bg-white rounded p-4 mb-4">
                                <h4 class="font-semibold mb-2 text-sm">📊 Summary by Business Unit:</h4>
                                <div class="space-y-2 text-sm">
                                    ${data.summary_html}
                                </div>
                            </div>
                            <a href="index.php" class="inline-block bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded text-sm">
                                View Dashboard →
                            </a>
                        </div>
                    `;
                } else {
                    subscribersResult.innerHTML = `
                        <div class="bg-red-50 border border-red-200 rounded-lg p-6">
                            <h3 class="text-red-800 font-semibold text-lg mb-2">❌ Import Failed</h3>
                            <p class="text-red-700 mb-4">${data.error}</p>
                            ${data.details ? `<pre class="text-xs text-red-600 bg-white p-2 rounded overflow-auto">${data.details}</pre>` : ''}
                            <button onclick="location.reload()" class="mt-4 bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded">
                                Try Again
                            </button>
                        </div>
                    `;
                }

                subscribersResult.classList.remove('hidden');

            } catch (error) {
                subscribersProgress.classList.add('hidden');
                subscribersResult.innerHTML = `
                    <div class="bg-red-50 border border-red-200 rounded-lg p-6">
                        <h3 class="text-red-800 font-semibold text-lg mb-2">❌ Upload Failed</h3>
                        <p class="text-red-700 mb-4">${error.message}</p>
                        <button onclick="location.reload()" class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded">
                            Try Again
                        </button>
                    </div>
                `;
                subscribersResult.classList.remove('hidden');
            } finally {
                subscribersUploadBtn.disabled = false;
                subscribersUploadBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        });

        // ============================================
        // VACATIONS UPLOAD FUNCTIONALITY
        // ============================================
        const vacationsForm = document.getElementById('vacationsForm');
        const vacationsUploadBtn = document.getElementById('vacationsUploadBtn');
        const vacationsFileInput = document.getElementById('vacationsFileInput');
        const vacationsFileInfo = document.getElementById('vacationsFileInfo');
        const vacationsProgress = document.getElementById('vacationsProgress');
        const vacationsProgressText = document.getElementById('vacationsProgressText');
        const vacationsResult = document.getElementById('vacationsResult');

        // Show file info when selected
        vacationsFileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                const sizeMB = (file.size / 1024 / 1024).toFixed(2);
                vacationsFileInfo.textContent = `Selected: ${file.name} (${sizeMB} MB)`;
                vacationsFileInfo.classList.remove('hidden');
            }
        });

        vacationsForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            // Show progress
            vacationsProgress.classList.remove('hidden');
            vacationsResult.classList.add('hidden');
            vacationsResult.innerHTML = '';
            vacationsUploadBtn.disabled = true;
            vacationsUploadBtn.classList.add('opacity-50', 'cursor-not-allowed');

            try {
                const formData = new FormData(vacationsForm);
                vacationsProgressText.textContent = 'Uploading file to server...';

                const response = await fetch('upload_vacations.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                vacationsProgress.classList.add('hidden');

                if (response.ok && data.success) {
                    const stats = data.stats;
                    let detailsHTML = `
                        <p class="font-semibold text-sm">File: ${data.filename}</p>
                        <ul class="mt-2 space-y-1 text-sm">
                            <li>• Total rows processed: ${stats.total_rows}</li>
                            <li>• Snapshots updated: ${stats.updated_rows}</li>
                            <li>• Rows skipped: ${stats.skipped_rows}</li>
                        </ul>
                    `;

                    if (Object.keys(stats.by_paper).length > 0) {
                        detailsHTML += '<p class="mt-3 font-semibold text-sm">By Paper:</p><ul class="mt-1 space-y-1 text-sm">';
                        for (const [paper, count] of Object.entries(stats.by_paper)) {
                            detailsHTML += `<li>• ${paper}: ${count} updated</li>`;
                        }
                        detailsHTML += '</ul>';
                    }

                    vacationsResult.innerHTML = `
                        <div class="bg-green-50 border border-green-200 rounded-lg p-6">
                            <h3 class="text-green-800 font-semibold text-lg mb-2">✅ Vacation Data Imported Successfully!</h3>
                            <div class="text-green-700">${detailsHTML}</div>
                            <a href="index.php" class="inline-block mt-4 bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded text-sm">
                                View Dashboard →
                            </a>
                        </div>
                    `;

                    // Reset form
                    vacationsForm.reset();
                    vacationsFileInfo.classList.add('hidden');
                } else {
                    vacationsResult.innerHTML = `
                        <div class="bg-red-50 border border-red-200 rounded-lg p-6">
                            <h3 class="text-red-800 font-semibold text-lg mb-2">❌ Import Failed</h3>
                            <p class="text-red-700">${data.error || 'Unknown error occurred'}</p>
                            <button onclick="location.reload()" class="mt-4 bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded">
                                Try Again
                            </button>
                        </div>
                    `;
                }

                vacationsResult.classList.remove('hidden');

            } catch (error) {
                vacationsProgress.classList.add('hidden');
                vacationsResult.innerHTML = `
                    <div class="bg-red-50 border border-red-200 rounded-lg p-6">
                        <h3 class="text-red-800 font-semibold text-lg mb-2">❌ Upload Failed</h3>
                        <p class="text-red-700">${error.message}</p>
                        <button onclick="location.reload()" class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded">
                            Try Again
                        </button>
                    </div>
                `;
                vacationsResult.classList.remove('hidden');
            } finally {
                vacationsUploadBtn.disabled = false;
                vacationsUploadBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        });
    </script>
</body>
</html>
