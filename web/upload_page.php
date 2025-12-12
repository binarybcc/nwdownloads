<?php

/**
 * Upload Page - Requires Authentication
 *
 * This page displays the CSV upload form for importing weekly circulation data.
 * Users must be authenticated before accessing this page.
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
</head>
<body class="bg-gray-50">
    <div class="min-h-screen p-8">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <h1 class="text-3xl font-bold text-gray-900">📊 Weekly Data Upload</h1>
                <p class="text-gray-600 mt-2">Upload Newzware All Subscriber Report to update dashboard data</p>
                <a href="index.php" class="text-blue-600 hover:text-blue-700 text-sm mt-2 inline-block">← Back to Dashboard</a>
            </div>

            <!-- Upload Form -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-xl font-semibold mb-4">Upload All Subscriber Report</h2>

                <form id="uploadForm" class="space-y-4">
                    <!-- All Subscriber Report File -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            📄 All Subscriber Report CSV (required)
                        </label>
                        <input type="file"
                               name="allsubscriber"
                               id="fileInput"
                               accept=".csv"
                               required
                               class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none p-2">
                        <p class="text-xs text-gray-500 mt-1">
                            Format: AllSubscriberReport<strong>YYYYMMDDHHMMSS</strong>.csv
                        </p>
                        <p id="fileInfo" class="text-xs text-blue-600 mt-1 hidden"></p>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-4">
                        <button type="submit" id="uploadBtn"
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-200">
                            🚀 Upload and Process Data
                        </button>
                    </div>
                </form>

                <!-- Progress/Results -->
                <div id="progress" class="mt-6 hidden">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600 mr-3"></div>
                            <span class="text-blue-800 font-medium" id="progressText">Processing file...</span>
                        </div>
                        <div class="mt-2 text-sm text-blue-600" id="progressDetails"></div>
                    </div>
                </div>

                <div id="result" class="mt-6 hidden">
                    <!-- Success/error message will appear here -->
                </div>
            </div>

            <!-- Instructions -->
            <div class="bg-white rounded-lg shadow-sm p-6 mt-6">
                <h3 class="text-lg font-semibold mb-3">📋 How to Upload Weekly Data</h3>
                <ol class="list-decimal list-inside space-y-2 text-gray-700">
                    <li>Run "All Subscriber Report" query in Newzware Ad-Hoc Query Builder</li>
                    <li>Export results as CSV (saves as <code class="bg-gray-100 px-1 py-0.5 rounded text-sm">AllSubscriberReportYYYYMMDDHHMMSS.csv</code>)</li>
                    <li>Click "Choose File" above and select the CSV file</li>
                    <li>Click "Upload and Process Data"</li>
                    <li>Wait for processing (typically 10-30 seconds for ~8,000 rows)</li>
                    <li>Review the import summary</li>
                    <li>Dashboard will automatically include the new week's data</li>
                </ol>

                <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded">
                    <p class="text-sm text-blue-800 font-semibold mb-2">
                        📊 How Upsert Works:
                    </p>
                    <ul class="text-sm text-blue-700 space-y-1 list-disc list-inside">
                        <li><strong>New data:</strong> Adds new weekly snapshots automatically</li>
                        <li><strong>Existing data:</strong> Updates with latest subscriber counts (if Newzware corrects historical data)</li>
                        <li><strong>Date filter:</strong> Only imports data from January 1, 2025 onwards</li>
                        <li><strong>Safe:</strong> Never deletes existing data, only adds or updates</li>
                    </ul>
                </div>

                <div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded">
                    <p class="text-sm text-yellow-800">
                        <strong>⚠️ Data Range:</strong> Only data from <strong>January 1, 2025 onwards</strong> will be imported. Earlier dates are filtered out automatically due to the rate system change.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        const form = document.getElementById('uploadForm');
        const uploadBtn = document.getElementById('uploadBtn');
        const fileInput = document.getElementById('fileInput');
        const fileInfo = document.getElementById('fileInfo');
        const progress = document.getElementById('progress');
        const progressText = document.getElementById('progressText');
        const progressDetails = document.getElementById('progressDetails');
        const result = document.getElementById('result');

        // Show file info when selected
        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                const sizeMB = (file.size / 1024 / 1024).toFixed(2);
                fileInfo.textContent = `Selected: ${file.name} (${sizeMB} MB)`;
                fileInfo.classList.remove('hidden');
            }
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            // Show progress
            progress.classList.remove('hidden');
            result.classList.add('hidden');
            result.innerHTML = '';
            uploadBtn.disabled = true;
            uploadBtn.classList.add('opacity-50', 'cursor-not-allowed');

            try {
                // Create FormData with file
                const formData = new FormData(form);

                progressText.textContent = 'Uploading file to server...';
                progressDetails.textContent = '';

                // Upload file
                const response = await fetch('upload.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                // Hide progress
                progress.classList.add('hidden');

                if (data.success) {
                    // Show success
                    result.innerHTML = `
                        <div class="bg-green-50 border border-green-200 rounded-lg p-6">
                            <h3 class="text-green-800 font-semibold text-lg mb-2">✅ Import Successful!</h3>
                            <div class="text-green-700 space-y-1 mb-4">
                                <p><strong>Date Range:</strong> ${data.date_range}</p>
                                <p><strong>New Records Added:</strong> ${data.new_records}</p>
                                <p><strong>Existing Records Updated:</strong> ${data.updated_records}</p>
                                <p><strong>Total Records Processed:</strong> ${data.total_processed}</p>
                                <p><strong>Processing Time:</strong> ${data.processing_time}</p>
                            </div>
                            <div class="bg-white rounded p-4 mb-4">
                                <h4 class="font-semibold mb-2">📊 Summary by Business Unit:</h4>
                                <div class="space-y-2 text-sm">
                                    ${data.summary_html}
                                </div>
                            </div>
                            <a href="index.php" class="inline-block bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded">
                                View Dashboard →
                            </a>
                        </div>
                    `;
                } else {
                    // Show error
                    result.innerHTML = `
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

                result.classList.remove('hidden');

            } catch (error) {
                progress.classList.add('hidden');
                result.innerHTML = `
                    <div class="bg-red-50 border border-red-200 rounded-lg p-6">
                        <h3 class="text-red-800 font-semibold text-lg mb-2">❌ Upload Failed</h3>
                        <p class="text-red-700 mb-4">${error.message}</p>
                        <button onclick="location.reload()" class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded">
                            Try Again
                        </button>
                    </div>
                `;
                result.classList.remove('hidden');
            } finally {
                uploadBtn.disabled = false;
                uploadBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        });
    </script>
</body>
</html>
