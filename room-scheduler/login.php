<?php
session_start();
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'includes/db.php';

$role = $_GET['role'] ?? null;

if ($role !== 'admin') {
    header("Location: index.html");
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = trim($_POST['id']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ?");
    if ($stmt) {
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $row = $result->fetch_assoc();
            if ($password === $row['password']) {
                $_SESSION['admin_id'] = $id;
                header("Location: admin_dashboard.php");
                exit();
            } else {
                $error_message = "Incorrect password.";
            }
        } else {
            $error_message = "Invalid admin username.";
        }
    } else {
        $error_message = "Database error. Please try again later.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login</title>
    <link rel="stylesheet" href="header.css"/>
    <link rel="stylesheet" href="login.css"/>
    <style>
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 999;
        }

        .modal-content {
            background-color: #f8d7da;
            color: #721c24;
            padding: 20px 25px;
            border-radius: 8px;
            width: 90%;
            max-width: 320px;
            text-align: center;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            border-left: 6px solid #dc3545;
            animation: popIn 0.25s ease;
        }

        .modal-content h3 {
            margin: 0;
            font-size: 16px;
        }

        .modal-content button {
            margin-top: 15px;
            background-color: #dc3545;
            border: none;
            padding: 8px 18px;
            color: white;
            font-size: 14px;
            border-radius: 5px;
            cursor: pointer;
        }

        .modal-content button:hover {
            background-color: #b52a36;
        }

        @keyframes popIn {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body>
<header>
    <div class="logo">
        <img src="assets/logodark.png" alt="Northwestern University Logo" />
        <span>NORTHWESTERN<br>UNIVERSITY</span>
    </div>
</header>

<div class="main-container">
    <div class="login-box">
        <div class="login-container">
            <h1 class="login-title">LOGIN TO ADMIN</h1>
            <form action="login.php?role=admin" method="POST">
                <div class="input-group">
                    <label for="id">Admin:</label>
                    <input type="text" id="id" name="id" placeholder="Enter username" required>
                </div>
                <div class="input-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" placeholder="Enter password" required>
                </div>
                <button type="submit" class="login-btn">Log-in</button>
            </form>
            <a href="index.html" class="back-btn">← Back</a>
        </div>
        <div class="welcome-message"><p>Welcome!</p></div>
    </div>
</div>

<div class="page-footer">
    <div class="footer-content">
        <p>Developed by NWU BSCS2A</p>
    </div>
</div>

<?php if (!empty($error_message)): ?>
<!-- Error Modal -->
<div class="modal" id="errorModal" style="display: flex;">
    <div class="modal-content">
        <h3><?= htmlspecialchars($error_message) ?></h3>
        <button onclick="closeModal()">Close</button>
    </div>
</div>
<?php endif; ?>

<script>
    function closeModal() {
        const modal = document.getElementById('errorModal');
        if (modal) modal.style.display = 'none';
    }
</script>
</body>
</html>

<?php $conn->close(); ?>
