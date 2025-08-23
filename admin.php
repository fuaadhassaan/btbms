<?php
session_start();

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "btbms";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli($host, $user, $pass, $dbname);
$conn->set_charset('utf8mb4');

function json_out($arr) {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($arr);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // ---------------- LOGIN ----------------
  if (isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $userid   = $_POST['userid'] ?? '';
    $password = $_POST['password'] ?? '';
    if ($username === 'admin' && $userid === 'AD' && $password === '123') {
      $_SESSION['admin_logged_in'] = true;
      json_out(['status' => 'success']);
    } else {
      json_out(['status' => 'fail']);
    }
  }

  // Block actions when not logged in
  if (!isset($_SESSION['admin_logged_in'])) {
    json_out(['status' => 'unauthorized']);
  }

  $action = $_POST['action'] ?? '';

  // ---------------- TRIPS: OVERVIEW (with realtime available seats) ----------------
  if ($action === 'trip_overview') {
    $sql = "
      SELECT
        trip_id, bus_id, origin, destination, departure_time, arrival_time, fare, travel_date,
        (28 - (SELECT COUNT(*) FROM tickets WHERE tickets.trip_id = trips.trip_id)) AS available_seats
      FROM trips
      ORDER BY travel_date, departure_time
    ";
    $result = $conn->query($sql);

    echo "<table><tr>
      <th>trip_id</th><th>bus_id</th><th>origin</th><th>destination</th>
      <th>departure_time</th><th>arrival_time</th><th>fare</th>
      <th>travel_date</th><th>available_seats</th>
    </tr>";
    while ($row = $result->fetch_assoc()) {
      echo "<tr>
        <td>{$row['trip_id']}</td>
        <td>{$row['bus_id']}</td>
        <td>{$row['origin']}</td>
        <td>{$row['destination']}</td>
        <td>{$row['departure_time']}</td>
        <td>{$row['arrival_time']}</td>
        <td>{$row['fare']}</td>
        <td>{$row['travel_date']}</td>
        <td>{$row['available_seats']}</td>
      </tr>";
    }
    echo "</table>";
    exit;
  }

  // ---------------- TRIPS: CREATE (form) ----------------
  if ($action === 'trip_create') {
    echo <<<HTML
    <form id="createTripForm">
      <label for="trip_id">Trip ID</label><br/>
      <input type="text" name="trip_id" placeholder="Trip ID" required><br/>

      <label for="bus_id">Bus ID</label><br/>
      <input type="text" name="bus_id" placeholder="Bus ID" required><br/>

      <label for="origin">Origin</label><br/>
      <select name="origin" required> <br/>
        <option value="" disabled selected>-- Select Origin --</option> <br/>
        <option value="Dhaka">Dhaka</option>
        <option value="Chittagong">Chittagong</option>
        <option value="Sylhet">Sylhet</option>
      </select><br/>

      <label for="destination">Destination</label><br/>
      <select name="destination" required> <br/>
        <option value="" disabled selected>-- Select Destination --</option> <br/>
        <option value="Dhaka">Dhaka</option>
        <option value="Chittagong">Chittagong</option>
        <option value="Sylhet">Sylhet</option>
      </select><br/>

      <label for="travel_date">Travel Date</label><br/>
      <input type="date" name="travel_date" required><br/>

      <label for="departure_time">Departure Time</label><br/>
      <input type="time" name="departure_time" required><br/>

      <label for="arrival_time">Arrival Time</label><br/>
      <input type="time" name="arrival_time" required><br/>

      <label for="fare">Fare (BDT)</label><br/>
      <input type="number" name="fare" min="0" step="1" placeholder="Fare" required><br/>

      <button type="submit">Confirm</button>
    </form>
    <div id="createTripMsg"></div>
HTML;
    exit;
  }

  // ---------------- TRIPS: CREATE (confirm) ----------------
  if ($action === 'trip_create_confirm') {
    $trip_id        = trim($_POST['trip_id'] ?? '');
    $bus_id         = trim($_POST['bus_id'] ?? '');

    // Dropdown origin + validation
    $origin = trim($_POST['origin'] ?? '');
    $allowedOrigins = ['Dhaka','Chittagong','Sylhet'];
    if (!in_array($origin, $allowedOrigins, true)) {
      echo "<span style='color:red;'>Invalid origin selected.</span>";
      exit;
    }

    $destination    = trim($_POST['destination'] ?? '');
    $travel_date    = trim($_POST['travel_date'] ?? '');
    $departure_time = trim($_POST['departure_time'] ?? '');
    $arrival_time   = trim($_POST['arrival_time'] ?? '');
    $fare           = (int)($_POST['fare'] ?? 0);

    // sanity defaults for old schemas
    $available_seats = 28;

    $stmt = $conn->prepare(
      "INSERT INTO trips
        (trip_id, bus_id, origin, destination, departure_time, arrival_time, fare, travel_date, available_seats)
       VALUES (?,?,?,?,?,?,?,?,?)"
    );
    $stmt->bind_param('ssssssisi',
      $trip_id, $bus_id, $origin, $destination, $departure_time, $arrival_time, $fare, $travel_date, $available_seats
    );

    try {
      $stmt->execute();
      echo "<span style='color:green;'>Trip created successfully!</span>";
    } catch (Throwable $e) {
      echo "<span style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</span>";
    }
    exit;
  }

  // ---------------- TRIPS: UPDATE (search form) ----------------
  if ($action === 'trip_update') {
    echo <<<HTML
      <form id="updateTripSearchForm">
        <label for="trip_id">Trip ID</label><br/>
        <input type="text" name="trip_id" placeholder="Enter Trip ID" required><br/>
        <button type="submit">Search</button>
      </form>
      <div id="updateTripFields"></div>
      <div id="updateTripMsg"></div>
HTML;
    exit;
  }

  // ---------------- TRIPS: UPDATE (load form) ----------------
  if ($action === 'trip_update_form') {
    $trip_id = trim($_POST['trip_id'] ?? '');
    $stmt = $conn->prepare("SELECT * FROM trips WHERE trip_id=?");
    $stmt->bind_param('s', $trip_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row) {
      $dep = htmlspecialchars($row['departure_time'] ?? '');
      $arr = htmlspecialchars($row['arrival_time'] ?? '');
      $fare = htmlspecialchars($row['fare'] ?? '');
      $originOptions = function($current){
        $opts = ['Dhaka','Chittagong','Sylhet'];
        $html = '';
        foreach ($opts as $o) {
          $sel = ($o === $current) ? 'selected' : '';
          $html .= "<option value=\"$o\" $sel>$o</option>";
        }
        return $html;
      };
      $originSelect = $originOptions($row['origin'] ?? '');

      echo <<<HTML
      <form id="updateTripForm">
        <label for="trip_id">Trip ID</label><br/>
        <input type="text" name="trip_id" value="{$row['trip_id']}" required><br/>

        <label for="bus_id">Bus ID</label><br/>
        <input type="text" name="bus_id" value="{$row['bus_id']}" required><br/>

        <label for="origin">Origin</label><br/>
        <select name="origin" required>
          $originSelect
        </select><br/>

        <label for="destination">Destination</label><br/>
        <input type="text" name="destination" value="{$row['destination']}" required><br/>

        <label for="travel_date">Travel Date</label><br/>
        <input type="date" name="travel_date" value="{$row['travel_date']}" required><br/>

        <label for="departure_time">Departure Time</label><br/>
        <input type="time" name="departure_time" value="{$dep}" required><br/>

        <label for="arrival_time">Arrival Time</label><br/>
        <input type="time" name="arrival_time" value="{$arr}" required><br/>

        <label for="fare">Fare (BDT)</label><br/>
        <input type="number" name="fare" min="0" step="1" value="{$fare}" required><br/>

        <button type="submit">Confirm</button>
      </form>
HTML;
    } else {
      echo "<span style='color:red;'>Trip not found.</span>";
    }
    exit;
  }

  // ---------------- TRIPS: UPDATE (confirm) ----------------
  if ($action === 'trip_update_confirm') {
    $trip_id        = trim($_POST['trip_id'] ?? '');
    $bus_id         = trim($_POST['bus_id'] ?? '');
    $origin         = trim($_POST['origin'] ?? '');
    $destination    = trim($_POST['destination'] ?? '');
    $travel_date    = trim($_POST['travel_date'] ?? '');
    $departure_time = trim($_POST['departure_time'] ?? '');
    $arrival_time   = trim($_POST['arrival_time'] ?? '');
    $fare           = (int)($_POST['fare'] ?? 0);

    // validate origin again
    if (!in_array($origin, ['Dhaka','Chittagong', 'Sylhet'], true)) {
      echo "<span style='color:red;'>Invalid origin selected.</span>";
      exit;
    }
    if (!in_array($destination, ['Dhaka','Chittagong', 'Sylhet'], true)) {
      echo "<span style='color:red;'>Invalid destination selected.</span>";
      exit;
    }

    $stmt = $conn->prepare(
      "UPDATE trips
       SET bus_id=?, origin=?, destination=?, travel_date=?, departure_time=?, arrival_time=?, fare=?
       WHERE trip_id=?"
    );
    $stmt->bind_param('ssssssis',
      $bus_id, $origin, $destination, $travel_date, $departure_time, $arrival_time, $fare, $trip_id
    );

    try {
      $stmt->execute();
      echo "<span style='color:green;'>Trip updated successfully!</span>";
    } catch (Throwable $e) {
      echo "<span style='color:red;'>Update failed: " . htmlspecialchars($e->getMessage()) . "</span>";
    }
    exit;
  }

  // ---------------- TRIPS: DELETE (search form) ----------------
  if ($action === 'trip_delete') {
    echo <<<HTML
      <form id="deleteTripSearchForm">
        <label for="trip_id">Trip ID</label><br/>
        <input type="text" name="trip_id" placeholder="Enter Trip ID" required><br/>
        <button type="submit">Search</button>
      </form>
      <div id="deleteTripMsg"></div>
HTML;
    exit;
  }

  // ---------------- TRIPS: DELETE (confirm with safe cascade) ----------------
  if ($action === 'trip_delete_confirm') {
    $trip_id = trim($_POST['trip_id'] ?? '');

    $stmt = $conn->prepare("SELECT trip_id FROM trips WHERE trip_id=?");
    $stmt->bind_param('s', $trip_id);
    $stmt->execute();
    $tripRow = $stmt->get_result()->fetch_assoc();

    if (!$tripRow) {
      echo "<span style='color:red;'>Trip not found.</span>";
      exit;
    }

    try {
      $conn->begin_transaction();

      $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM tickets WHERE trip_id=?");
      $stmt->bind_param('s', $trip_id);
      $stmt->execute();
      $count = (int)$stmt->get_result()->fetch_assoc()['c'];

      if ($count > 0) {
        $stmt = $conn->prepare("DELETE FROM tickets WHERE trip_id=?");
        $stmt->bind_param('s', $trip_id);
        $stmt->execute();
      }

      $stmt = $conn->prepare("DELETE FROM trips WHERE trip_id=?");
      $stmt->bind_param('s', $trip_id);
      $stmt->execute();

      $conn->commit();

      $msg = $count > 0
        ? "Trip deleted successfully! Also removed $count ticket(s) linked to this trip."
        : "Trip deleted successfully!";
      echo "<span style='color:green;'>$msg</span>";
    } catch (Throwable $e) {
      $conn->rollback();
      echo "<span style='color:red;'>Delete failed: " . htmlspecialchars($e->getMessage()) . "</span>";
    }
    exit;
  }

  // ---------------- TICKETS: OVERVIEW ----------------
  if ($action === 'ticket_overview') {
    $result = $conn->query("SELECT * FROM tickets");
    echo "<table><tr>
      <th>ticket_id</th><th>trip_id</th><th>seat_number</th>
      <th>passenger_name</th><th>phone</th><th>email</th><th>address</th>
      <th>boarding_point</th><th>dropping_point</th><th>payment_method</th>
      <th>payment_info</th><th>booking_time</th>
    </tr>";
    while($row = $result->fetch_assoc()) {
      echo "<tr>
        <td>{$row['ticket_id']}</td>
        <td>{$row['trip_id']}</td>
        <td>{$row['seat_number']}</td>
        <td>{$row['passenger_name']}</td>
        <td>{$row['phone']}</td>
        <td>{$row['email']}</td>
        <td>{$row['address']}</td>
        <td>{$row['boarding_point']}</td>
        <td>{$row['dropping_point']}</td>
        <td>{$row['payment_method']}</td>
        <td>{$row['payment_info']}</td>
        <td>{$row['booking_time']}</td>
      </tr>";
    }
    echo "</table>";
    exit;
  }

  // ---------------- TICKETS: CREATE (form) ----------------
  if ($action === 'ticket_create') {
    echo <<<HTML
    <form id="createTicketForm">
      <label for="trip_id">Trip ID</label><br/>
      <input type="text" name="trip_id" placeholder="Trip ID" required><br/>
      <label for="seat_number">Seat Number</label><br/>
      <input type="text" name="seat_number" placeholder="Seat Number" required><br/>
      <label for="passenger_name">Passenger Name</label><br/>
      <input type="text" name="passenger_name" placeholder="Passenger Name" required><br/>
      <label for="phone">Phone</label><br/>
      <input type="text" name="phone" placeholder="Phone" required><br/>
      <label for="email">Email</label><br/>
      <input type="email" name="email" placeholder="Email" required><br/>
      <label for="address">Address</label><br/>
      <input type="text" name="address" placeholder="Address" required><br/>
      <label for="boarding_point">Boarding Point</label><br/>
      <input type="text" name="boarding_point" placeholder="Boarding Point" required><br/>
      <label for="dropping_point">Dropping Point</label><br/>
      <input type="text" name="dropping_point" placeholder="Dropping Point" required><br/>
      <label for="payment_method">Payment Method</label><br/>
      <input type="text" name="payment_method" placeholder="Payment Method" required><br/>
      <label for="payment_info">Payment Info</label><br/>
      <input type="text" name="payment_info" placeholder="Payment Info" required><br/>
      <button type="submit">Create</button>
    </form>
    <div id="createTicketMsg"></div>
HTML;
    exit;
  }

  // ---------------- TICKETS: CREATE (confirm) ----------------
  if ($action === 'ticket_create_confirm') {
    $trip_id = trim($_POST['trip_id'] ?? '');
    $seat_number = trim($_POST['seat_number'] ?? '');
    $passenger_name = trim($_POST['passenger_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $boarding_point = trim($_POST['boarding_point'] ?? '');
    $dropping_point = trim($_POST['dropping_point'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? '');
    $payment_info = trim($_POST['payment_info'] ?? '');

    $stmt = $conn->prepare("INSERT INTO tickets
      (trip_id, seat_number, passenger_name, phone, email, address, boarding_point, dropping_point, payment_method, payment_info)
      VALUES (?,?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param('ssssssssss',
      $trip_id, $seat_number, $passenger_name, $phone, $email, $address, $boarding_point, $dropping_point, $payment_method, $payment_info
    );

    try {
      $stmt->execute();
      echo "<span style='color:green;'>Ticket created successfully!</span>";
    } catch (Throwable $e) {
      echo "<span style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</span>";
    }
    exit;
  }

  // ---------------- TICKETS: UPDATE (search form) ----------------
  if ($action === 'ticket_update') {
    echo <<<HTML
      <form id="updateTicketSearchForm">
        <label for="ticket_id">Ticket ID</label><br/>
        <input type="text" name="ticket_id" placeholder="Enter Ticket ID" required>
        <button type="submit">Search</button>
      </form>
      <div id="updateTicketFields"></div>
      <div id="updateTicketMsg"></div>
HTML;
    exit;
  }

  // ---------------- TICKETS: UPDATE (load form) ----------------
  if ($action === 'ticket_update_form') {
    $ticket_id = trim($_POST['ticket_id'] ?? '');
    $stmt = $conn->prepare("
      SELECT t.*, tr.bus_id, tr.origin, tr.destination, tr.travel_date
      FROM tickets t LEFT JOIN trips tr ON t.trip_id=tr.trip_id
      WHERE t.ticket_id=?
    ");
    $stmt->bind_param('s', $ticket_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row) {
      echo <<<HTML
      <form id="updateTicketForm">
        <label for="ticket_id">Ticket ID</label><br/>
        <input type="text" name="ticket_id" value="{$row['ticket_id']}" required><br/>

        <label for="trip_id">Trip ID</label><br/>
        <input type="text" name="trip_id" value="{$row['trip_id']}" required><br/>

        <label for="seat_number">Seat Number</label><br/>
        <input type="text" name="seat_number" value="{$row['seat_number']}" required><br/>

        <label for="passenger_name">Passenger Name</label><br/>
        <input type="text" name="passenger_name" value="{$row['passenger_name']}" required><br/>

        <label for="phone">Phone</label><br/>
        <input type="text" name="phone" value="{$row['phone']}" required><br/>

        <label for="email">Email</label><br/>
        <input type="email" name="email" value="{$row['email']}" required><br/>

        <label for="address">Address</label><br/>
        <input type="text" name="address" value="{$row['address']}" required><br/>

        <label for="bus_id">Bus ID</label><br/>
        <input type="text" name="bus_id" value="{$row['bus_id']}" readonly><br/>

        <label for="origin">Origin</label><br/>
        <input type="text" name="origin" value="{$row['origin']}" readonly><br/>

        <label for="destination">Destination</label><br/>
        <input type="text" name="destination" value="{$row['destination']}" readonly><br/>

        <label for="travel_date">Travel Date</label><br/>
        <input type="date" name="travel_date" value="{$row['travel_date']}" readonly><br/>

        <label for="boarding_point">Boarding Point</label><br/>
        <input type="text" name="boarding_point" value="{$row['boarding_point']}" required><br/>

        <label for="dropping_point">Dropping Point</label><br/>
        <input type="text" name="dropping_point" value="{$row['dropping_point']}" required><br/>

        <label for="payment_method">Payment Method</label><br/>
        <input type="text" name="payment_method" value="{$row['payment_method']}" required><br/>

        <label for="payment_info">Payment Info</label><br/>
        <input type="text" name="payment_info" value="{$row['payment_info']}" required><br/>

        <button type="submit">Confirm</button>
      </form>
HTML;
    } else {
      echo "<span style='color:red;'>Ticket not found.</span>";
    }
    exit;
  }

  // ---------------- TICKETS: UPDATE (confirm) ----------------
  if ($action === 'ticket_update_confirm') {
    $ticket_id = trim($_POST['ticket_id'] ?? '');
    $trip_id = trim($_POST['trip_id'] ?? '');
    $seat_number = trim($_POST['seat_number'] ?? '');
    $passenger_name = trim($_POST['passenger_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $boarding_point = trim($_POST['boarding_point'] ?? '');
    $dropping_point = trim($_POST['dropping_point'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? '');
    $payment_info = trim($_POST['payment_info'] ?? '');

    $stmt = $conn->prepare("UPDATE tickets SET 
              trip_id=?, seat_number=?, passenger_name=?, phone=?, email=?, address=?,
              boarding_point=?, dropping_point=?, payment_method=?, payment_info=?
            WHERE ticket_id=?");
    $stmt->bind_param(
      'sssssssssss',
      $trip_id, $seat_number, $passenger_name, $phone, $email, $address,
      $boarding_point, $dropping_point, $payment_method, $payment_info, $ticket_id
    );

    try {
      $stmt->execute();
      echo "<span style='color:green;'>Ticket updated successfully!</span>";
    } catch (Throwable $e) {
      echo "<span style='color:red;'>Update failed: " . htmlspecialchars($e->getMessage()) . "</span>";
    }
    exit;
  }

  // ---------------- TICKETS: DELETE ----------------
  if ($action === 'ticket_delete') {
    echo <<<HTML
      <form id="deleteTicketSearchForm">
        <label for="ticket_id">Ticket ID</label><br/>
        <input type="text" name="ticket_id" placeholder="Enter Ticket ID" required><br/>
        <button type="submit">Search</button>
      </form>
      <div id="deleteTicketMsg"></div>
HTML;
    exit;
  }

  if ($action === 'ticket_delete_confirm') {
    $ticket_id = trim($_POST['ticket_id'] ?? '');
    $stmt = $conn->prepare("SELECT ticket_id FROM tickets WHERE ticket_id=?");
    $stmt->bind_param('s', $ticket_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row) {
      $stmt = $conn->prepare("DELETE FROM tickets WHERE ticket_id=?");
      $stmt->bind_param('s', $ticket_id);
      $stmt->execute();
      echo "<span style='color:green;'>Ticket deleted successfully!</span>";
    } else {
      echo "<span style='color:red;'>Ticket not found.</span>";
    }
    exit;
  }

  // ---------------- SIMPLE STATS (legacy) ----------------
  if (in_array($action, ['stats_daily', 'stats_monthly', 'stats_yearly'])) {
    $where = "";
    if ($action === 'stats_daily')   $where = "WHERE DATE(booking_time)=CURDATE()";
    if ($action === 'stats_monthly') $where = "WHERE MONTH(booking_time)=MONTH(CURDATE()) AND YEAR(booking_time)=YEAR(CURDATE())";
    if ($action === 'stats_yearly')  $where = "WHERE YEAR(booking_time)=YEAR(CURDATE())";
    $q = $conn->query("SELECT COUNT(*) as ticket_count FROM tickets $where");
    $tickets = $q->fetch_assoc()['ticket_count'];
    echo "<h3>Total Tickets: $tickets</h3>";
    echo "<canvas id='chart' width='400' height='100' style='background:#222;border-radius:10px'></canvas>
    <script>
      var c = document.getElementById('chart').getContext('2d');
      c.fillStyle = 'red';
      c.fillRect(50, 20, $tickets*10, 50);
      c.fillStyle = 'white';
      c.font = '20px Arial';
      c.fillText('Tickets', 10, 90);
      c.fillText('$tickets', 55, 55);
    </script>";
    exit;
  }

  // ---------------- COMPANY STATS (daily/monthly/yearly) ----------------
  if (preg_match('/^company_stats_(daily|monthly|yearly)$/', $action, $m)) {
    $period = $m[1];
    $trips = [];
    $tripLabels = [];
    $tickets = [];
    $revenues = [];
    $totalTrips = 0; $totalRevenue = 0;

    $result = $conn->query("SELECT trip_id, origin, destination, fare FROM trips ORDER BY trip_id");
    while($row = $result->fetch_assoc()) {
      $trips[$row['trip_id']] = $row;
      $tripLabels[] = $row['trip_id'];
      $tickets[$row['trip_id']] = 0;
      $revenues[$row['trip_id']] = 0;
    }

    if ($period === "daily") {
      $group = "DATE(t.booking_time)=CURDATE()";
    } elseif ($period === "monthly") {
      $group = "MONTH(t.booking_time)=MONTH(CURDATE()) AND YEAR(t.booking_time)=YEAR(CURDATE())";
    } else {
      $group = "YEAR(t.booking_time)=YEAR(CURDATE())";
    }

    $result = $conn->query("
      SELECT t.trip_id, COUNT(*) as ticket_count, SUM(tr.fare) as revenue
      FROM tickets t
      JOIN trips tr ON t.trip_id=tr.trip_id
      WHERE $group
      GROUP BY t.trip_id
    ");
    while($row = $result->fetch_assoc()) {
      $tickets[$row['trip_id']] = (int)$row['ticket_count'];
      $revenues[$row['trip_id']] = (int)$row['revenue'];
      $totalTrips += $row['ticket_count'];
      $totalRevenue += $row['revenue'];
    }

    $barLabels = [];
    $barData = [];
    foreach ($tripLabels as $tid) {
      $barLabels[] = "'".$tid."'";
      $barData[] = $tickets[$tid];
    }

    echo "<div style='font-weight:bold;font-size:17px;margin-bottom:6px;'>
      ".ucfirst($period)." Trips: $totalTrips<br>
      Revenue: ".number_format((float)$totalRevenue)." BDT
    </div>";

    $imgUrl = "https://quickchart.io/chart?c=" . urlencode("{type:'bar',data:{labels:[".implode(",",$barLabels)."],datasets:[{label:'Tickets Sold',backgroundColor:'#4fb3f6',data:[".implode(",",$barData)."]}]}}");
    echo "<div class='chart-img'><img src=\"$imgUrl\" width='500' height='250' alt='${period} Stats Chart' /></div>";
    exit;
  }

  // ---------------- HANDLE REQUESTS ----------------
  if ($action === 'requests_all') {
    $result = $conn->query("SELECT * FROM admin_requests ORDER BY id");
    echo "<table><tr>
      <th>id</th><th>subject</th><th>details</th>
      <th>requested_by</th><th>requested_at</th>
      <th>Status</th><th>Action</th>
    </tr>";
    while($row = $result->fetch_assoc()) {
      $id = $row['id'];
      $status = htmlspecialchars($row['status'] ?? '');
      echo "<tr>
        <td>{$row['id']}</td>
        <td>{$row['subject']}</td>
        <td>{$row['details']}</td>
        <td>{$row['requested_by']}</td>
        <td>{$row['requested_at']}</td>
        <td id='status-cell-{$id}'>".($status ? $status : "<i>None</i>")."</td>
        <td><button onclick=\"showStatusEdit('$id', '".htmlspecialchars($status,ENT_QUOTES)."')\">Handle</button></td>
      </tr>";
    }
    echo "</table>";
    exit;
  }

  if ($action === 'update_request_status') {
    $id = intval($_POST['id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    $stmt = $conn->prepare("UPDATE admin_requests SET status=? WHERE id=?");
    $stmt->bind_param('si', $status, $id);
    $stmt->execute();
    echo "Status updated!";
    exit;
  }

  if ($action === 'logout') {
    session_destroy();
    exit;
  }

  echo "Invalid action.";
}
?>
