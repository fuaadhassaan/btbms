<?php
// local-book-seat.php
// Run this on your local XAMPP server (place in htdocs or serve from the same folder).
// Receives JSON: { trip_id: 'TR1', seats: ['A1','A2'], passenger_name: 'Name' }
// Inserts rows into `bookings` table and runs export-db.ps1 to update data/db.json

header('Content-Type: application/json');

// Restrict to localhost for safety
$remote = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remote, ['127.0.0.1', '::1', 'localhost'])) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data || !isset($data['trip_id']) || !isset($data['seats']) || !is_array($data['seats'])) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_request']);
    exit;
}

$trip_id = $data['trip_id'];
$seats = $data['seats'];
$passenger = isset($data['passenger_name']) ? $data['passenger_name'] : '';

// DB config - edit if your local DB uses a password or different host
$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';
$dbName = 'btbms';

$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'db_connect', 'msg' => $mysqli->connect_error]);
    exit;
}

$mysqli->begin_transaction();
try {
    $stmt = $mysqli->prepare('INSERT INTO bookings (trip_id, seat_label, passenger_name) VALUES (?, ?, ?)');
    foreach ($seats as $s) {
        $stmt->bind_param('sss', $trip_id, $s, $passenger);
        $stmt->execute();
        if ($stmt->errno) throw new Exception('insert error: '.$stmt->error);
    }
    $stmt->close();
    $mysqli->commit();
} catch (Exception $e) {
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode(['error'=>'insert_failed','msg'=>$e->getMessage()]);
    exit;
}

$out = null;
// Path to export-db.ps1 - change this if your repo is elsewhere
$exportScript = 'C:\\Users\\fuaad\\OneDrive\\Documents\\btbms-main\\export-db.ps1';
if (file_exists($exportScript)) {
    // Run PowerShell script to update data/db.json and push
    $ps = 'powershell -ExecutionPolicy Bypass -File '.escapeshellarg($exportScript);
    $out = shell_exec($ps . ' 2>&1');
}

echo json_encode(['success'=>true,'inserted'=>count($seats),'export_output'=>$out]);

?>
