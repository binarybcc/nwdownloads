<?php

/**
 * Circulation Dashboard - Login Page
 *
 * Authenticates users against Newzware authentication system.
 * Only users with usertype="NW" are granted access.
 */

// Include config FIRST (sets session security settings)
require_once 'config.php';
require_once 'brute_force_protection.php';
// Start session AFTER config loaded (session settings must be set before session_start)
session_start();
// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// If already logged in, redirect to dashboard
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: index.php');
    exit;
}

$error = '';
// Handle Login Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_id = trim($_POST['login_id'] ?? '');
    $password = trim($_POST['password'] ?? '');
// SECURITY: Validate CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
        error_log('SECURITY: CSRF validation failed from IP: ' . $_SERVER['REMOTE_ADDR']);
    }
    // SECURITY: Check brute force protection
    elseif (!empty($login_id) && !checkBruteForce($login_id)) {
        $error = $_SESSION['lockout_message'] ?? 'Too many failed attempts. Please try again later.';
    } elseif (empty($login_id) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
    // Prepare API Request
        $params = [
            'site' => NW_SITE_ID,
            'login_id' => $login_id,
            'password' => $password
        ];
        $requestUrl = NW_AUTH_URL . '?' . http_build_query($params);
    // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $requestUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        if ($httpCode === 200 && $response) {
            try {
                $xml = new SimpleXMLElement($response);
                // STRICT AUTHENTICATION LOGIC
                // 1. Check if authenticated is "Yes"
                // 2. Check if usertype is "NW"
                $isAuthenticated = (string) $xml->authenticated === 'Yes';
                $userType = (string) $xml->usertype;
                if ($isAuthenticated && $userType === 'NW') {
                // Success: Set Session
                    $_SESSION['logged_in'] = true;
                    $_SESSION['user'] = (string) $xml->login;
                    $_SESSION['user_type'] = $userType;
                    $_SESSION['login_time'] = time();
                    $_SESSION['last_activity'] = time();
                // SECURITY: Regenerate session ID to prevent fixation attacks
                    session_regenerate_id(true);
                // SECURITY: Reset brute force counter after successful login
                    resetAttempts($login_id);
                // Redirect to dashboard
                    header('Location: index.php');
                    exit;
                } else {
                // Auth failed or User Type not allowed
                    $error = 'Access Denied. Invalid credentials or unauthorized user type.';
                // SECURITY: Record failed attempt for brute force protection
                    recordFailedAttempt($login_id);
                }
            } catch (Exception $e) {
                $error = 'System Error: Unable to process authentication response.';
                error_log('Auth XML Parse Error: ' . $e->getMessage());
            // SECURITY: Record attempt even on system error (could be attack probe)
                recordFailedAttempt($login_id);
            }
        } else {
            $error = 'System Error: Unable to connect to authentication server.';
            if ($curlError) {
                error_log('Auth cURL Error: ' . $curlError);
            }
            // SECURITY: Record attempt even on connection error
            recordFailedAttempt($login_id);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <style>
        :root {
            --primary-color: #3b82f6;
            --primary-hover: #2563eb;
            --bg-color: #f3f4f6;
            --card-bg: #ffffff;
            --text-color: #1f2937;
            --error-color: #ef4444;
            --border-color: #d1d5db;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: var(--text-color);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 1rem;
        }

        .login-card {
            background-color: var(--card-bg);
            padding: 2.5rem;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            width: 100%;
            max-width: 420px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #111827;
        }

        .login-header p {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 0.875rem;
            border: 2px solid var(--border-color);
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: all 0.2s ease;
        }

        input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        button {
            width: 100%;
            padding: 0.875rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.1s ease, box-shadow 0.2s ease;
            margin-top: 0.5rem;
        }

        button:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        button:active {
            transform: translateY(0);
        }

        .error-message {
            background-color: #fee2e2;
            border-left: 4px solid var(--error-color);
            color: #991b1b;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            display: flex;
            align-items: start;
        }

        .error-message::before {
            content: "⚠️";
            margin-right: 0.75rem;
            font-size: 1.25rem;
        }

        .footer-note {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 0.75rem;
            color: #9ca3af;
        }

        .nw-logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background-color: #f9fafb;
            border-radius: 0.5rem;
            border: 1px solid #e5e7eb;
        }

        .nw-logo {
            width: 48px;
            height: 48px;
        }

        .nw-logo-text {
            font-size: 0.875rem;
            color: #6b7280;
            text-align: left;
        }

        .nw-logo-text strong {
            color: #374151;
            display: block;
            margin-bottom: 0.25rem;
        }
    </style>
</head>

<body>
    <div class="login-card">
        <div class="login-header">
            <h1><?php echo APP_NAME; ?></h1>
        </div>

        <?php if ($error) :
            ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php
        endif; ?>

        <!-- Newzware Logo & Instructions -->
        <div class="nw-logo-container">
            <img src="assets/NWlogo.png?v=20251205" alt="Newzware" class="nw-logo">
            <div class="nw-logo-text">
                <strong>Use your Newzware credentials</strong>
                Same login as your Newzware system
            </div>
        </div>

        <form method="POST" action="login.php">
            <!-- SECURITY: CSRF Protection Token -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <div class="form-group">
                <label for="login_id">Login ID</label>
                <input type="text" id="login_id" name="login_id" required autofocus autocomplete="username" placeholder="Enter your login ID">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="Enter your password">
            </div>

            <button type="submit">Sign In</button>
        </form>

        <div class="footer-note">
            Secure authentication via Newzware
        </div>
    </div>
</body>

</html>
