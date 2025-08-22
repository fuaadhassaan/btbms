<?php
declare(strict_types=1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header('Content-Type: application/json; charset=utf-8');

try {
    $trip_id = trim($_POST['trip_id'] ?? '');
    if ($trip_id === '') {
        echo json_encode([]);
        exit;
    }

    $conn = new mysqli("localhost", "root", "", "btbms");
    $conn->set_charset('utf8mb4');

    $stmt = $conn->prepare("SELECT seat_number FROM tickets WHERE trip_id = ?");
    $stmt->bind_param("s", $trip_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $booked = [];
    while ($row = $res->fetch_assoc()) {
        // If you store one seat per row, this is fine.
        // If you ever store CSV of seats, split here.
        $booked[] = $row['seat_number'];
    }
    echo json_encode($booked);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'detail' => $e->getMessage()]);
}
