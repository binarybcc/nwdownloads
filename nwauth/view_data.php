<?php
session_start();

// Security Check: Redirect if not logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Logout Logic
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internal Data View</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f3f4f6;
            color: #1f2937;
            margin: 0;
            padding: 2rem;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 1rem;
            margin-bottom: 2rem;
        }

        h1 {
            margin: 0;
            font-size: 1.5rem;
        }

        .user-info {
            font-size: 0.875rem;
            color: #6b7280;
        }

        .logout-btn {
            background-color: #ef4444;
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
        }

        .logout-btn:hover {
            background-color: #dc2626;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            text-align: left;
            padding: 0.75rem;
            border-bottom: 1px solid #e5e7eb;
        }

        th {
            font-weight: 600;
            color: #374151;
        }
    </style>
</head>

<body>
    <div class="container">
        <header>
            <div>
                <h1>Internal Data View</h1>
                <span class="user-info">Logged in as:
                    <strong><?php echo htmlspecialchars($_SESSION['user']); ?></strong>
                    (<?php echo htmlspecialchars($_SESSION['user_type']); ?>)</span>
            </div>
            <a href="?logout=true" class="logout-btn">Logout</a>
        </header>

        <p>Welcome. You have successfully authenticated against the Newzware system.</p>

        <h3>Exportable Data</h3>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Report Name</th>
                    <th>Date Generated</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>001</td>
                    <td>Daily Circulation Report</td>
                    <td>2025-12-04</td>
                    <td><a href="#">View</a> | <a href="#">Export</a></td>
                </tr>
                <tr>
                    <td>002</td>
                    <td>Subscriber Churn Analysis</td>
                    <td>2025-12-03</td>
                    <td><a href="#">View</a> | <a href="#">Export</a></td>
                </tr>
                <tr>
                    <td>003</td>
                    <td>Revenue Projections Q4</td>
                    <td>2025-12-01</td>
                    <td><a href="#">View</a> | <a href="#">Export</a></td>
                </tr>
            </tbody>
        </table>
    </div>
</body>

</html>