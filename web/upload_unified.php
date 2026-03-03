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
    <link rel="stylesheet" href="assets/output.css">
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
                    <button
                        class="tab-button"
                        data-tab="renewals"
                        onclick="switchTab('renewals')"
                    >
                        📊 Renewal Churns
                    </button>
                    <button
                        class="tab-button"
                        data-tab="newstarts"
                        onclick="switchTab('newstarts')"
                    >
                        🆕 New Starts
                    </button>
                    <button
                        class="tab-button"
                        data-tab="stopanalysis"
                        onclick="switchTab('stopanalysis')"
                    >
                        🛑 Stop Analysis
                    </button>
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

                    <!-- Renewal Churns Tab -->
                    <div id="renewals-tab" class="tab-content">
                        <h2 class="text-xl font-semibold mb-4">Upload Renewal Churn Data</h2>

                        <form id="renewalsForm" class="space-y-4">
                            <!-- Renewal Churn CSV File -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    📊 Renewal Churn Report CSV (required)
                                </label>
                                <input type="file"
                                       name="renewal_csv"
                                       id="renewalsFileInput"
                                       accept=".csv"
                                       required
                                       class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none p-2">
                                <p class="text-xs text-gray-500 mt-1">
                                    Format: Renewal Churn Report by Issue from Newzware
                                </p>
                                <p id="renewalsFileInfo" class="text-xs text-blue-600 mt-1 hidden"></p>
                            </div>

                            <!-- Submit Button -->
                            <div class="pt-4">
                                <button type="submit" id="renewalsUploadBtn"
                                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200">
                                    🚀 Upload and Process Renewal Churn Data
                                </button>
                            </div>
                        </form>

                        <!-- Progress/Results -->
                        <div id="renewalsProgress" class="mt-6 hidden">
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <div class="flex items-center">
                                    <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600 mr-3"></div>
                                    <span class="text-blue-800 font-medium" id="renewalsProgressText">Processing file...</span>
                                </div>
                            </div>
                        </div>

                        <div id="renewalsResult" class="mt-6 hidden">
                            <!-- Success/error message will appear here -->
                        </div>

                        <!-- Instructions -->
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <h3 class="text-lg font-semibold mb-3">📋 How to Upload Renewal Churn Data</h3>
                            <ol class="list-decimal list-inside space-y-2 text-gray-700 text-sm">
                                <li>Run "Renewal Churn Report by Issue" query in Newzware</li>
                                <li>Export results as CSV file</li>
                                <li>Click "Choose File" above and select the CSV file</li>
                                <li>Click "Upload and Process Renewal Churn Data"</li>
                                <li>Review the import summary showing events and daily summaries</li>
                            </ol>

                            <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded text-sm">
                                <p class="text-blue-800 font-semibold mb-1">📊 Renewal Churn Tracking:</p>
                                <ul class="text-blue-700 space-y-1 list-disc list-inside text-xs">
                                    <li>Tracks subscription renewal and churn events by publication</li>
                                    <li>Categorizes events by subscription type (REGULAR, MONTHLY, etc.)</li>
                                    <li>Generates daily summaries for trend analysis</li>
                                    <li>Links to churn dashboard for detailed analytics</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- New Starts Tab -->
                    <div id="newstarts-tab" class="tab-content">
                        <h2 class="text-xl font-semibold mb-4">Upload New Subscription Starts</h2>

                        <form id="newstartsForm" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    🆕 New Subscription Starts CSV (required)
                                </label>
                                <input type="file"
                                       name="newstarts_csv"
                                       id="newstartsFileInput"
                                       accept=".csv"
                                       required
                                       class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none p-2">
                                <p class="text-xs text-gray-500 mt-1">
                                    Format: NewSubscriptionStarts<strong>YYYYMMDDHHMMSS</strong>.csv from Newzware
                                </p>
                                <p id="newstartsFileInfo" class="text-xs text-blue-600 mt-1 hidden"></p>
                            </div>

                            <div class="pt-4">
                                <button type="submit" id="newstartsUploadBtn"
                                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200">
                                    🚀 Upload and Process New Starts Data
                                </button>
                            </div>
                        </form>

                        <!-- Progress/Results -->
                        <div id="newstartsProgress" class="mt-6 hidden">
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <div class="flex items-center">
                                    <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600 mr-3"></div>
                                    <span class="text-blue-800 font-medium" id="newstartsProgressText">Processing file...</span>
                                </div>
                            </div>
                        </div>

                        <div id="newstartsResult" class="mt-6 hidden"></div>

                        <!-- Instructions -->
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <h3 class="text-lg font-semibold mb-3">📋 How to Upload New Starts Data</h3>
                            <ol class="list-decimal list-inside space-y-2 text-gray-700 text-sm">
                                <li>Run "New Subscription Starts" report in Newzware (newstarts macro)</li>
                                <li>Settings: All editions, Include Restarts: N, Transaction Types: S</li>
                                <li>Export results as CSV file</li>
                                <li>Click "Choose File" above and select the CSV file</li>
                                <li>Click "Upload and Process New Starts Data"</li>
                                <li>Review the import summary showing new vs restart classifications</li>
                            </ol>

                            <div class="mt-4 p-3 bg-purple-50 border border-purple-200 rounded text-sm">
                                <p class="text-purple-800 font-semibold mb-1">🆕 New Starts Classification:</p>
                                <ul class="text-purple-700 space-y-1 list-disc list-inside text-xs">
                                    <li><strong>Truly New:</strong> Subscribers with no prior renewal/expiration history</li>
                                    <li><strong>Restarts:</strong> Subscribers who previously expired and came back</li>
                                    <li>Cross-references against renewal churn data automatically</li>
                                    <li>Feeds into BU Trend Detail chart for growth analysis</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Stop Analysis Tab -->
                    <div id="stopanalysis-tab" class="tab-content">
                        <h2 class="text-xl font-semibold mb-4">Upload Stop Analysis Report</h2>

                        <form id="stopanalysisForm" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    🛑 Stop Analysis Report CSV (required)
                                </label>
                                <input type="file"
                                       name="stop_analysis_csv"
                                       id="stopanalysisFileInput"
                                       accept=".csv"
                                       required
                                       class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none p-2">
                                <p class="text-xs text-gray-500 mt-1">
                                    Format: StopAnalysisReport<strong>YYYYMMDDHHMMSS</strong>.csv from Newzware
                                </p>
                                <p id="stopanalysisFileInfo" class="text-xs text-blue-600 mt-1 hidden"></p>
                            </div>

                            <div class="pt-4">
                                <button type="submit" id="stopanalysisUploadBtn"
                                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200">
                                    🚀 Upload and Process Stop Analysis Data
                                </button>
                            </div>
                        </form>

                        <!-- Progress/Results -->
                        <div id="stopanalysisProgress" class="mt-6 hidden">
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <div class="flex items-center">
                                    <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600 mr-3"></div>
                                    <span class="text-blue-800 font-medium" id="stopanalysisProgressText">Processing file...</span>
                                </div>
                            </div>
                        </div>

                        <div id="stopanalysisResult" class="mt-6 hidden"></div>

                        <!-- Instructions -->
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <h3 class="text-lg font-semibold mb-3">📋 How to Upload Stop Analysis Data</h3>
                            <ol class="list-decimal list-inside space-y-2 text-gray-700 text-sm">
                                <li>Run "Stop Analysis Report" in Newzware (recentstopsfordashboard macro)</li>
                                <li>Settings: All editions, date range covering recent weeks</li>
                                <li>Export results as CSV file</li>
                                <li>Click "Choose File" above and select the CSV file</li>
                                <li>Click "Upload and Process Stop Analysis Data"</li>
                                <li>Review the import summary showing stops by publication and reason</li>
                            </ol>

                            <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded text-sm">
                                <p class="text-red-800 font-semibold mb-1">🛑 Stop Analysis Data:</p>
                                <ul class="text-red-700 space-y-1 list-disc list-inside text-xs">
                                    <li><strong>Per-subscriber detail:</strong> Name, address, phone, email for each stopped subscriber</li>
                                    <li><strong>Stop reasons:</strong> AUTO EXPIRE, NON-PAY, COST, DECEASED, and more</li>
                                    <li><strong>Remarks:</strong> Customer service notes explaining why they stopped</li>
                                    <li>Click on red Stops bars in BU Trend Detail to drill down into individual stops</li>
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
        // Check for hash in URL and switch to that tab on page load
        window.addEventListener("DOMContentLoaded", function() {
            const hash = window.location.hash.substring(1);
            if (hash && ["subscribers", "vacations", "renewals", "newstarts", "stopanalysis"].includes(hash)) {
                switchTab(hash);
            }
        });
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

        // ============================================
        // RENEWAL CHURNS UPLOAD FUNCTIONALITY
        // ============================================
        const renewalsForm = document.getElementById("renewalsForm");
        const renewalsUploadBtn = document.getElementById("renewalsUploadBtn");
        const renewalsFileInput = document.getElementById("renewalsFileInput");
        const renewalsFileInfo = document.getElementById("renewalsFileInfo");
        const renewalsProgress = document.getElementById("renewalsProgress");
        const renewalsProgressText = document.getElementById("renewalsProgressText");
        const renewalsResult = document.getElementById("renewalsResult");

        renewalsFileInput.addEventListener("change", (e) => {
            const file = e.target.files[0];
            if (file) {
                const sizeMB = (file.size / 1024 / 1024).toFixed(2);
                renewalsFileInfo.textContent = "Selected: " + file.name + " (" + sizeMB + " MB)";
                renewalsFileInfo.classList.remove("hidden");
            }
        });

        renewalsForm.addEventListener("submit", async (e) => {
            e.preventDefault();
            renewalsProgress.classList.remove("hidden");
            renewalsResult.classList.add("hidden");
            renewalsUploadBtn.disabled = true;

            try {
                const formData = new FormData(renewalsForm);
                const response = await fetch("upload_renewals.php", {
                    method: "POST",
                    body: formData
                });
                const data = await response.json();
                renewalsProgress.classList.add("hidden");

                if (data.success) {
                    const msg = "Imported: " + data.events_imported + " events, " + data.summaries_imported + " summaries";
                    renewalsResult.textContent = "✅ " + msg;
                    renewalsResult.className = "mt-6 p-4 bg-green-50 border border-green-200 rounded text-green-800";
                    renewalsForm.reset();
                    renewalsFileInfo.classList.add("hidden");
                } else {
                    renewalsResult.textContent = "❌ Error: " + (data.error || "Unknown error");
                    renewalsResult.className = "mt-6 p-4 bg-red-50 border border-red-200 rounded text-red-800";
                }
                renewalsResult.classList.remove("hidden");
            } catch (error) {
                renewalsProgress.classList.add("hidden");
                renewalsResult.textContent = "❌ Upload failed: " + error.message;
                renewalsResult.className = "mt-6 p-4 bg-red-50 border border-red-200 rounded text-red-800";
                renewalsResult.classList.remove("hidden");
            } finally {
                renewalsUploadBtn.disabled = false;
            }
        });

        // ============================================
        // NEW STARTS UPLOAD FUNCTIONALITY
        // ============================================
        const newstartsForm = document.getElementById("newstartsForm");
        const newstartsUploadBtn = document.getElementById("newstartsUploadBtn");
        const newstartsFileInput = document.getElementById("newstartsFileInput");
        const newstartsFileInfo = document.getElementById("newstartsFileInfo");
        const newstartsProgress = document.getElementById("newstartsProgress");
        const newstartsProgressText = document.getElementById("newstartsProgressText");
        const newstartsResult = document.getElementById("newstartsResult");

        newstartsFileInput.addEventListener("change", (e) => {
            const file = e.target.files[0];
            if (file) {
                const sizeMB = (file.size / 1024 / 1024).toFixed(2);
                newstartsFileInfo.textContent = "Selected: " + file.name + " (" + sizeMB + " MB)";
                newstartsFileInfo.classList.remove("hidden");
            }
        });

        newstartsForm.addEventListener("submit", async (e) => {
            e.preventDefault();
            newstartsProgress.classList.remove("hidden");
            newstartsResult.classList.add("hidden");
            newstartsResult.textContent = "";
            newstartsUploadBtn.disabled = true;
            newstartsUploadBtn.classList.add("opacity-50", "cursor-not-allowed");

            try {
                const formData = new FormData(newstartsForm);
                newstartsProgressText.textContent = "Uploading and cross-referencing...";

                const response = await fetch("upload_new_starts.php", {
                    method: "POST",
                    body: formData
                });
                const data = await response.json();
                newstartsProgress.classList.add("hidden");

                if (data.success) {
                    renderNewStartsSuccess(data);
                    newstartsForm.reset();
                    newstartsFileInfo.classList.add("hidden");
                } else {
                    newstartsResult.textContent = "❌ Error: " + (data.error || "Unknown error");
                    newstartsResult.className = "mt-6 p-4 bg-red-50 border border-red-200 rounded text-red-800";
                }
                newstartsResult.classList.remove("hidden");
            } catch (error) {
                newstartsProgress.classList.add("hidden");
                newstartsResult.textContent = "❌ Upload failed: " + error.message;
                newstartsResult.className = "mt-6 p-4 bg-red-50 border border-red-200 rounded text-red-800";
                newstartsResult.classList.remove("hidden");
            } finally {
                newstartsUploadBtn.disabled = false;
                newstartsUploadBtn.classList.remove("opacity-50", "cursor-not-allowed");
            }
        });

        // ============================================
        // STOP ANALYSIS UPLOAD FUNCTIONALITY
        // ============================================
        const stopanalysisForm = document.getElementById("stopanalysisForm");
        const stopanalysisUploadBtn = document.getElementById("stopanalysisUploadBtn");
        const stopanalysisFileInput = document.getElementById("stopanalysisFileInput");
        const stopanalysisFileInfo = document.getElementById("stopanalysisFileInfo");
        const stopanalysisProgress = document.getElementById("stopanalysisProgress");
        const stopanalysisProgressText = document.getElementById("stopanalysisProgressText");
        const stopanalysisResult = document.getElementById("stopanalysisResult");

        stopanalysisFileInput.addEventListener("change", (e) => {
            const file = e.target.files[0];
            if (file) {
                const sizeMB = (file.size / 1024 / 1024).toFixed(2);
                stopanalysisFileInfo.textContent = "Selected: " + file.name + " (" + sizeMB + " MB)";
                stopanalysisFileInfo.classList.remove("hidden");
            }
        });

        stopanalysisForm.addEventListener("submit", async (e) => {
            e.preventDefault();
            stopanalysisProgress.classList.remove("hidden");
            stopanalysisResult.classList.add("hidden");
            stopanalysisResult.textContent = "";
            stopanalysisUploadBtn.disabled = true;
            stopanalysisUploadBtn.classList.add("opacity-50", "cursor-not-allowed");

            try {
                const formData = new FormData(stopanalysisForm);
                stopanalysisProgressText.textContent = "Uploading and processing stop data...";

                const response = await fetch("upload_stop_analysis.php", {
                    method: "POST",
                    body: formData
                });
                const data = await response.json();
                stopanalysisProgress.classList.add("hidden");

                if (data.success) {
                    renderStopAnalysisSuccess(data);
                    stopanalysisForm.reset();
                    stopanalysisFileInfo.classList.add("hidden");
                } else {
                    stopanalysisResult.textContent = "❌ Error: " + (data.error || "Unknown error");
                    stopanalysisResult.className = "mt-6 p-4 bg-red-50 border border-red-200 rounded text-red-800";
                }
                stopanalysisResult.classList.remove("hidden");
            } catch (error) {
                stopanalysisProgress.classList.add("hidden");
                stopanalysisResult.textContent = "❌ Upload failed: " + error.message;
                stopanalysisResult.className = "mt-6 p-4 bg-red-50 border border-red-200 rounded text-red-800";
                stopanalysisResult.classList.remove("hidden");
            } finally {
                stopanalysisUploadBtn.disabled = false;
                stopanalysisUploadBtn.classList.remove("opacity-50", "cursor-not-allowed");
            }
        });

        function renderStopAnalysisSuccess(data) {
            const container = document.createElement("div");
            container.className = "bg-green-50 border border-green-200 rounded-lg p-6";

            const title = document.createElement("h3");
            title.className = "text-green-800 font-semibold text-lg mb-2";
            title.textContent = "✅ Stop Analysis Data Imported!";
            container.appendChild(title);

            const info = document.createElement("div");
            info.className = "text-green-700 space-y-1 mb-4 text-sm";
            const fields = [
                ["Date Range", data.date_range],
                ["Total Processed", data.total_processed],
                ["New Records", data.new_records],
                ["Updated Records", data.updated_records],
                ["Processing Time", data.processing_time]
            ];
            fields.forEach(([label, value]) => {
                const p = document.createElement("p");
                const strong = document.createElement("strong");
                strong.textContent = label + ": ";
                p.appendChild(strong);
                p.appendChild(document.createTextNode(value));
                info.appendChild(p);
            });
            container.appendChild(info);

            const link = document.createElement("a");
            link.href = "index.php";
            link.className = "inline-block bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded text-sm";
            link.textContent = "View Dashboard →";
            container.appendChild(link);

            stopanalysisResult.textContent = "";
            stopanalysisResult.appendChild(container);
            stopanalysisResult.className = "mt-6";
        }

        function renderNewStartsSuccess(data) {
            // Build result display using DOM methods (safe, no innerHTML with untrusted content)
            const container = document.createElement("div");
            container.className = "bg-green-50 border border-green-200 rounded-lg p-6";

            const title = document.createElement("h3");
            title.className = "text-green-800 font-semibold text-lg mb-2";
            title.textContent = "✅ New Starts Imported!";
            container.appendChild(title);

            const info = document.createElement("div");
            info.className = "text-green-700 space-y-1 mb-4 text-sm";
            const fields = [
                ["Date Range", data.date_range],
                ["Total Processed", data.total_processed],
                ["New Records", data.new_records],
                ["Duplicates Updated", data.updated_records],
                ["Processing Time", data.processing_time]
            ];
            fields.forEach(([label, value]) => {
                const p = document.createElement("p");
                const strong = document.createElement("strong");
                strong.textContent = label + ": ";
                p.appendChild(strong);
                p.appendChild(document.createTextNode(value));
                info.appendChild(p);
            });
            container.appendChild(info);

            // Classification boxes
            const classGrid = document.createElement("div");
            classGrid.className = "grid grid-cols-2 gap-4 text-sm mb-4";

            const newBox = document.createElement("div");
            newBox.className = "bg-purple-50 rounded p-3 text-center";
            const newNum = document.createElement("div");
            newNum.className = "text-2xl font-bold text-purple-700";
            newNum.textContent = data.truly_new;
            const newLabel = document.createElement("div");
            newLabel.className = "text-purple-600 text-xs";
            newLabel.textContent = "Truly New Subscribers";
            newBox.appendChild(newNum);
            newBox.appendChild(newLabel);
            classGrid.appendChild(newBox);

            const restartBox = document.createElement("div");
            restartBox.className = "bg-orange-50 rounded p-3 text-center";
            const restartNum = document.createElement("div");
            restartNum.className = "text-2xl font-bold text-orange-700";
            restartNum.textContent = data.restarts;
            const restartLabel = document.createElement("div");
            restartLabel.className = "text-orange-600 text-xs";
            restartLabel.textContent = "Restarts / Overlaps";
            restartBox.appendChild(restartNum);
            restartBox.appendChild(restartLabel);
            classGrid.appendChild(restartBox);

            container.appendChild(classGrid);

            const link = document.createElement("a");
            link.href = "index.php";
            link.className = "inline-block bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded text-sm";
            link.textContent = "View Dashboard →";
            container.appendChild(link);

            newstartsResult.textContent = "";
            newstartsResult.appendChild(container);
            newstartsResult.className = "mt-6";
        }
    </script>
</body>
</html>
