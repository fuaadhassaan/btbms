<?php
// cmanager.php — API for Counter Manager dashboard
declare(strict_types=1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
error_reporting(E_ALL);

function respond($payload, int $code = 200) {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload);
  exit;
}
function db(): mysqli {
  $conn = new mysqli("localhost", "root", "", "btbms");
  $conn->set_charset('utf8mb4');
  return $conn;
}
function clean($v): string { return trim((string)($v ?? '')); }

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/* ---------- GET ALL TRIPS (with available seats) ---------- */
if ($action === 'get_trips') {
  try {
    $conn = db();

    $hasSeatCol = false;
    try { $conn->query("SELECT total_seats FROM buses LIMIT 1"); $hasSeatCol = true; } catch (Throwable $e) {}

    $sql = "SELECT trip_id, bus_id, origin, destination, departure_time, arrival_time, fare, travel_date
              FROM trips
             ORDER BY CAST(SUBSTRING(trip_id, 3) AS UNSIGNED), trip_id";
    $res = $conn->query($sql);

    $rows = [];
    while ($t = $res->fetch_assoc()) {
      $trip_id = $t['trip_id'];
      $capacity = 28;
      if ($hasSeatCol) {
        $stmtC = $conn->prepare("SELECT b.total_seats FROM trips t JOIN buses b ON b.bus_id = t.bus_id WHERE t.trip_id = ? LIMIT 1");
        $stmtC->bind_param('s', $trip_id);
        $stmtC->execute();
        $stmtC->bind_result($cap);
        if ($stmtC->fetch() && (int)$cap > 0) $capacity = (int)$cap;
        $stmtC->close();
      }
      $stmtB = $conn->prepare("SELECT COUNT(*) FROM tickets WHERE trip_id = ?");
      $stmtB->bind_param('s', $trip_id);
      $stmtB->execute();
      $stmtB->bind_result($booked);
      $stmtB->fetch();
      $stmtB->close();

      $t['available_seats'] = max(0, $capacity - (int)$booked);
      $rows[] = $t;
    }
    respond($rows);
  } catch (Throwable $e) {
    respond(['status'=>'error','message'=>$e->getMessage()], 500);
  }
}

/* ---------- OVERVIEW TRIP (trip_id -> booked / remaining) ---------- */
if ($action === 'overview_trip') {
  try {
    $trip_id = clean($_POST['trip_id'] ?? '');
    if ($trip_id === '') respond(['error' => 'trip_id required'], 400);

    $conn = db();

    $capacity = 28;
    try {
      $stmt = $conn->prepare("SELECT b.total_seats FROM trips t JOIN buses b ON b.bus_id = t.bus_id WHERE t.trip_id = ? LIMIT 1");
      $stmt->bind_param('s', $trip_id);
      $stmt->execute();
      $stmt->bind_result($cap);
      if ($stmt->fetch() && (int)$cap > 0) $capacity = (int)$cap;
      $stmt->close();
    } catch (Throwable $e) {}

    $stmt2 = $conn->prepare("SELECT COUNT(*) FROM tickets WHERE trip_id = ?");
    $stmt2->bind_param('s', $trip_id);
    $stmt2->execute();
    $stmt2->bind_result($booked);
    $stmt2->fetch();
    $stmt2->close();

    $remaining = max(0, $capacity - (int)$booked);
    respond([
      'trip_id'   => $trip_id,
      'booked'    => (int)$booked,
      'remaining' => $remaining,
      'capacity'  => $capacity
    ]);
  } catch (Throwable $e) {
    respond(['status'=>'error','message'=>$e->getMessage()], 500);
  }
}

/* ---------- LIST TICKETS (all) ---------- */
if ($action === 'tickets_all') {
  try {
    $conn = db();
    $sql = "SELECT ticket_id, trip_id, seat_number, passenger_name, phone, email, address,
                   boarding_point, dropping_point, payment_method, payment_info, booking_time
              FROM tickets
             ORDER BY booking_time DESC, ticket_id DESC";
    $res = $conn->query($sql);
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    respond($rows);
  } catch (Throwable $e) {
    respond(['status'=>'error','message'=>$e->getMessage()], 500);
  }
}

/* ---------- LIST TICKETS (by trip) ---------- */
if ($action === 'tickets_by_trip') {
  try {
    $trip_id = clean($_POST['trip_id'] ?? '');
    if ($trip_id === '') respond([], 200);

    $conn = db();
    $stmt = $conn->prepare(
      "SELECT ticket_id, trip_id, seat_number, passenger_name, phone, email, address,
              boarding_point, dropping_point, payment_method, payment_info, booking_time
         FROM tickets
        WHERE trip_id = ?
        ORDER BY booking_time DESC, ticket_id DESC"
    );
    $stmt->bind_param('s', $trip_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();

    respond($rows);
  } catch (Throwable $e) {
    respond(['status'=>'error','message'=>$e->getMessage()], 500);
  }
}

/* ---------- CREATE TICKET ---------- */
if ($action === 'create_ticket') {
  try {
    $conn = db();

    $trip_id        = clean($_POST['trip_id'] ?? '');
    $seat_number    = clean($_POST['seat_number'] ?? '');
    $passenger_name = clean($_POST['passenger_name'] ?? '');
    $phone          = clean($_POST['phone'] ?? '');
    $email          = clean($_POST['email'] ?? '');
    $address        = clean($_POST['address'] ?? '');
    $boarding_point = clean($_POST['boarding_point'] ?? '');
    $dropping_point = clean($_POST['dropping_point'] ?? '');
    $payment_method = clean($_POST['payment_method'] ?? '');
    $payment_info   = clean($_POST['payment_info'] ?? '');

    if ($trip_id === '' || $seat_number === '') {
      respond(['status'=>'error','message'=>'trip_id and seat_number are required'], 400);
    }

    // prevent double-booking of same seat on a trip
    $stmtChk = $conn->prepare("SELECT COUNT(*) FROM tickets WHERE trip_id = ? AND seat_number = ?");
    $stmtChk->bind_param('ss', $trip_id, $seat_number);
    $stmtChk->execute();
    $stmtChk->bind_result($exists);
    $stmtChk->fetch();
    $stmtChk->close();
    if ($exists > 0) respond(['status'=>'error','message'=>'Seat already booked for this trip'], 409);

    $stmt = $conn->prepare(
      "INSERT INTO tickets
       (trip_id, seat_number, passenger_name, phone, email, address,
        boarding_point, dropping_point, payment_method, payment_info, booking_time)
       VALUES (?,?,?,?,?,?,?,?,?,?,NOW())"
    );
    $stmt->bind_param(
      'ssssssssss',
      $trip_id, $seat_number, $passenger_name, $phone, $email, $address,
      $boarding_point, $dropping_point, $payment_method, $payment_info
    );
    $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();

    respond(['status'=>'success','ticket_id'=>$newId]);
  } catch (Throwable $e) {
    respond(['status'=>'error','message'=>$e->getMessage()], 500);
  }
}

/* ---------- GET ONE TICKET ---------- */
if ($action === 'get_ticket') {
  try {
    $ticket_id = (int)($_POST['ticket_id'] ?? 0);
    if ($ticket_id <= 0) respond([], 200);

    $conn = db();
    $stmt = $conn->prepare("SELECT * FROM tickets WHERE ticket_id = ? LIMIT 1");
    $stmt->bind_param('i', $ticket_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc() ?: [];
    $stmt->close();

    respond($row);
  } catch (Throwable $e) {
    respond(['status'=>'error','message'=>$e->getMessage()], 500);
  }
}

/* ---------- UPDATE TICKET (trip_id, seat_number only) ---------- */
if ($action === 'update_ticket') {
  try {
    $ticket_id   = (int)($_POST['ticket_id'] ?? 0);
    $trip_id     = clean($_POST['trip_id'] ?? '');
    $seat_number = clean($_POST['seat_number'] ?? '');

    if ($ticket_id <= 0 || $trip_id === '' || $seat_number === '') {
      respond(['status'=>'error','message'=>'ticket_id, trip_id and seat_number are required'], 400);
    }

    $conn = db();

    $stmtChk = $conn->prepare("SELECT COUNT(*) FROM tickets WHERE trip_id = ? AND seat_number = ? AND ticket_id <> ?");
    $stmtChk->bind_param('ssi', $trip_id, $seat_number, $ticket_id);
    $stmtChk->execute();
    $stmtChk->bind_result($exists);
    $stmtChk->fetch();
    $stmtChk->close();
    if ($exists > 0) respond(['status'=>'error','message'=>'Seat already booked for this trip'], 409);

    $stmt = $conn->prepare("UPDATE tickets SET trip_id = ?, seat_number = ? WHERE ticket_id = ?");
    $stmt->bind_param('ssi', $trip_id, $seat_number, $ticket_id);
    $stmt->execute();
    $stmt->close();

    respond(['status'=>'success']);
  } catch (Throwable $e) {
    respond(['status'=>'error','message'=>$e->getMessage()], 500);
  }
}

/* ---------- REQUEST TO ADMIN (works with admin_requests OR admin_request) ---------- */
if ($action === 'request_admin' || isset($_POST['request_admin'])) {
  try {
    $subject      = clean($_POST['subject'] ?? '');
    $details      = clean($_POST['details'] ?? '');
    // Prefer explicit requested_by; fallback to userid if a legacy non-AJAX post is used.
    $requested_by = clean($_POST['requested_by'] ?? ($_POST['userid'] ?? 'unknown'));

    if ($subject === '' || $details === '') {
      respond(['status'=>'error','message'=>'subject and details are required'], 400);
    }

    $conn = db();

    // Decide which table to use: prefer existing one
    $table = null;
    $q1 = $conn->query("SHOW TABLES LIKE 'admin_requests'");
    if ($q1->num_rows > 0) $table = 'admin_requests';
    $q1->close();

    if ($table === null) {
      $q2 = $conn->query("SHOW TABLES LIKE 'admin_request'");
      if ($q2->num_rows > 0) $table = 'admin_request';
      $q2->close();
    }

    // If none exist
    if ($table === null) {
      $conn->query("
        CREATE TABLE admin_requests (
          id INT AUTO_INCREMENT PRIMARY KEY,
          subject VARCHAR(255) NOT NULL,
          details TEXT NOT NULL,
          requested_by VARCHAR(100) NOT NULL,
          status VARCHAR(50) DEFAULT 'Pending',
          requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
      ");
      $table = 'admin_requests';
    }

    // Read actual columns of the chosen table
    $cols = [];
    $resCols = $conn->query("SHOW COLUMNS FROM `$table`");
    while ($row = $resCols->fetch_assoc()) $cols[] = $row['Field'];
    $resCols->close();

    // Build INSERT list only with columns that exist; let timestamp default fill itself.
    $insertCols = [];
    $params = [];
    $types = '';

    if (in_array('subject', $cols, true))      { $insertCols[] = 'subject';      $params[] = $subject;      $types .= 's'; }
    if (in_array('details', $cols, true))      { $insertCols[] = 'details';      $params[] = $details;      $types .= 's'; }
    // requested_by (or common alternatives)
    $requestedByCol = null;
    foreach (['requested_by','counter_manager_id','manager_id','user_id'] as $cand) {
      if (in_array($cand, $cols, true)) { $requestedByCol = $cand; break; }
    }
    if ($requestedByCol) { $insertCols[] = $requestedByCol; $params[] = $requested_by; $types .= 's'; }
    // status if present -> set default Pending
    if (in_array('status', $cols, true))       { $insertCols[] = 'status';       $params[] = 'Pending';     $types .= 's'; }

    if (count($insertCols) < 2) {
      respond(['status'=>'error','message'=>'admin_requests table lacks required columns'], 500);
    }

    $placeholders = implode(',', array_fill(0, count($insertCols), '?'));
    $colList = '`' . implode('`,`', $insertCols) . '`';
    $sql = "INSERT INTO `$table` ($colList) VALUES ($placeholders)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();

    if ($newId <= 0) respond(['status'=>'error','message'=>'Insert failed'], 500);

    respond(['status'=>'success','id'=>$newId,'table_used'=>$table]);
  } catch (Throwable $e) {
    respond(['status'=>'error','message'=>$e->getMessage()], 500);
  }
}

/* ---------- Default: nothing matched ---------- */
respond(['status'=>'error','message'=>'Unknown action'], 400);
