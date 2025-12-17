<?php
require_once 'auth_check.php';
require_once 'version.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup & Restore - Circulation Dashboard</title>
    <link rel="stylesheet" href="assets/output.css?v=20251206">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        * { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
    </style>
</head>
<body class="bg-gray-50">
    <header class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <a href="settings.php" class="text-gray-600 hover:text-gray-900 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                    </a>
                    <h1 class="text-2xl font-bold text-gray-900">Backup & Restore</h1>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-sm font-medium text-gray-600 mb-2">Next Backup</h3>
                <p id="nextBackup" class="text-2xl font-bold text-gray-900">Loading...</p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-sm font-medium text-gray-600 mb-2">Last Backup</h3>
                <p id="lastBackup" class="text-2xl font-bold text-gray-900">Loading...</p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-sm font-medium text-gray-600 mb-2">Free Space</h3>
                <p id="diskSpace" class="text-2xl font-bold text-gray-900">Loading...</p>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Available Backups</h2>
            <div id="backupsList" class="space-y-4"></div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Restore System</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select Backup</label>
                    <select id="restoreBackupNumber" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="">-- Choose Backup --</option>
                        <option value="1">Backup 1 (Most Recent)</option>
                        <option value="2">Backup 2 (Middle)</option>
                        <option value="3">Backup 3 (Oldest)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Restore Type</label>
                    <select id="restoreType" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="">-- Choose Type --</option>
                        <option value="database">Database Only</option>
                        <option value="code">Code Only</option>
                        <option value="full">Full System</option>
                    </select>
                </div>
            </div>
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Type CONFIRM to proceed</label>
                <input type="text" id="restoreConfirmation" class="w-full px-3 py-2 border rounded-lg" placeholder="Type CONFIRM">
            </div>
            <button onclick="executeRestore()" id="restoreButton" class="w-full bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-lg font-medium">
                Restore System
            </button>
            <div id="restoreStatus" class="mt-4 hidden"></div>
        </div>
    </main>

    <script>
    async function loadBackupStatus() {
        try {
            const response = await fetch('/api/api_backup_status.php');
            const data = await response.json();
            document.getElementById('nextBackup').textContent = data.next_backup || 'N/A';
            document.getElementById('lastBackup').textContent = data.last_backup || 'N/A';
            document.getElementById('diskSpace').textContent = data.disk_space_gb ? data.disk_space_gb + ' GB' : 'N/A';
            renderBackups(data.backups || []);
        } catch (error) {
            console.error('Failed to load:', error);
        }
    }

    function renderBackups(backups) {
        const container = document.getElementById('backupsList');
        container.textContent = '';
        if (backups.length === 0) {
            const p = document.createElement('p');
            p.className = 'text-gray-500';
            p.textContent = 'No backups available';
            container.appendChild(p);
            return;
        }
        backups.forEach(backup => {
            const div = document.createElement('div');
            div.className = 'flex items-center justify-between p-4 border rounded-lg';
            const left = document.createElement('div');
            const h3 = document.createElement('h3');
            h3.className = 'font-semibold';
            h3.textContent = backup.name;
            const p1 = document.createElement('p');
            p1.className = 'text-sm text-gray-600';
            p1.textContent = backup.timestamp;
            const p2 = document.createElement('p');
            p2.className = 'text-xs text-gray-500';
            p2.textContent = backup.age;
            left.appendChild(h3);
            left.appendChild(p1);
            left.appendChild(p2);
            const right = document.createElement('div');
            right.className = 'text-right';
            const p3 = document.createElement('p');
            p3.className = 'text-sm font-medium';
            p3.textContent = backup.total_size;
            const p4 = document.createElement('p');
            p4.className = 'text-xs text-gray-500';
            p4.textContent = 'DB: ' + backup.database_size + ' | Code: ' + backup.code_size;
            right.appendChild(p3);
            right.appendChild(p4);
            div.appendChild(left);
            div.appendChild(right);
            container.appendChild(div);
        });
    }

    async function executeRestore() {
        const backupNumber = document.getElementById('restoreBackupNumber').value;
        const restoreType = document.getElementById('restoreType').value;
        const confirmation = document.getElementById('restoreConfirmation').value;
        if (!backupNumber || !restoreType || confirmation !== 'CONFIRM') {
            alert('Please fill all fields and type CONFIRM exactly');
            return;
        }
        if (!confirm('Are you sure? This cannot be undone!')) return;
        const button = document.getElementById('restoreButton');
        button.disabled = true;
        try {
            const response = await fetch('/api/api_backup_restore.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ backup_number: backupNumber, restore_type: restoreType, confirmation })
            });
            const data = await response.json();
            if (data.status === 'success') {
                alert(data.message + '\n\nPage will reload...');
                setTimeout(() => location.reload(), 3000);
            } else {
                alert('Error: ' + data.message);
                button.disabled = false;
            }
        } catch (error) {
            alert('Error: ' + error.message);
            button.disabled = false;
        }
    }
    loadBackupStatus();
    </script>
</body>
</html>
