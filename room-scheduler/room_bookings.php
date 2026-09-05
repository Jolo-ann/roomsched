<?php
session_start();

if (!isset($_SESSION['student_id'])) {
    header("Location: index.html");  // Redirect if the user is not logged in
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "room-scheduler";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$student_id = $_SESSION['student_id'];

$sql = "SELECT name, purpose, date, time FROM bookings WHERE student_id = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Your Room Bookings</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
            text-align: center;
        }
        .container {
            margin: 50px auto;
            width: 90%;
            max-width: 600px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            background: white;
        }
        th, td {
            padding: 12px;
            border: 1px solid #ccc;
        }
        th {
            background-color: darkred;
            color: white;
        }
        
    </style>
</head>
<body>
    <div class="container">
        <h2>Room Bookings</h2>
        <?php if ($result->num_rows > 0): ?>
        <table>
            <tr>
                <th>Name</th>
                <th>Purpose</th>
                <th>Date</th>
                <th>Time</th>
            </tr>
            <?php while($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row["name"]); ?></td>
                <td><?php echo htmlspecialchars($row["purpose"]); ?></td>
                <td><?php echo htmlspecialchars($row["date"]); ?></td>
                <td><?php echo htmlspecialchars($row["time"]); ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
        <?php else: ?>
            <p>No bookings found.</p>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
