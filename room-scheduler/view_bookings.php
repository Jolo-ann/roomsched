<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "room-scheduler";

// Establishing the database connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Allowed filter values (Maps to the structural building IDs now)
$allowed_buildings = ['1', '2']; // 1 = BAN, 2 = MAC
$allowed_sorts = ['room_asc', 'room_desc'];

// Set building filter if provided
$building_filter = isset($_GET['building']) && in_array($_GET['building'], ['BAN', 'MAC']) ? $_GET['building'] : '';

// Map alphabetical sorting selection to the normalized rooms table column
$room_sort = 'r.room_name ASC';
if (isset($_GET['sort_room']) && in_array($_GET['sort_room'], $allowed_sorts)) {
    $room_sort = $_GET['sort_room'] === 'room_desc' ? 'r.room_name DESC' : 'r.room_name ASC';
}

// Fetch fully joined data from lookup tables instead of raw IDs
$query = "SELECT 
    b.id AS booking_id,
    bl.building_name AS building,
    s.section_name AS section,
    c.course_name AS course,
    sj.subject_name AS subject,
    cd.day_name AS class_days,
    ct.time_slot AS time,
    b.t_start,
    b.t_end,
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
LEFT JOIN rooms r ON b.room_id = r.room_id";

if ($building_filter) {
    $query .= " WHERE bl.building_name = ?";
}
$query .= " ORDER BY $room_sort";

$stmt = $conn->prepare($query);

// If there's a building filter, bind the text parameter safely
if ($building_filter) {
    $stmt->bind_param("s", $building_filter);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Bookings</title>
    <link rel="stylesheet" href="stylebookings.css">
    <link rel="stylesheet" href="header.css">
    <style>
        /* Fullscreen map styles */
        .map-item.fullscreen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: white;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            box-sizing: border-box;
        }
        
        .map-item.fullscreen img {
            max-width: 90%;
            max-height: 80vh;
            object-fit: contain;
        }
        
        .exit-fullscreen {
            position: absolute;
            top: 20px;
            right: 20px;
            background: #f44336;
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            font-size: 20px;
            cursor: pointer;
            z-index: 1001;
        }
        
        .exit-fullscreen:hover {
            background: #d32f2f;
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <img src="assets/logodark.png" alt="Northwestern University Logo">
            <span>NORTHWESTERN<br>UNIVERSITY</span>
        </div>
    </header>

    <h2>ROOM BOOKINGS</h2>

    <div class="sort-container">
        <form method="GET" action="" id="sortForm">
            <label for="building">View Buildings:</label>
            <select id="building" name="building" onchange="this.form.submit()">
                <option value="" <?= $building_filter === '' ? 'selected' : '' ?>>ALL BUILDINGS</option>
                <?php
                $buildings = [
                    'BAN' => 'BEN A. NICOLAS BUILDING',
                    'CEAT' => 'SCIENCE AND TECHNOLOGY CENTER',
                    'MAC' => 'MEGA BUILDING',
                    'IH' => 'IH BUILDING',
                ];
                foreach ($buildings as $code => $name) {
                    echo "<option value=\"$code\" " . ($building_filter === $code ? 'selected' : '') . ">$name</option>";
                }
                ?>
            </select>

            <label for="sort_room">Sort Rooms:</label>
            <select id="sort_room" name="sort_room" onchange="this.form.submit()">
                <option value="room_asc" <?= $room_sort === 'room_name ASC' ? 'selected' : '' ?>>ASCENDING (A-Z)</option>
                <option value="room_desc" <?= $room_sort === 'room_name DESC' ? 'selected' : '' ?>>DESCENDING (Z-A)</option>
            </select>
        </form>
    </div>

    <?php if ($building_filter): ?>
        <div class="building-title">
            <?= $buildings[$building_filter] ?> ROOMS
        </div>

        <div class="map-button">
            <button id="mapToggleBtn" onclick="toggleMap()">View Building Map</button>
        </div>

        <div class="map-scroll-container" id="mapContainer" style="display:none;">
            <div class="map-track">
                <?php if ($building_filter === 'BAN'): ?>
                    <div class="map-item" ondblclick="toggleFullScreen(this)">
                        <img src="assets/BAN Ground Floor.png" alt="BAN Ground Floor" onclick="openModal(this)">
                        <div class="floor-label">Ground Floor</div>
                        <button class="exit-fullscreen" onclick="exitFullScreen(this.parentElement)" style="display:none;">✕</button>
                    </div>
                    <div class="map-item" ondblclick="toggleFullScreen(this)">
                        <img src="assets/BAN Second Floor.png" alt="BAN Second Floor" onclick="openModal(this)">
                        <div class="floor-label">Second Floor</div>
                        <button class="exit-fullscreen" onclick="exitFullScreen(this.parentElement)" style="display:none;">✕</button>
                    </div>
                    <div class="map-item" ondblclick="toggleFullScreen(this)">
                        <img src="assets/BAN Third Floor.png" alt="BAN Third Floor" onclick="openModal(this)">
                        <div class="floor-label">Third Floor</div>
                        <button class="exit-fullscreen" onclick="exitFullScreen(this.parentElement)" style="display:none;">✕</button>
                    </div>
                    <div class="map-item" ondblclick="toggleFullScreen(this)">
                        <img src="assets/BAN Fourth Floor.png" alt="BAN Fourth Floor" onclick="openModal(this)">
                        <div class="floor-label">Fourth Floor</div>
                        <button class="exit-fullscreen" onclick="exitFullScreen(this.parentElement)" style="display:none;">✕</button>
                    </div>
                <?php elseif ($building_filter === 'CEAT'): ?>
                    <div class="map-item" ondblclick="toggleFullScreen(this)">
                        <img src="assets/CEAT Building Map.png" alt="CEAT Building Map" onclick="openModal(this)">
                        <div class="floor-label">Ground & Second Floor</div>
                        <button class="exit-fullscreen" onclick="exitFullScreen(this.parentElement)" style="display:none;">✕</button>
                    </div>
                <?php elseif ($building_filter === 'MAC'): ?>
                    <div class="map-item" ondblclick="toggleFullScreen(this)">
                        <img src="assets/Mega Building Map.png" alt="MAC Building Map" onclick="openModal(this)">
                        <div class="floor-label">Ground & Second Floor</div>
                        <button class="exit-fullscreen" onclick="exitFullScreen(this.parentElement)" style="display:none;">✕</button>
                    </div>
                <?php elseif ($building_filter === 'IH'): ?>
                    <div class="map-item" ondblclick="toggleFullScreen(this)">
                        <img src="assets/IH Building Map.png" alt="IH Building Map" onclick="openModal(this)">
                        <div class="floor-label">All Floors</div>
                        <button class="exit-fullscreen" onclick="exitFullScreen(this.parentElement)" style="display:none;">✕</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="imageModal" class="modal">
            <span class="close" onclick="closeModal()">&times;</span>
            <img class="modal-content" id="modalImage">
        </div>

        <script>
            function toggleMap() {
                const map = document.getElementById('mapContainer');
                const btn = document.getElementById('mapToggleBtn');

                if (map.style.display === 'none' || map.style.display === '') {
                    map.style.display = 'block';
                    btn.textContent = 'Hide Building Map';
                    map.scrollIntoView({ behavior: 'smooth' });
                } else {
                    map.style.display = 'none';
                    btn.textContent = 'View Building Map';
                }
            }

            function openModal(imgElement) {
                const modal = document.getElementById('imageModal');
                const modalImg = document.getElementById('modalImage');
                modal.style.display = 'block';
                modalImg.src = imgElement.src;
                document.body.style.overflow = 'hidden';
            }

            function closeModal() {
                const modal = document.getElementById('imageModal');
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }

            function toggleFullScreen(element) {
                if (element.classList.contains('fullscreen')) {
                    exitFullScreen(element);
                } else {
                    enterFullScreen(element);
                }
            }
            
            function enterFullScreen(element) {
                element.classList.add('fullscreen');
                element.querySelector('.exit-fullscreen').style.display = 'block';
                element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                document.body.style.overflow = 'hidden';
            }
            
            function exitFullScreen(element) {
                element.classList.remove('fullscreen');
                element.querySelector('.exit-fullscreen').style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        </script>
    <?php endif; ?>

    <div class="table-container">
        <?php if ($result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Building</th>
                        <th>Section</th>
                        <th>Course</th>
                        <th>Subject</th>
                        <th>Class Days</th>
                        <th>Time</th>
                        <th>Instructor</th>
                        <th>Room Name</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['building']) ?></td>
                            <td><?= htmlspecialchars($row['section']) ?></td>
                            <td><?= htmlspecialchars($row['course']) ?></td>
                            <td><?= htmlspecialchars($row['subject']) ?></td>
                            <td><?= htmlspecialchars($row['class_days']) ?></td>
                            <td><?= htmlspecialchars($row['time']) ?></td>
                            <td><?= htmlspecialchars($row['instructor_name']) ?></td>
                            <td><?= htmlspecialchars($row['room_name']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-bookings">No bookings found.</p>
        <?php endif; ?>
    </div>

    <div class="back-button">
        <a href="login.php" class="btn-back">← Back</a>
    </div>

    <footer class="page-footer">
        <p>Developed by NWU BSCS3A</p>
    </footer>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>