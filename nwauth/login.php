<?php
session_start();
require_once 'config.php';

$error = '';

// Handle Login Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_id = trim($_POST['login_id'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($login_id) || empty($password)) {
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
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Note: For production, ensure valid SSL certs

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
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

                    // Redirect to protected page
                    header('Location: view_data.php');
                    exit;
                } else {
                    // Auth failed or User Type not allowed
                    $error = 'Access Denied. Invalid credentials or unauthorized user type.';
                }
            } catch (Exception $e) {
                $error = 'System Error: Unable to process authentication response.';
            }
        } else {
            $error = 'System Error: Unable to connect to authentication server.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internal Access Login</title>
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

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .login-card {
            background-color: var(--card-bg);
            padding: 2.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            width: 100%;
            max-width: 380px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            color: #111827;
        }

        .login-header p {
            margin-top: 0.5rem;
            color: #6b7280;
            font-size: 0.875rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 0.375rem;
            font-size: 1rem;
            box-sizing: border-box;
            transition: border-color 0.15s ease-in-out;
        }

        input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        button {
            width: 100%;
            padding: 0.75rem;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 0.375rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.15s ease-in-out;
        }

        button:hover {
            background-color: var(--primary-hover);
        }

        .error-message {
            background-color: #fee2e2;
            border: 1px solid #fecaca;
            color: var(--error-color);
            padding: 0.75rem;
            border-radius: 0.375rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="login-card">
        <div class="login-header">
            <h1>Restricted Access</h1>
            <p>Please sign in to continue</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="login_id">Login ID</label>
                <input type="text" id="login_id" name="login_id" required autofocus autocomplete="username">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>

            <button type="submit">Sign In</button>
        </form>
    </div>
</body>

</html>