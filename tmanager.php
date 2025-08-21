<?php
session_start();
$DEMO_USER = "tmanager1";
$DEMO_ID   = "TM1";
$DEMO_PASS = "1234";
$dbhost = "localhost";
$dbuser = "root";
$dbpass = "";
$dbname = "btbms";
$conn = new mysqli($dbhost, $dbuser, $dbpass, $dbname);

$login_error = "";
$request_message = "";

// Handle logout
if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: tmanager.php");
    exit;
}

// Handle login
if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? "");
    $userid   = trim($_POST['userid'] ?? "");
    $password = $_POST['password'] ?? "";
    if ($username === $DEMO_USER && $userid === $DEMO_ID && $password === $DEMO_PASS) {
        $_SESSION['tmanager_logged_in'] = true;
        $_SESSION['tmanager_id'] = $DEMO_ID;
    } else {
        $login_error = "Invalid credentials. Use tmanager1 / TM1 / 1234";
    }
}

// Handle Request to Admin
if (isset($_POST['request_admin']) && isset($_SESSION['tmanager_logged_in'])) {
    $subject = $conn->real_escape_string($_POST['subject'] ?? '');
    $details = $conn->real_escape_string($_POST['details'] ?? '');
    $by      = $_SESSION['tmanager_id'];
    if ($subject && $details) {
        $q = "INSERT INTO admin_requests (subject, details, requested_by) VALUES ('$subject', '$details', '$by')";
        if ($conn->query($q)) {
            $request_message = "Request sent to Admin successfully!";
        } else {
            $request_message = "Could not send request. Database error.";
        }
    } else {
        $request_message = "Please fill all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Transport Manager Dashboard</title>
  <link rel="stylesheet" href="tmanager.css" />
  <style>
    a { text-decoration:none; }
    .chart-img img { width:100%; max-width:400px; border-radius:8px; margin: 20px 0;}
    .sidebar button#btn-home {background:#00aaff;color:#141414;font-weight:bold;margin-bottom:18px;}
  </style>
</head>
<body>
<?php if (!isset($_SESSION['tmanager_logged_in'])): ?>
  <!-- Login Form -->
  <div id="login-section" class="centered">
    <h2>Transport Manager Login</h2>
    <form method="post" action="">
      <input type="text" name="username" placeholder="User Name" required /><br />
      <input type="text" name="userid" placeholder="User ID" required /><br />
      <input type="password" name="password" placeholder="Password" required /><br />
      <button type="submit" name="login">Login</button>
      <?php if ($login_error) echo "<div class='error'>$login_error</div>"; ?>
    </form>
  </div>
<?php else: ?>
  <div id="dashboard-section" style="display:flex;">
    <div class="sidebar">
      <a href="index.html"><button id="btn-home">&#8962; Home</button></a>
      <h3>Sections</h3>
      <button class="tab-btn active" data-tab="trip-stats">Trip Stats</button>
      <button class="tab-btn" data-tab="trip-money">Trip Money</button>
      <button class="tab-btn" data-tab="staff-comp">Staff Compensation</button>
      <button class="tab-btn" data-tab="vehicle-cost">Vehicle's Cost</button>
      <button class="tab-btn" data-tab="profit-loss">Profit / Loss</button>
      <button class="tab-btn" data-tab="request-admin">Request to Admin</button>
      <form method="post" action="" style="margin-top:30px;">
        <button type="submit" name="logout" style="background:#ff4b4b; color:#fff;">Logout</button>
      </form>
    </div>
    <div class="main-content">
      <!-- Trip Stats -->
      <div id="trip-stats" class="content-section" style="display:block;">
        <h2>Trip Stats</h2>
        <div class="subsection-btns">
          <button class="subtab-btn active" data-subtab="daily">Daily</button>
          <button class="subtab-btn" data-subtab="monthly">Monthly</button>
          <button class="subtab-btn" data-subtab="yearly">Yearly</button>
        </div>
        <div id="trip-stats-daily" class="subtab-content" style="display:block;">
          <b>Daily Trips:</b> 8<br><b>Revenue:</b> 45,000 BDT
          <div class="chart-img">
            <img src="https://quickchart.io/chart?c={type:'bar',data:{labels:['Trip 1','Trip 2','Trip 3','Trip 4','Trip 5','Trip 6', 'Trip 7', 'Trip 8'],datasets:[{label:'Tickets Sold',data:[17,22,9,5,23,28,27,28]}]}}" alt="Daily Stats Chart"/>
          </div>
        </div>
        <div id="trip-stats-monthly" class="subtab-content" style="display:none;">
          <b>Monthly Trips:</b> 350<br><b>Revenue:</b> 13,00,000 BDT
          <div class="chart-img">
            <img src="https://quickchart.io/chart?c={type:'line',data:{labels:['Jan','Feb','Mar','Apr','May','Jun','Jul'],datasets:[{label:'Trips',data:[198,212,205,199,223,195,213]}]}}" alt="Monthly Stats Chart"/>
          </div>
        </div>
        <div id="trip-stats-yearly" class="subtab-content" style="display:none;">
          <b>Yearly Trips:</b> 4000<br><b>Revenue:</b> 1,56,00,000 BDT
          <div class="chart-img">
            <img src="https://quickchart.io/chart?c={type:'pie',data:{labels:['2022','2023','2024','2025'],datasets:[{label:'Total Trips',data:[2100,2220,2190,2300]}]}}" alt="Yearly Stats Chart"/>
          </div>
        </div>
      </div>
      <!-- Trip Money -->
      <div id="trip-money" class="content-section" style="display:none;">
        <h2>Trip Money</h2>
        <b>Today:</b> 5,000 BDT<br>
        <b>This Month:</b> 1,20,000 BDT<br>
        <b>This Year:</b> 12,00,000 BDT
        <div class="chart-img">
          <img src="https://quickchart.io/chart?c={type:'doughnut',data:{labels:['Today','This Month','This Year'],datasets:[{data:[5000,120000,1200000]}]}}" alt="Trip Money Chart"/>
        </div>
      </div>
      <!-- Staff Compensation -->
      <div id="staff-comp" class="content-section" style="display:none;">
        <h2>Staff Compensation</h2>
        <b>Drivers:</b> 4,000 BDT/month<br>
        <b>Helpers:</b> 1,500 BDT/month
        <div class="chart-img">
          <img src="https://quickchart.io/chart?c={type:'bar',data:{labels:['Drivers','Helpers'],datasets:[{label:'Compensation',data:[4000,1500]}]}}" alt="Staff Compensation Chart"/>
        </div>
      </div>
      <!-- Vehicle Cost -->
      <div id="vehicle-cost" class="content-section" style="display:none;">
        <h2>Vehicle's Overall Cost</h2>
        <b>Maintenance (Monthly):</b> 3,000 BDT<br>
        <b>Fuel (Monthly):</b> 6,000 BDT<br>
        <b>Insurance (Yearly):</b> 7,000 BDT
        <div class="chart-img">
          <img src="https://quickchart.io/chart?c={type:'pie',data:{labels:['Maintenance','Fuel','Insurance'],datasets:[{data:[3000,6000,7000]}]}}" alt="Vehicle Cost Chart"/>
        </div>
      </div>
      <!-- Profit & Loss -->
      <div id="profit-loss" class="content-section" style="display:none;">
        <h2>Profit & Loss</h2>
        <b>Profit (This Year):</b> 5,00,000 BDT<br>
        <b>Loss (This Year):</b> 20,000 BDT
        <div class="chart-img">
          <img src="https://quickchart.io/chart?c={type:'bar',data:{labels:['Profit','Loss'],datasets:[{label:'Amount (BDT)',data:[500000,20000]}]}}" alt="Profit Loss Chart"/>
        </div>
      </div>
      <!-- Request to Admin -->
      <div id="request-admin" class="content-section" style="display:none;">
        <h2>Request to Admin</h2>
        <form method="post" action="" id="adminRequestForm">
          <input type="text" name="subject" placeholder="Subject" required /><br />
          <textarea name="details" placeholder="Request Details" required></textarea><br />
          <button type="submit" name="request_admin">Send Request</button>
        </form>
        <?php if ($request_message) echo "<div class='success'>$request_message</div>"; ?>
      </div>
    </div>
  </div>
  <script>
    // Section switching
    document.querySelectorAll('.tab-btn').forEach(btn => {
      btn.onclick = function() {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        document.querySelectorAll('.content-section').forEach(sec => sec.style.display = 'none');
        document.getElementById(this.dataset.tab).style.display = 'block';
        if (this.dataset.tab === "trip-stats") showTripStatsSubtab("daily");
      };
    });
    function showTripStatsSubtab(which) {
      document.querySelectorAll('.subtab-btn').forEach(btn => btn.classList.remove('active'));
      document.querySelector('.subtab-btn[data-subtab="' + which + '"]').classList.add('active');
      document.querySelectorAll('.subtab-content').forEach(sub => sub.style.display = 'none');
      document.getElementById('trip-stats-' + which).style.display = 'block';
    }
    document.querySelectorAll('.subtab-btn').forEach(btn => {
      btn.onclick = function() {
        showTripStatsSubtab(this.dataset.subtab);
      };
    });
  </script>
<?php endif; ?>
</body>
</html>
