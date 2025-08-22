<?php
// Error reporting and debug logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

$conn = new mysqli("localhost", "root", "", "btbms");
if ($conn->connect_error) {
    file_put_contents("php_error.log", "DB error: " . $conn->connect_error);
    die(json_encode(["status" => "error", "message" => "DB connection failed: " . $conn->connect_error]));
}
$conn->set_charset('utf8mb4');

// Debug: log incoming POST data (optional)
file_put_contents("post_debug.txt", print_r($_POST, true));

// Helper
function clean($val) { return trim((string)($val ?? "")); }

$required = ['trip_id', 'seats', 'name', 'phone', 'email', 'address', 'boarding', 'dropping', 'paymentMethod'];
foreach ($required as $field) {
    if (!isset($_POST[$field]) || clean($_POST[$field]) === "") {
        die(json_encode(["status" => "error", "message" => "Missing or empty field: $field"]));
    }
}

$trip_id = clean($_POST['trip_id']);
$seats = json_decode($_POST['seats'], true);
$name = clean($_POST['name']);
$phone = clean($_POST['phone']);
$email = clean($_POST['email']);
$address = clean($_POST['address']);
$boarding = clean($_POST['boarding']);
$dropping = clean($_POST['dropping']);
$payment_method = clean($_POST['paymentMethod']);
$payment_info = "";

// Payment info collection
if ($payment_method === "Bkash") {
    $payment_info = "Bkash: " . clean($_POST['bkash_number'] ?? '') . ", Txn: " . clean($_POST['bkash_txn'] ?? '');
} elseif ($payment_method === "Card") {
    $payment_info = "Card: " . clean($_POST['card_number'] ?? '') . ", Exp: " . clean($_POST['expiry'] ?? '') . ", CVC: " . clean($_POST['cvc'] ?? '');
}

file_put_contents("trip_debug.txt", "TripID: [$trip_id]\nSeats: " . print_r($seats, true) . "\n", FILE_APPEND);

// Ensure exact trip exists
$stmt_check = $conn->prepare("SELECT trip_id FROM trips WHERE trip_id = ?");
$stmt_check->bind_param("s", $trip_id);
$stmt_check->execute();
$stmt_check->store_result();
if ($stmt_check->num_rows === 0) {
    die(json_encode(["status" => "error", "message" => "Trip ID '$trip_id' does not exist in trips table!"]));
}
$stmt_check->close();

if (!is_array($seats) || count($seats) === 0) {
    die(json_encode(["status" => "error", "message" => "No seats selected"]));
}

// Insert tickets seat-by-seat (one seat per row)
foreach ($seats as $seat) {
    $seat_clean = clean($seat);

    // Avoid double booking
    $check_seat = $conn->prepare("SELECT ticket_id FROM tickets WHERE trip_id = ? AND seat_number = ?");
    $check_seat->bind_param("ss", $trip_id, $seat_clean);
    $check_seat->execute();
    $check_seat->store_result();
    if ($check_seat->num_rows > 0) {
        $check_seat->close();
        die(json_encode(["status" => "error", "message" => "Seat $seat_clean already booked for this trip!"]));
    }
    $check_seat->close();

    $stmt = $conn->prepare("
        INSERT INTO tickets (
            trip_id, seat_number, passenger_name, phone, email, address,
            boarding_point, dropping_point, payment_method, payment_info
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        file_put_contents("php_error.log", "Prepare failed: " . $conn->error);
        die(json_encode(["status" => "error", "message" => "Prepare failed: " . $conn->error]));
    }

    $stmt->bind_param(
        "ssssssssss",
        $trip_id, $seat_clean, $name, $phone, $email, $address,
        $boarding, $dropping, $payment_method, $payment_info
    );

    if (!$stmt->execute()) {
        file_put_contents("php_error.log", "Execute failed: " . $stmt->error);
        die(json_encode(["status" => "error", "message" => "Execute failed: " . $stmt->error]));
    }
    $stmt->close();
}

echo json_encode(["status" => "success"]);
