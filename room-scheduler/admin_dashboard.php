<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

include 'includes/db.php';

// Global Display Safety: Suppress PHP Notices/Warnings from breaking your visual table layouts
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

$message = "";

// Fetch courses dynamically from the relational table for dropdown
$courses_sql = "SELECT course_name FROM courses ORDER BY course_name ASC";
$courses_result = $conn->query($courses_sql);
$courses = [];
if ($courses_result && $courses_result->num_rows > 0) {
    while ($row = $courses_result->fetch_assoc()) {
        $courses[] = $row['course_name'];
    }
}

// DELETE BOOKING
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM bookings WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $message = "Booking deleted successfully!";
}

// EDIT BOOKING (Pulls numeric ID records for form alignment)
$edit_mode = false;
$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $edit_mode = true;
    $stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_result = $stmt->get_result();
    $edit_data = $edit_result->fetch_assoc();
}

// FORM SUBMIT (ADD or UPDATE)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Collect literal text names from user input selections
    $building_name = strtoupper(trim($_POST['building']));
    $section_name  = strtoupper(trim($_POST['section']));
    $course_name   = strtoupper(trim($_POST['course']));
    $subject_name  = strtoupper(trim($_POST['subject']));
    $day_name      = strtoupper(trim($_POST['class_days']));
    $time_start_str = $_POST['time_start'];
    $time_end_str   = $_POST['time_end'];
    $instructor_name = strtoupper(trim($_POST['instructor_name']));
    $room_name     = strtoupper(trim($_POST['room_name']));
    
    $t_start = date("H:i:s", strtotime($time_start_str));
    $t_end = date("H:i:s", strtotime($time_end_str));
    $time_label = "$time_start_str - $time_end_str";
    $booking_id = $_POST['booking_id'] ?? '';

    // --- ID TRANSLATION BLOCK ---
    $b_stmt = $conn->prepare("SELECT building_id FROM buildings WHERE building_name = ?");
    $b_stmt->bind_param("s", $building_name); $b_stmt->execute(); $res = $b_stmt->get_result()->fetch_assoc();
    $building_id = $res['building_id'] ?? null;

    $s_stmt = $conn->prepare("SELECT section_id FROM sections WHERE section_name = ?");
    $s_stmt->bind_param("s", $section_name); $s_stmt->execute(); $res = $s_stmt->get_result()->fetch_assoc();
    $section_id = $res['section_id'] ?? null;

    $c_stmt = $conn->prepare("SELECT id FROM courses WHERE course_name = ?");
    $c_stmt->bind_param("s", $course_name); $c_stmt->execute(); $res = $c_stmt->get_result()->fetch_assoc();
    $course_id = $res['id'] ?? null;

    $sj_stmt = $conn->prepare("SELECT subject_id FROM subjects WHERE subject_name = ?");
    $sj_stmt->bind_param("s", $subject_name); $sj_stmt->execute(); $res = $sj_stmt->get_result()->fetch_assoc();
    $subject_id = $res['subject_id'] ?? null;

    $d_stmt = $conn->prepare("SELECT day_id FROM class_days WHERE day_name = ?");
    $d_stmt->bind_param("s", $day_name); $d_stmt->execute(); $res = $d_stmt->get_result()->fetch_assoc();
    $day_id = $res['day_id'] ?? null;

    // Time Slot Mapping Logic
    $t_stmt = $conn->prepare("SELECT time_id FROM class_times WHERE time_slot = ?");
    $t_stmt->bind_param("s", $time_label); $t_stmt->execute(); $res = $t_stmt->get_result()->fetch_assoc();
    $time_id = $res['time_id'] ?? null;
    if (!$time_id && !empty($time_label)) {
        $ins_t = $conn->prepare("INSERT INTO class_times (time_slot) VALUES (?)");
        $ins_t->bind_param("s", $time_label); $ins_t->execute();
        $time_id = $conn->insert_id;
    }

    $i_stmt = $conn->prepare("SELECT instructor_id FROM instructors WHERE name = ?");
    $i_stmt->bind_param("s", $instructor_name); $i_stmt->execute(); $res = $i_stmt->get_result()->fetch_assoc();
    $instructor_id = $res['instructor_id'] ?? null;

    $r_stmt = $conn->prepare("SELECT room_id FROM rooms WHERE room_name = ?");
    $r_stmt->bind_param("s", $room_name); $r_stmt->execute(); $res = $r_stmt->get_result()->fetch_assoc();
    $room_id = $res['room_id'] ?? null;
    
    //  LINE 101 FIX: Changed !$r_id to !$room_id to match variable assignment logic
    if (!$room_id && !empty($room_name)) {
        $ins_r = $conn->prepare("INSERT INTO rooms (room_name) VALUES (?)");
        $ins_r->bind_param("s", $room_name); $ins_r->execute();
        $room_id = $conn->insert_id;
    }

    // RELATIONAL CONFLICT DETECTION - STRICT BOUNDARY MATRIX
    $check_sql = "SELECT b.id, b.room_id, b.instructor_id, i.name AS instructor_name, r.room_name 
                  FROM bookings b
                  LEFT JOIN instructors i ON b.instructor_id = i.instructor_id
                  LEFT JOIN rooms r ON b.room_id = r.room_id
                  WHERE b.day_id = ? 
                  AND (b.t_start < ? AND b.t_end > ?)
                  AND ((b.room_id = ? AND b.building_id = ?) OR (b.instructor_id = ?))";
    
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("isiiii", $day_id, $t_end, $t_start, $room_id, $building_id, $instructor_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    $is_conflict = false;
    while ($row = $check_result->fetch_assoc()) {
        if ($booking_id && $row['id'] == $booking_id) {
            continue; 
        }
        $is_conflict = true;
        
        if (intval($row['instructor_id']) === intval($instructor_id)) {
            $message = "DUAL BOOKING DETECTED: Instructor " . htmlspecialchars($row['instructor_name']) . " is already booked during this time sequence!";
        } else {
            $message = "ROOM CONFLICT DETECTED: Room " . htmlspecialchars($row['room_name']) . " is already occupied during this time window!";
        }
        break;
    }

    if (!$is_conflict) {
        if ($booking_id) {
            $stmt = $conn->prepare("UPDATE bookings SET building_id=?, section_id=?, course_id=?, subject_id=?, day_id=?, time_id=?, t_start=?, t_end=?, instructor_id=?, room_id=? WHERE id=?");
            $stmt->bind_param("iiiiisssiii", $building_id, $section_id, $course_id, $subject_id, $day_id, $time_id, $t_start, $t_end, $instructor_id, $room_id, $booking_id);
            $stmt->execute();
            $message = "Booking updated successfully!";
        } else {
            $stmt = $conn->prepare("INSERT INTO bookings (building_id, section_id, course_id, subject_id, day_id, time_id, t_start, t_end, instructor_id, room_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiiisssii", $building_id, $section_id, $course_id, $subject_id, $day_id, $time_id, $t_start, $t_end, $instructor_id, $room_id);
            $stmt->execute();
            $message = "Booking successfully added!";
        }
        if ($booking_id) { header("Location: admin_dashboard.php"); exit(); }
    }
}

// FETCH SYSTEM VIEW WITH NAMES VIA LEFT JOINS
$query = "SELECT 
    b.id, b.building_id, b.section_id, b.course_id, b.subject_id, b.day_id, b.instructor_id, b.room_id,
    bl.building_name AS building,
    s.section_name AS section,
    c.course_name AS course,
    sj.subject_name AS subject,
    cd.day_name AS class_days,
    ct.time_slot AS time,
    i.name AS instructor_name,
    r.room_name
FROM bookings b
LEFT JOIN buildings bl ON b.building_id = bl.building_id
LEFT JOIN sections s ON b.section_id = s.section_id
LEFT JOIN courses c ON b.course_id = c.id
LEFT JOIN subjects sj ON b.subject_id = sj.subject_id
LEFT JOIN class_days cd ON b.day_id = cd.day_id
LEFT JOIN class_times ct ON b.time_id = ct.time_id
LEFT JOIN instructors i ON b.instructor_id = i.instructor_id
LEFT JOIN rooms r ON b.room_id = r.room_id
ORDER BY cd.day_id ASC";

$bookings = $conn->query($query);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="styleadmin.css"/>
    <link rel="stylesheet" href="header.css"/>
    <link rel="stylesheet" href="footer.css">
    <style>
        .modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); justify-content:center; align-items:center; z-index:1000; }
        .modal-content { background:#fff; padding:20px; border-radius:10px; width:400px; text-align:center; background-color:#f8d7da; color:#721c24; }
        .cancel-btn { background:#bbb; color:#fff; }
        .confirm-btn { background:#f44336; color:#fff; }
        .cancel-btn:hover, .confirm-btn:hover { opacity:0.8; }
        .action-buttons a { text-decoration:none; padding:8px 15px; border-radius:5px; color:white; margin-right:5px; }
        .edit-btn { background-color:#4CAF50; }
        .delete-btn { background-color:#f44336; }
        select { width: 100%; padding: 10px; margin-bottom: 10px; border-radius: 5px; }
        .empty-cell { color: #9aa0a6; font-style: italic; }
    </style>
</head>
<body>
<header>
    <div class="logo">
        <img src="assets/logodark.png" alt="Northwestern University Logo" />
        <span>NORTHWESTERN<br>UNIVERSITY</span>
    </div>
    <a href="logout.php" class="header-btn">Logout</a>
</header>

<div class="container">

    <div class="form-section">
        <h2><?= $edit_mode ? "EDIT BOOKING" : "ROOM SCHEDULER" ?></h2>

        <?php if (!empty($message)): ?>
            <div class="message" style="padding:10px; margin-bottom:15px; background-color:#f8d7da; color:#721c24; border-radius:5px; font-weight:bold;"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="booking_id" value="<?= $edit_mode ? $edit_data['id'] : '' ?>">

            <?php
            $edit_b_name = ""; $edit_c_name = ""; $edit_s_name = ""; $edit_sj_name = ""; $edit_d_name = ""; $edit_i_name = ""; $edit_r_name = "";
            if ($edit_mode) {
                $look_b = $conn->query("SELECT building_name FROM buildings WHERE building_id=".intval($edit_data['building_id']))->fetch_assoc(); 
                $edit_b_name = $look_b['building_name'] ?? "";

                $look_c = $conn->query("SELECT course_name FROM courses WHERE id=".intval($edit_data['course_id']))->fetch_assoc(); 
                $edit_c_name = trim(strtoupper($look_c['course_name'] ?? ""));

                $look_s = $conn->query("SELECT section_name FROM sections WHERE section_id=".intval($edit_data['section_id']))->fetch_assoc(); 
                $edit_s_name = trim(strtoupper($look_s['section_name'] ?? ""));

                $look_sj = $conn->query("SELECT subject_name FROM subjects WHERE subject_id=".intval($edit_data['subject_id']))->fetch_assoc(); 
                $edit_sj_name = trim(strtoupper($look_sj['subject_name'] ?? ""));

                $look_d = $conn->query("SELECT day_name FROM class_days WHERE day_id=".intval($edit_data['day_id']))->fetch_assoc(); 
                $edit_d_name = trim(strtoupper($look_d['day_name'] ?? ""));

                $look_i = $conn->query("SELECT name FROM instructors WHERE instructor_id=".intval($edit_data['instructor_id']))->fetch_assoc(); 
                $edit_i_name = trim(strtoupper($look_i['name'] ?? ""));

                $look_r = $conn->query("SELECT room_name FROM rooms WHERE room_id=".intval($edit_data['room_id']))->fetch_assoc(); 
                $edit_r_name = $look_r['room_name'] ?? "";

                $look_t = $conn->query("SELECT time_slot FROM class_times WHERE time_id=".intval($edit_data['time_id']))->fetch_assoc(); 
                $edit_data['time'] = $look_t['time_slot'] ?? "";
            }
            ?>

            <select name="building" id="buildingSelect" required onchange="filterRooms()">
                <option value="" disabled <?= !$edit_mode ? "selected" : "" ?>>SELECT BUILDING</option>
                <?php
                $buildings = ["BAN" => "BEN A. NICOLAS BUILDING", "IH" => "IH BUILDING", "CEAT" => "SCIENCE AND TECHNOLOGY CENTER", "MAC" => "MEGA BUILDING"];
                foreach ($buildings as $code => $label): ?>
                    <option value="<?= $code ?>" <?= ($edit_mode && trim($edit_b_name) == $code) ? "selected" : "" ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>

            <select name="course" required>
                <option value="" disabled <?= !$edit_mode ? "selected" : "" ?>>SELECT COURSE</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>" <?= ($edit_mode && strtoupper(trim($edit_c_name)) == strtoupper(trim($c))) ? "selected" : "" ?>><?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
            </select>

            <select name="time_start" required>
                <option value="" disabled <?= !$edit_mode ? "selected" : "" ?>>SELECT START TIME</option>
                <?php
                $start_time = strtotime('6:00 AM');
                $end_time = strtotime('9:00 PM');
                $db_start_time = $edit_mode ? trim(explode("-", $edit_data['time'])[0]) : "";

                while ($start_time <= $end_time) {
                    $time_value = date('g:i A', $start_time);
                    $selected = ($edit_mode && $db_start_time == $time_value) ? "selected" : "";
                    echo '<option value="' . $time_value . '" ' . $selected . '>' . $time_value . '</option>';
                    $start_time = strtotime('+30 minutes', $start_time);
                }
                ?>
            </select>

            <select name="time_end" required>
                <option value="" disabled <?= !$edit_mode ? "selected" : "" ?>>SELECT END TIME</option>
                <?php
                $start_time = strtotime('6:00 AM');
                $end_time = strtotime('9:00 PM');
                $db_end_time = ($edit_mode && strpos($edit_data['time'], '-') !== false) ? trim(explode("-", $edit_data['time'])[1]) : "";

                while ($start_time <= $end_time) {
                    $time_value = date('g:i A', $start_time);
                    $selected = ($edit_mode && $db_end_time == $time_value) ? "selected" : "";
                    echo '<option value="' . $time_value . '" ' . $selected . '>' . $time_value . '</option>';
                    $start_time = strtotime('+30 minutes', $start_time);
                }
                ?>
            </select>

            <select name="section" required>
                <option value="" disabled <?= !$edit_mode ? "selected" : "" ?>>SELECT SECTION</option>
                <?php foreach(["BSCS-1A", "BSCS-2A", "BSIT-1B", "BSCE-2", "BSLMS-3"] as $opt): ?>
                    <option value="<?= $opt ?>" <?= ($edit_mode && strtoupper(trim($edit_s_name)) == strtoupper($opt)) ? "selected" : "" ?>><?= $opt ?></option>
                <?php endforeach; ?>
            </select>

            <select name="subject" required>
                <option value="" disabled <?= !$edit_mode ? "selected" : "" ?>>SELECT SUBJECT</option>
                <?php foreach(["CS ELECTIVE 1", "MULTIMEDIA", "DATA STRUCTURES", "NETWORKING"] as $opt): ?>
                    <option value="<?= $opt ?>" <?= ($edit_mode && strtoupper(trim($edit_sj_name)) == strtoupper($opt)) ? "selected" : "" ?>><?= $opt ?></option>
                <?php endforeach; ?>
            </select>

            <select name="class_days" required>
                <option value="" disabled <?= !$edit_mode ? "selected" : "" ?>>SELECT DAY</option>
                <?php foreach(["MWF", "MW", "TTH", "SATURDAY"] as $opt): ?>
                    <option value="<?= $opt ?>" <?= ($edit_mode && strtoupper(trim($edit_d_name)) == strtoupper($opt)) ? "selected" : "" ?>><?= $opt ?></option>
                <?php endforeach; ?>
            </select>

            <select name="instructor_name" required>
                <option value="" disabled <?= !$edit_mode ? "selected" : "" ?>>SELECT INSTRUCTOR</option>
                <?php foreach(["ROWELL CASIL", "RYAN BABA", "MARC KEVIN BITUEN"] as $opt): ?>
                    <option value="<?= $opt ?>" <?= ($edit_mode && strtoupper(trim($edit_i_name)) == strtoupper($opt)) ? "selected" : "" ?>><?= $opt ?></option>
                <?php endforeach; ?>
            </select>

            <select name="room_name" id="roomSelect" required>
                <option value="" disabled <?= !$edit_mode ? "selected" : "" ?>>SELECT ROOM</option>
            </select>

            <button type="submit"><?= $edit_mode ? "Update Booking" : "Book Room" ?></button>
            <?php if ($edit_mode): ?>
                <a href="admin_dashboard.php" class="cancel-btn" style="text-decoration:none; padding:10px; display:inline-block; border-radius:5px;">Cancel</a>
            <?php endif; ?>
        </form>
    </div>

    <h2>ROOM BOOKINGS</h2>
    <?php if ($bookings && $bookings->num_rows > 0): ?>
        <table>
            <tr>
                <th>Building</th>
                <th>Section</th>
                <th>Course</th>
                <th>Subject</th>
                <th>Class Days</th>
                <th>Time</th>
                <th>Instructor</th>
                <th>Room</th>
                <th>Actions</th>
            </tr>
            <?php while ($row = $bookings->fetch_assoc()): ?>
                <tr>
                    <td><?= !empty($row['building']) ? htmlspecialchars($row['building']) : '<span class="empty-cell">—</span>' ?></td>
                    <td><?= !empty($row['section']) ? htmlspecialchars($row['section']) : '<span class="empty-cell">—</span>' ?></td>
                    <td><?= !empty($row['course']) ? htmlspecialchars($row['course']) : '<span class="empty-cell">—</span>' ?></td>
                    <td><?= !empty($row['subject']) ? htmlspecialchars($row['subject']) : '<span class="empty-cell">—</span>' ?></td>
                    <td><?= !empty($row['class_days']) ? htmlspecialchars($row['class_days']) : '<span class="empty-cell">—</span>' ?></td>
                    <td><?= !empty($row['time']) ? htmlspecialchars($row['time']) : '<span class="empty-cell">—</span>' ?></td>
                    <td><?= !empty($row['instructor_name']) ? htmlspecialchars($row['instructor_name']) : '<span class="empty-cell">—</span>' ?></td>
                    <td><?= !empty($row['room_name']) ? htmlspecialchars($row['room_name']) : '<span class="empty-cell">—</span>' ?></td>
                    <td class="action-buttons">
                        <a href="?edit=<?= $row['id'] ?>" class="edit-btn">Edit</a>
                        <a href="#" class="delete-btn" onclick="openDeleteModal(<?= $row['id'] ?>)">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p>No bookings found.</p>
    <?php endif; ?>

</div>

<div class="modal" id="deleteModal">
    <div class="modal-content">
        <h3>Are you sure you want to delete this booking?</h3>
        <button class="cancel-btn" onclick="closeModal()">Cancel</button>
        <button class="confirm-btn" id="confirmDeleteBtn">Confirm</button>
    </div>
</div>

<script>
    const roomData = {
        "BAN": ["201", "202", "203", "204", "205"],
        "IH": ["LAB 1", "LAB 2", "IH-101"],
        "CEAT": ["ST-101", "ST-102", "PHYSICS LAB"],
        "MAC": ["MEGA-1", "MEGA-2", "AUDITORIUM"]
    };

    function filterRooms() {
        const building = document.getElementById('buildingSelect').value;
        const roomSelect = document.getElementById('roomSelect');
        const currentRoom = "<?= $edit_mode ? $edit_r_name : '' ?>";
        
        roomSelect.innerHTML = '<option value="" disabled selected>SELECT ROOM</option>';
        if (roomData[building]) {
            roomData[building].forEach(room => {
                const opt = document.createElement('option');
                opt.value = room;
                opt.textContent = room;
                if(room === currentRoom) opt.selected = true;
                roomSelect.appendChild(opt);
            });
        }
    }

    let deleteUrl = "";
    function openDeleteModal(id) {
        deleteUrl = "?delete=" + id;
        document.getElementById('deleteModal').style.display = 'flex';
    }
    function closeModal() {
        document.getElementById('deleteModal').style.display = 'none';
    }
    document.getElementById('confirmDeleteBtn').onclick = function () {
        window.location.href = deleteUrl;
    };

    window.onload = function() {
        if (document.getElementById('buildingSelect').value !== "") {
            filterRooms();
        }
    };
</script>

</body>
</html>
<?php $conn->close(); ?>