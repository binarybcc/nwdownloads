<?php
/**
 * Backup Restore API - 6-Layer Security Model
 */
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    exit;
}

// Layer 1: Whitelist backup number
function validateBackupNumber($n) {
    if (!in_array($n, ['1', '2', '3'], true)) {
        throw new Exception('Invalid backup number');
    }
    return $n;
}

// Layer 2: Whitelist restore type
function validateRestoreType($t) {
    if (!in_array($t, ['database', 'code', 'full'], true)) {
        throw new Exception('Invalid restore type');
    }
    return $t;
}

// Layer 3: Confirmation validation
function validateConfirmation($c) {
    if ($c !== 'CONFIRM') {
        throw new Exception('Must type CONFIRM exactly');
    }
    return true;
}

// Layer 4: Array mapping (no concatenation)
function getScriptPath($type) {
    $map = [
        'database' => '/volume1/homes/it/scripts/restore-database.sh',
        'code' => '/volume1/homes/it/scripts/restore-code.sh',
        'full' => '/volume1/homes/it/scripts/restore-full.sh'
    ];
    return $map[$type] ?? null;
}

// Execute with Layers 5 & 6
function executeRestore($num, $type) {
    $script = getScriptPath($type);
    if (!$script) throw new Exception('Script not found');
    
    // Layer 5: escapeshellarg for params
    $safe_num = escapeshellarg($num);
    // Layer 6: escapeshellcmd for script
    $safe_script = escapeshellcmd($script);
    
    $command = "{$safe_script} {$safe_num} 2>&1";
    $output = [];
    $code = 0;
    exec($command, $output, $code);
    
    return [
        'success' => $code === 0,
        'output' => implode("\n", $output),
        'code' => $code
    ];
}

// Audit logging
function auditLog($num, $type, $conf, $success) {
    $log = '/volume1/homes/newzware/backup/logs/restore-audit.log';
    $ts = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $status = $success ? 'SUCCESS' : 'FAILED';
    $entry = "[{$ts}] IP:{$ip} Type:{$type} Backup:{$num} Status:{$status}\n";
    file_put_contents($log, $entry, FILE_APPEND | LOCK_EX);
}

// Main execution
try {
    $num = validateBackupNumber($input['backup_number'] ?? null);
    $type = validateRestoreType($input['restore_type'] ?? null);
    validateConfirmation($input['confirmation'] ?? null);
    
    $result = executeRestore($num, $type);
    auditLog($num, $type, 'CONFIRM', $result['success']);
    
    if ($result['success']) {
        echo json_encode([
            'status' => 'success',
            'message' => ucfirst($type) . ' restore completed',
            'output' => $result['output']
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Restore failed',
            'output' => $result['output']
        ]);
    }
} catch (Exception $e) {
    auditLog($input['backup_number'] ?? 'invalid', 
             $input['restore_type'] ?? 'invalid',
             $input['confirmation'] ?? 'invalid',
             false);
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
