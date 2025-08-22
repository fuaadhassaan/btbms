<?php
// search_trips.php
header('Content-Type: application/json');

$host = "localhost";
$username = "root";
$password = "";
$database = "btbms";

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

function clean($s) { return trim($s ?? ""); }

$from = clean($_POST['from'] ?? '');
$to   = clean($_POST['to']   ?? '');
$date = clean($_POST['date'] ?? '');

// Fallback to today if the browser somehow sends nothing
if ($date === '') {
    $date = date('Y-m-d');
}

// If browser/datepicker shows dd/mm/yyyy in UI, the value it POSTs is still yyyy-mm-dd.
// Just in case someone posts dd/mm/yyyy manually, convert it.
if (preg_match('~^\d{2}/\d{2}/\d{4}$~', $date)) {
    [$d, $m, $y] = explode('/', $date);
    $date = sprintf('%04d-%02d-%02d', (int)$y, (int)$m, (int)$d);
}

if ($from === '' || $to === '') {
    echo json_encode([]);
    exit;
}

// end date = selected date + 60 days
$end = date('Y-m-d', strtotime($date . ' +60 days'));

// IMPORTANT: compute booked seats per trip, then remaining seats.
// We use trips.available_seats when present, else assume 28 (as in your dataset).
$sql = "
SELECT
    t.trip_id,
    t.travel_date,
    t.departure_time,
    t.arrival_time,
    t.bus_id,
    t.origin,
    t.destination,
    t.fare,
    GREATEST(0, COALESCE(t.available_seats, 28) - COALESCE(x.booked, 0)) AS available_seats
FROM trips t
LEFT JOIN (
    SELECT trip_id, COUNT(*) AS booked
    FROM tickets
    GROUP BY trip_id
) x ON x.trip_id = t.trip_id
WHERE
    t.origin = ?
    AND t.destination = ?
    AND t.travel_date BETWEEN ? AND ?
ORDER BY t.travel_date ASC, t.departure_time ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $from, $to, $date, $end);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = $row;
}

echo json_encode($rows);
