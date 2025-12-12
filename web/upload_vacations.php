<?php

/**
 * Vacation Data Upload Handler
 * Processes "Subscribers On Vacation" CSV exports from Newzware
 * Updates vacation_start, vacation_end, and vacation_weeks in subscriber_snapshots
 */

header('Content-Type: application/json');
require_once 'config.php';
// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded or upload error occurred']);
    exit;
}

// Validate file size (10MB limit)
if ($_FILES['csv_file']['size'] > 10 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['error' => 'File too large (max 10MB)']);
    exit;
}

$file = $_FILES['csv_file'];
$filename = basename($file['name']);
// Database configuration
$db_config = [
    'host' => getenv('DB_HOST') ?: 'database',
    'port' => getenv('DB_PORT') ?: 3306,
    'database' => getenv('DB_NAME') ?: 'circulation_dashboard',
    'username' => getenv('DB_USER') ?: 'circ_dash',
    'password' => getenv('DB_PASSWORD') ?: 'Barnaby358@Jones!',
];
try {
// Connect to database
    $dsn = "mysql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['database']};charset=utf8mb4";
    error_log("Vacation upload: Attempting DB connection with DSN: $dsn");
    $db = new PDO($dsn, $db_config['username'], $db_config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    error_log("Vacation upload: DB connection successful");
// Step 1: Save raw CSV to raw_uploads table (source of truth)
    $raw_csv_data = file_get_contents($file['tmp_name']);
    if ($raw_csv_data === false) {
        throw new Exception('Could not read uploaded file');
    }

    $file_size = filesize($file['tmp_name']);
    $file_hash = hash('sha256', $raw_csv_data);
// Save to raw_uploads (snapshot_date determined later)
    $stmt = $db->prepare("
        INSERT INTO raw_uploads (
            filename, file_size, file_hash, snapshot_date,
            row_count, subscriber_count, raw_csv_data,
            processing_status, uploaded_by, ip_address, user_agent
        ) VALUES (
            :filename, :file_size, :file_hash, '1970-01-01',
            0, 0, :raw_csv_data,
            'pending', 'web_interface_vacation', :ip, :user_agent
        )
    ");
    $stmt->execute([
        'filename' => $filename,
        'file_size' => $file_size,
        'file_hash' => $file_hash,
        'raw_csv_data' => $raw_csv_data,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
    $upload_id = $db->lastInsertId();
// Step 2: Process CSV as normal
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        throw new Exception('Could not read uploaded file');
    }

    $stats = [
        'total_rows' => 0,
        'skipped_rows' => 0,
        'updated_rows' => 0,
        'errors' => [],
        'by_paper' => []
    ];
    $lineNumber = 0;
    $headerFound = false;
    $headerRow = null;
    while (($row = fgetcsv($handle)) !== false) {
        $lineNumber++;
    // Skip empty rows
        if (empty(array_filter($row))) {
            continue;
        }

        // Look for header row (contains "SUB NUM" and "VAC BEG.")
        if (!$headerFound) {
            $firstCell = trim($row[0] ?? '');
            if (stripos($firstCell, 'SUB NUM') !== false) {
                $headerFound = true;
                $headerRow = array_map('trim', $row);
            // Validate required columns
                $requiredColumns = ['SUB NUM', 'VAC BEG.', 'VAC END', 'Ed'];
                foreach ($requiredColumns as $col) {
                    if (!in_array($col, $headerRow)) {
                            throw new Exception("Missing required column: $col. This doesn't appear to be a Subscribers On Vacation report.");
                    }
                }
                continue;
            }
            continue;
// Skip header rows
        }

        // Skip separator rows (all dashes)
        if (isset($row[0]) && preg_match('/^-+$/', trim($row[0]))) {
            continue;
        }

        // Skip footer rows (starts with "Total Vacations" or "Report")
        $firstCell = trim($row[0] ?? '');
        if (
            stripos($firstCell, 'Total Vacations') !== false ||
            stripos($firstCell, 'Report') !== false ||
            empty($firstCell)
        ) {
            break;
// End of data
        }

        $stats['total_rows']++;
    // Extract data using header positions
        $subNum = trim($row[array_search('SUB NUM', $headerRow)] ?? '');
        $vacBeg = trim($row[array_search('VAC BEG.', $headerRow)] ?? '');
        $vacEnd = trim($row[array_search('VAC END', $headerRow)] ?? '');
        $paperCode = trim($row[array_search('Ed', $headerRow)] ?? '');
    // Validate required fields
        if (empty($subNum) || empty($vacBeg) || empty($vacEnd) || empty($paperCode)) {
            $stats['skipped_rows']++;
            $stats['errors'][] = "Line $lineNumber: Missing required fields (sub_num, dates, or paper)";
            continue;
        }

        // Parse dates (MM/DD/YY format)
        // Handle 2-digit year: assume 00-50 = 2000-2050, 51-99 = 1951-1999
        $vacStart = parseNewzwareDate($vacBeg);
        $vacEnd = parseNewzwareDate($vacEnd);
        if (!$vacStart || !$vacEnd) {
            $stats['skipped_rows']++;
            $stats['errors'][] = "Line $lineNumber: Invalid date format (sub: $subNum, start: $vacBeg, end: $vacEnd)";
            continue;
        }

        // Validate dates (technical checks only - no business logic)
        if ($vacEnd < $vacStart) {
            $stats['skipped_rows']++;
            $stats['errors'][] = "Line $lineNumber: End date before start date (sub: $subNum)";
            continue;
        }

        // Calculate weeks on vacation (calendar weeks)
        $vacationDays = $vacStart->diff($vacEnd)->days;
        $vacationWeeks = round($vacationDays / 7, 1);
    // Update subscriber_snapshots for this subscriber
        // SubscribersOnVacation CSV is the AUTHORITATIVE source for vacation status
        // Any subscriber in this CSV is considered on vacation, regardless of AllSubscriberReport
        // Find most recent snapshot for this subscriber and mark as on vacation with dates
        $stmt = $db->prepare("
            UPDATE subscriber_snapshots
            SET on_vacation = 1,
                vacation_start = :vac_start,
                vacation_end = :vac_end,
                vacation_weeks = :vac_weeks
            WHERE sub_num = :sub_num
              AND paper_code = :paper_code
              AND snapshot_date = (
                  SELECT MAX(snapshot_date)
                  FROM subscriber_snapshots AS ss2
                  WHERE ss2.sub_num = :sub_num
                    AND ss2.paper_code = :paper_code
              )
        ");
        $stmt->execute([
            ':sub_num' => $subNum,
            ':paper_code' => $paperCode,
            ':vac_start' => $vacStart->format('Y-m-d'),
            ':vac_end' => $vacEnd->format('Y-m-d'),
            ':vac_weeks' => $vacationWeeks
        ]);
        if ($stmt->rowCount() > 0) {
            $stats['updated_rows']++;
            // Track by paper
            if (!isset($stats['by_paper'][$paperCode])) {
                $stats['by_paper'][$paperCode] = 0;
            }
            $stats['by_paper'][$paperCode]++;
        } else {
            $stats['skipped_rows']++;
            $stats['errors'][] = "Line $lineNumber: No matching snapshot found for sub $subNum ($paperCode) - subscriber not in weekly snapshot data";
        }
    }

    fclose($handle);
    if (!$headerFound) {
        throw new Exception('CSV does not appear to be a Subscribers On Vacation report (header row not found)');
    }

    if ($stats['total_rows'] === 0) {
        throw new Exception('No vacation data found in CSV file');
    }

    // Update daily_snapshots with recalculated vacation counts
    // This is the aggregated table used by the dashboard
    $updateDaily = $db->prepare("
        UPDATE daily_snapshots ds
        SET ds.on_vacation = (
            SELECT COUNT(*)
            FROM subscriber_snapshots ss
            WHERE ss.snapshot_date = ds.snapshot_date
              AND ss.paper_code = ds.paper_code
              AND ss.on_vacation = 1
        )
        WHERE ds.snapshot_date = (
            SELECT MAX(snapshot_date) FROM subscriber_snapshots
        )
    ");
    $updateDaily->execute();
    $stats['daily_snapshots_updated'] = $updateDaily->rowCount();
// Step 3: Update raw_uploads with final metadata
    $latestSnapshotStmt = $db->query("SELECT MAX(snapshot_date) as max_date FROM subscriber_snapshots");
    $latestSnapshot = $latestSnapshotStmt->fetch();
    $updateRawStmt = $db->prepare("
        UPDATE raw_uploads SET
            snapshot_date = :snapshot_date,
            row_count = :row_count,
            subscriber_count = :subscriber_count,
            processed_at = NOW(),
            processing_status = 'completed'
        WHERE upload_id = :upload_id
    ");
    $updateRawStmt->execute([
        'snapshot_date' => $latestSnapshot['max_date'] ?? date('Y-m-d'),
        'row_count' => $stats['total_rows'],
        'subscriber_count' => $stats['updated_rows'],
        'upload_id' => $upload_id
    ]);
// Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Vacation data imported successfully',
        'filename' => $filename,
        'upload_id' => $upload_id,
        'stats' => $stats
    ]);
} catch (Exception $e) {
    error_log("Vacation upload error: " . $e->getMessage());
// Mark raw upload as failed
    if (isset($upload_id) && isset($db)) {
        try {
            $failStmt = $db->prepare("
                UPDATE raw_uploads SET
                    processing_status = 'failed',
                    processing_errors = :error
                WHERE upload_id = :upload_id
            ");
            $failStmt->execute([
                        'error' => $e->getMessage(),
                        'upload_id' => $upload_id
            ]);
        } catch (Exception $ignored) {
        // Ignore errors updating the error status
        }
    }

    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'filename' => $filename
    ]);
}

/**
 * Parse Newzware date format (MM/DD/YY)
 * Handles 2-digit years: ALL values treated as 2000-2099 for business logic
 * Examples: 24 = 2024, 55 = 2055, 99 = 2099
 */
function parseNewzwareDate($dateStr)
{

    if (empty($dateStr)) {
        return null;
    }

    // Try MM/DD/YY format
    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{2})$#', $dateStr, $matches)) {
        $month = (int)$matches[1];
        $day = (int)$matches[2];
        $year = (int)$matches[3];
// Convert 2-digit year to 4-digit
        // All 2-digit years are treated as 2000-2099 (business logic requirement)
        $year = 2000 + $year;
// Validate date components
        if (!checkdate($month, $day, $year)) {
            return null;
        }

        return new DateTime("$year-$month-$day");
    }

    // Try MM/DD/YYYY format (full year)
    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $dateStr, $matches)) {
        $month = (int)$matches[1];
        $day = (int)$matches[2];
        $year = (int)$matches[3];
        if (!checkdate($month, $day, $year)) {
            return null;
        }

        return new DateTime("$year-$month-$day");
    }

    return null;
}
