<#
export-db.ps1

Exports `buses` and `trips` from local MySQL (database: bt bms) into data/db.json,
then commits and pushes the file to the current Git repo (so Vercel redeploys).

Usage:
  Open PowerShell as your user, then run:
    cd "C:\Users\fuaad\OneDrive\Documents\btbms-main"
    .\export-db.ps1

If MySQL client is in a non-standard location, edit the `$mysqlCandidates` array.
#>

param()

$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent $MyInvocation.MyCommand.Definition
Set-Location $repoRoot

# Config - edit these if your setup differs
$dbName = 'btbms'
$dbUser = 'root'
$dbPass = ''  # leave empty if no password

# Candidate mysql client locations (common XAMPP / MySQL paths)
$mysqlCandidates = @(
    'C:\xampp\mysql\bin\mysql.exe',
    'C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe',
    'mysql' # assume in PATH
)

$mysqlCmd = $null
foreach ($c in $mysqlCandidates) {
    if (Get-Command $c -ErrorAction SilentlyContinue) { $mysqlCmd = $c; break }
    if (Test-Path $c) { $mysqlCmd = $c; break }
}

if (-not $mysqlCmd) {
    Write-Host "Could not find mysql client. Please install MySQL client or update `export-db.ps1` mysqlCandidates." -ForegroundColor Red
    exit 1
}

function Run-Query($sql) {
    # Build args: -N -B for batch/tab-separated output with header
    $args = @('-N','-B','-e', $sql, $dbName)
    if ($dbUser) { $args = @('-u', $dbUser) + $args }
    if ($dbPass) { $args = @("--password=$dbPass") + $args }

    $proc = Start-Process -FilePath $mysqlCmd -ArgumentList $args -NoNewWindow -RedirectStandardOutput -PassThru -Wait
    $out = $proc.StandardOutput.ReadToEnd()
    return $out.Trim()`n
}

# Queries - ensure column order matches expectations
$busesQuery = "SELECT bus_id, total_seats FROM buses;"
$tripsQuery = "SELECT trip_id, bus_id, origin, destination, departure_time, arrival_time, fare, travel_date, available_seats FROM trips;"
$bookingsQuery = "SELECT booking_id, trip_id, seat_label, passenger_name, booked_at FROM bookings;"

function Parse-Tabular($raw) {
    if (-not $raw) { return @() }
    $lines = $raw -split "`n" | ForEach-Object { $_.Trim() } | Where-Object { $_ -ne '' }
    if ($lines.Count -lt 1) { return @() }
    $headers = $lines[0] -split "`t"
    $rows = @()
    for ($i = 1; $i -lt $lines.Count; $i++) {
        $cols = $lines[$i] -split "`t"
        $obj = @{}
        for ($j=0; $j -lt $headers.Count; $j++) {
            $key = $headers[$j]
            $val = if ($j -lt $cols.Count) { $cols[$j] } else { $null }
            # try to convert numeric fields to numbers
            if ($val -match '^[0-9]+$') { $val = [int]$val }
            elseif ($val -match '^[0-9]+\.[0-9]+$') { $val = [decimal]$val }
            $obj[$key] = $val
        }
        $rows += (New-Object psobject -Property $obj)
    }
    return $rows
}

Write-Host "Using mysql client: $mysqlCmd"

try {
    Write-Host "Querying buses..."
    $rawB = Run-Query $busesQuery
    $buses = Parse-Tabular $rawB

    Write-Host "Querying trips..."
    $rawT = Run-Query $tripsQuery
    $trips = Parse-Tabular $rawT

    Write-Host "Querying bookings..."
    $rawBk = Run-Query $bookingsQuery
    $bookings = Parse-Tabular $rawBk

    $dbObj = @{ buses = $buses; trips = $trips; bookings = $bookings }
    $json = $dbObj | ConvertTo-Json -Depth 5

    $dataDir = Join-Path $repoRoot 'data'
    if (-not (Test-Path $dataDir)) { New-Item -ItemType Directory -Path $dataDir | Out-Null }
    $outFile = Join-Path $dataDir 'db.json'
    $json | Set-Content -Encoding UTF8 -Path $outFile

    Write-Host "Wrote $outFile (buses: $($buses.Count), trips: $($trips.Count))"

    # Git add/commit/push
    if (Get-Command git -ErrorAction SilentlyContinue) {
        git add $outFile
        $msg = "Update DB export: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
        git commit -m $msg 2>$null | Out-Null
        git push
        Write-Host "Committed and pushed to origin/main" -ForegroundColor Green
    } else {
        Write-Host "Git not found in PATH; skipping commit/push." -ForegroundColor Yellow
    }
} catch {
    Write-Host "Error: $_" -ForegroundColor Red
    exit 1
}
