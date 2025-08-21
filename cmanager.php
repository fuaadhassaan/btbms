<?php
session_start();
header('Content-Type: application/json');

// --- DB CONFIG ---
$host = 'localhost';
$user = 'root';
$pass = ''; // Set your password
$dbname = 'btbms'; // Set your DB name

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die(json_encode(['status'=>'error', 'message'=>'DB connection failed']));
}

// --- AJAX API SECTION ---
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action === 'get_trips') {
    // Return all daily trips for the Manage Trips page
    $sql = "SELECT 
                trip_id, travel_date, departure_time, bus_id, origin, destination, fare, arrival_time, 
                (28 - (SELECT COUNT(*) FROM tickets WHERE tickets.trip_id = trips.trip_id)) as available_seats
            FROM trips
            ORDER BY travel_date, departure_time";
    $result = $conn->query($sql);
    $trips = [];
    while ($row = $result->fetch_assoc()) {
        $trips[] = $row;
    }
    echo json_encode($trips);
    exit();
}

if ($action === 'overview_trip') {
    $trip_id = $conn->real_escape_string($_POST['trip_id']);
    $q = $conn->query("SELECT COUNT(*) as booked FROM tickets WHERE trip_id='$trip_id'");
    $booked = $q->fetch_assoc()['booked'];
    $remaining = 28 - intval($booked);
    echo json_encode([
        'trip_id' => $trip_id,
        'booked' => intval($booked),
        'remaining' => $remaining
    ]);
    exit();
}

if ($action === 'create_ticket') {
    $trip_id = $conn->real_escape_string($_POST['trip_id']);
    $seat_number = $conn->real_escape_string($_POST['seat_number']);
    $passenger_name = $conn->real_escape_string($_POST['passenger_name']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $email = $conn->real_escape_string($_POST['email']);
    $address = $conn->real_escape_string($_POST['address']);
    $boarding_point = $conn->real_escape_string($_POST['boarding_point']);
    $dropping_point = $conn->real_escape_string($_POST['dropping_point']);
    $payment_method = $conn->real_escape_string($_POST['payment_method']);
    $payment_info = $conn->real_escape_string($_POST['payment_info']);
    $booking_time = date('Y-m-d H:i:s');

    // Check for duplicate seat for same trip
    $dupe = $conn->query("SELECT ticket_id FROM tickets WHERE trip_id='$trip_id' AND seat_number='$seat_number'");
    if ($dupe->num_rows > 0) {
        echo json_encode(['status'=>'error', 'message'=>'Seat already booked']);
        exit();
    }

    $sql = "INSERT INTO tickets
        (trip_id, seat_number, passenger_name, phone, email, address, boarding_point, dropping_point, payment_method, payment_info, booking_time)
        VALUES
        ('$trip_id', '$seat_number', '$passenger_name', '$phone', '$email', '$address', '$boarding_point', '$dropping_point', '$payment_method', '$payment_info', '$booking_time')";
    if ($conn->query($sql)) {
        echo json_encode(['status'=>'success', 'ticket_id'=>$conn->insert_id]);
    } else {
        echo json_encode(['status'=>'error', 'message'=>'Create failed']);
    }
    exit();
}

if ($action === 'get_ticket') {
    $ticket_id = $conn->real_escape_string($_POST['ticket_id']);
    $result = $conn->query("SELECT * FROM tickets WHERE ticket_id='$ticket_id' LIMIT 1");
    if ($row = $result->fetch_assoc()) {
        echo json_encode($row);
    } else {
        echo json_encode([]);
    }
    exit();
}

if ($action === 'update_ticket') {
    $ticket_id = $conn->real_escape_string($_POST['ticket_id']);
    $trip_id = $conn->real_escape_string($_POST['trip_id']);
    $seat_number = $conn->real_escape_string($_POST['seat_number']);
    // Check for duplicate seat for same trip (excluding current ticket)
    $dupe = $conn->query("SELECT ticket_id FROM tickets WHERE trip_id='$trip_id' AND seat_number='$seat_number' AND ticket_id!='$ticket_id'");
    if ($dupe->num_rows > 0) {
        echo json_encode(['status'=>'error', 'message'=>'Seat already booked']);
        exit();
    }
    $sql = "UPDATE tickets SET trip_id='$trip_id', seat_number='$seat_number' WHERE ticket_id='$ticket_id'";
    if ($conn->query($sql)) {
        echo json_encode(['status'=>'success']);
    } else {
        echo json_encode(['status'=>'error', 'message'=>'Update failed']);
    }
    exit();
}

if ($action === 'delete_ticket') {
    $ticket_id = $conn->real_escape_string($_POST['ticket_id']);
    $sql = "DELETE FROM tickets WHERE ticket_id='$ticket_id'";
    if ($conn->query($sql)) {
        echo json_encode(['status'=>'success']);
    } else {
        echo json_encode(['status'=>'error', 'message'=>'Delete failed']);
    }
    exit();
}

// Admin Request
if ($action === 'request_admin') {
    $subject = $conn->real_escape_string($_POST['subject']);
    $details = $conn->real_escape_string($_POST['details']);
    $requested_by = $conn->real_escape_string($_POST['requested_by']);
    if (empty($subject) || empty($details) || empty($requested_by)) {
        echo json_encode(['status'=>'error', 'message'=>'All fields required']);
        exit();
    }
    $sql = "INSERT INTO admin_requests (subject, details, requested_by, requested_at)
            VALUES ('$subject', '$details', '$requested_by', NOW())";
    if ($conn->query($sql)) {
        echo json_encode(['status'=>'success']);
    } else {
        echo json_encode(['status'=>'error', 'message'=>'Request failed']);
    }
    exit();
}

// Fallback
echo json_encode(['status'=>'error', 'message'=>'Invalid request']);
exit();
?>
