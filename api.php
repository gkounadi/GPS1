<?php
session_start();
header('Content-Type: application/json');

// Load database configuration
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle API requests
$action = $_GET['action'] ?? '';

function haversineDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // meters
    $latDiff = deg2rad($lat2 - $lat1);
    $lonDiff = deg2rad($lon2 - $lon1);
    $a = sin($latDiff / 2) * sin($latDiff / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($lonDiff / 2) * sin($lonDiff / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earthRadius * $c; // meters
}

function convertDistance($distanceMeters, $unit) {
    return $unit === 'yards' ? ($distanceMeters * 1.09361) : $distanceMeters;
}

switch ($action) {
    case 'register':
        $data = json_decode(file_get_contents('php://input'), true);
        $email = filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $password = $data['password'] ?? '';
        $name = trim($data['name'] ?? '');

        if (!$email || strlen($password) < 6 || empty($name)) {
            echo json_encode(['error' => 'Invalid email, password (min 6 chars), or name']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode(['error' => 'Email already registered']);
            exit;
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (email, password, name) VALUES (?, ?, ?)");
        try {
            $stmt->execute([$email, $hashedPassword, $name]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Registration failed: ' . $e->getMessage()]);
        }
        break;

    case 'login':
        $data = json_decode(file_get_contents('php://input'), true);
        $email = filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $password = $data['password'] ?? '';

        if (!$email || !$password) {
            echo json_encode(['error' => 'Invalid email or password']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id, password, name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            session_regenerate_id(true);
            echo json_encode(['success' => true, 'name' => $user['name']]);
        } else {
            echo json_encode(['error' => 'Incorrect email or password']);
        }
        break;

    case 'logout':
        session_destroy();
        session_start();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        echo json_encode(['success' => true]);
        break;

    case 'get_profile':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['error' => 'Not logged in']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fetch sessions
        $stmt = $pdo->prepare(
            "SELECT rs.id as session_id, rs.created_at, c.name as course_name
             FROM round_sessions rs
             JOIN courses c ON rs.course_id = c.id
             WHERE rs.user_id = ?
             ORDER BY rs.created_at DESC"
        );
        $stmt->execute([$_SESSION['user_id']]);
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($sessions as &$session) {
            $stmt2 = $pdo->prepare(
                "SELECT hole_number, score FROM rounds WHERE round_session_id = ? ORDER BY hole_number"
            );
            $stmt2->execute([$session['session_id']]);
            $session['holes'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            $session['total'] = array_sum(array_column($session['holes'], 'score'));
        }

        echo json_encode([
            'email' => $user['email'],
            'name' => $user['name'],
            'sessions' => $sessions
        ]);
        break;

    case 'get_course':
        $course_id = 1; // Fixed to Riverside
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
        $stmt->execute([$course_id]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT * FROM holes WHERE course_id = ? ORDER BY hole_number");
        $stmt->execute([$course_id]);
        $holes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $course['holes_data'] = $holes;
        echo json_encode($course);
        break;

    case 'calculate_distances':
        $data = json_decode(file_get_contents('php://input'), true);
        if ($data['csrf_token'] !== $_SESSION['csrf_token']) {
            echo json_encode(['error' => 'Invalid CSRF token']);
            exit;
        }

        $player_lat = $data['player_lat'];
        $player_lon = $data['player_lon'];
        $fairway_lat = $data['fairway_lat'] ?? null;
        $fairway_lon = $data['fairway_lon'] ?? null;
        $hole_number = $data['hole_number'];
        $unit = $data['unit'] ?? 'meters';

        $stmt = $pdo->prepare("SELECT * FROM holes WHERE course_id = 1 AND hole_number = ?");
        $stmt->execute([$hole_number]);
        $hole = $stmt->fetch(PDO::FETCH_ASSOC);

        $distances = [
            'front' => convertDistance(haversineDistance($player_lat, $player_lon, $hole['green_front_lat'], $hole['green_front_lon']), $unit),
            'center' => convertDistance(haversineDistance($player_lat, $player_lon, $hole['green_center_lat'], $hole['green_center_lon']), $unit),
            'back' => convertDistance(haversineDistance($player_lat, $player_lon, $hole['green_back_lat'], $hole['green_back_lon']), $unit)
        ];

        if ($fairway_lat && $fairway_lon) {
            $distances['to_point'] = convertDistance(haversineDistance($player_lat, $player_lon, $fairway_lat, $fairway_lon), $unit);
            $distances['from_point_to_green'] = [
                'front' => convertDistance(haversineDistance($fairway_lat, $fairway_lon, $hole['green_front_lat'], $hole['green_front_lon']), $unit),
                'center' => convertDistance(haversineDistance($fairway_lat, $fairway_lon, $hole['green_center_lat'], $hole['green_center_lon']), $unit),
                'back' => convertDistance(haversineDistance($fairway_lat, $fairway_lon, $hole['green_back_lat'], $hole['green_back_lon']), $unit)
            ];
        }

        echo json_encode($distances);
        break;

    case 'start_round':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['error' => 'Not logged in']);
            exit;
        }
        // Only create a new round if there isn't one already in progress
        if (!isset($_SESSION['round_session_id']) || !$_SESSION['round_session_id']) {
            $course_id = 1; // Optionally, allow frontend to select course
            $user_id = $_SESSION['user_id'];
            $stmt = $pdo->prepare("INSERT INTO round_sessions (user_id, course_id) VALUES (?, ?)");
            $stmt->execute([$user_id, $course_id]);
            $_SESSION['round_session_id'] = $pdo->lastInsertId();
            echo json_encode(['success' => true, 'round_session_id' => $_SESSION['round_session_id']]);
        } else {
            echo json_encode(['success' => true, 'round_session_id' => $_SESSION['round_session_id']]);
        }
        break;

    case 'check_round_session':
        $has_round = isset($_SESSION['round_session_id']) && $_SESSION['round_session_id'];
        echo json_encode(['has_round' => $has_round]);
        break;

    case 'submit_round':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['error' => 'Not logged in']);
            exit;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        if ($data['csrf_token'] !== $_SESSION['csrf_token']) {
            echo json_encode(['error' => 'Invalid CSRF token']);
            exit;
        }
        if (!isset($_SESSION['round_session_id'])) {
            echo json_encode(['error' => 'No active round session. Start a round first.']);
            exit;
        }
        $user_id = $_SESSION['user_id'];
        $round_session_id = $_SESSION['round_session_id'];
        $scores = $data['scores'];
        if (!is_array($scores) || count($scores) === 0) {
            echo json_encode(['error' => 'No scores provided']);
            exit;
        }
        // Delete any existing scores for this session (in case of resubmission)
        $stmt = $pdo->prepare("DELETE FROM rounds WHERE user_id = ? AND course_id = 1 AND round_session_id = ?");
        $stmt->execute([$user_id, $round_session_id]);
        // Insert all scores
        $insert = $pdo->prepare("INSERT INTO rounds (user_id, course_id, hole_number, score, round_session_id) VALUES (?, 1, ?, ?, ?)");
        foreach ($scores as $row) {
            $hole_number = (int)$row['hole_number'];
            $score = (int)$row['score'];
            $insert->execute([$user_id, $hole_number, $score, $round_session_id]);
        }
        // End this session
        unset($_SESSION['round_session_id']);
        echo json_encode(['success' => true]);
        break;

    case 'end_round':
        // Only ends the round session.
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['error' => 'Not logged in']);
            exit;
        }
        if (isset($_SESSION['round_session_id'])) {
            unset($_SESSION['round_session_id']);
        }
        echo json_encode(['success' => true]);
        break;

    // --------- ROUND EDIT/DELETE SUPPORT ---------
    case 'get_round_details':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['error' => 'Not logged in']);
            exit;
        }
        $session_id = intval($_GET['session_id'] ?? 0);
        if (!$session_id) {
            echo json_encode(['error' => 'No session id']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT hole_number, score FROM rounds WHERE round_session_id = ? ORDER BY hole_number");
        $stmt->execute([$session_id]);
        $holes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['holes' => $holes]);
        break;

    case 'edit_round':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['error' => 'Not logged in']);
            exit;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        if (($data['csrf_token'] ?? null) !== $_SESSION['csrf_token']) {
            echo json_encode(['error' => 'Invalid CSRF token']);
            exit;
        }
        $session_id = intval($data['session_id'] ?? 0);
        $scores = $data['scores'] ?? [];
        if (!$session_id || !is_array($scores)) {
            echo json_encode(['error' => 'Missing session id or scores']);
            exit;
        }
        // Upsert for each hole (update if exists, insert if not)
        foreach ($scores as $row) {
            $hole_number = intval($row['hole_number']);
            $score = is_null($row['score']) || $row['score']==='' ? null : intval($row['score']);
            // Skip holes with no score
            if ($score === null) continue;
            // Try update first
            $stmt = $pdo->prepare("UPDATE rounds SET score = ? WHERE round_session_id = ? AND hole_number = ?");
            $stmt->execute([$score, $session_id, $hole_number]);
            if ($stmt->rowCount() === 0) {
                // If nothing updated, insert
                $user_id = $_SESSION['user_id'];
                $course_id = 1;
                $insert = $pdo->prepare("INSERT INTO rounds (user_id, course_id, hole_number, score, round_session_id) VALUES (?, ?, ?, ?, ?)");
                $insert->execute([$user_id, $course_id, $hole_number, $score, $session_id]);
            }
        }
        echo json_encode(['success' => true]);
        break;

    case 'delete_round':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['error' => 'Not logged in']);
            exit;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        if (($data['csrf_token'] ?? null) !== $_SESSION['csrf_token']) {
            echo json_encode(['error' => 'Invalid CSRF token']);
            exit;
        }
        $session_id = intval($data['session_id'] ?? 0);
        if (!$session_id) {
            echo json_encode(['error' => 'No session id']);
            exit;
        }
        $pdo->prepare("DELETE FROM rounds WHERE round_session_id = ?")->execute([$session_id]);
        $pdo->prepare("DELETE FROM round_sessions WHERE id = ?")->execute([$session_id]);
        echo json_encode(['success' => true]);
        break;
    // ------------- END ROUND EDIT/DELETE SUPPORT -----------

    case 'get_csrf_token':
        echo json_encode(['csrf_token' => $_SESSION['csrf_token']]);
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
}
?>