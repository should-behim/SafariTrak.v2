<?php

require_once __DIR__ . '/backend/config/database.php';
require_once __DIR__ . '/backend/includes/session.php';
require_once __DIR__ . '/backend/includes/auth-guard.php';

$db = safaritrak_db();

$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    header('Location: login.php');
    exit;
}

$activeJourney = null;

try {
    $stmt = $db->prepare("
        SELECT
            id,
            user_id,
            start_label,
            start_lat,
            start_lng,
            end_label,
            end_lat,
            end_lng,
            transport_mode,
            status,
            started_at,
            current_lat,
            current_lng,
            current_speed_kmh,
            last_location_update
        FROM journeys
        WHERE user_id = ?
        AND status = 'active'
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute([$userId]);

    $activeJourney = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    $activeJourney = null;
}

$userName =
    $_SESSION['name'] ??
    $_SESSION['username'] ??
    'Traveler';

$userInitial =
    strtoupper(substr($userName, 0, 1));

$journeyJson = $activeJourney
    ? json_encode($activeJourney, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)
    : 'null';

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>SafariTrak - Live Tracking</title>

<link
rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>

<link
rel="stylesheet"
href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f7f6;
    color: #263238;
}

.page {
    min-height: 100vh;
}

.sidebar {
    width: 230px;
    background: #10b981;
    color: #fff;
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
    z-index: 1000;
}

.brand {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 25px 22px;
}

.brand-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: #e5a82c;
    display: flex;
    align-items: center;
    justify-content: center;
}

.brand-text strong {
    display: block;
    font-size: 16px;
}

.brand-text span {
    font-size: 9px;
    opacity: .85;
}

.nav {
    padding: 8px 14px;
}

.nav a {
    display: flex;
    align-items: center;
    gap: 13px;
    color: #fff;
    text-decoration: none;
    padding: 13px 12px;
    border-radius: 10px;
    margin-bottom: 3px;
    font-size: 13px;
}

.nav a:hover {
    background: rgba(255,255,255,.1);
}

.nav a.active {
    background: rgba(255,255,255,.1);
    border-left: 3px solid #e5a82c;
    padding-left: 9px;
}

.user-area {
    position: absolute;
    left: 20px;
    right: 20px;
    bottom: 20px;
}

.user-area:before {
    content: "";
    display: block;
    height: 1px;
    background: rgba(255,255,255,.18);
    margin-bottom: 15px;
}

.user-profile {
    display: flex;
    align-items: center;
    gap: 10px;
}

.user-avatar {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background: #e6f1ee;
    color: #0e9871;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.user-info strong {
    display: block;
    font-size: 11px;
}

.user-info span {
    font-size: 9px;
}

.main {
    margin-left: 230px;
    width: calc(100% - 230px);
}

.topbar {
    height: 75px;
    background: #fff;
    border-bottom: 1px solid #e1e7e5;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 30px;
}

.title small {
    color: #10a77e;
    font-size: 10px;
    font-weight: bold;
    letter-spacing: 1px;
    text-transform: uppercase;
}

.title h1 {
    margin: 4px 0 0;
    font-size: 20px;
}

.profile-circle {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background: #e1eeeb;
    color: #0d9874;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.content {
    padding: 25px 30px;
}

.actions {
    display: flex;
    gap: 10px;
    margin-bottom: 18px;
}

.action-btn {
    border: 0;
    border-radius: 9px;
    padding: 12px 16px;
    background: #147968;
    color: #fff;
    text-decoration: none;
    font-size: 12px;
    font-weight: bold;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.action-btn:hover {
    background: #106557;
}

.action-btn.group {
    background: #e5a82c;
    color: #fff;
}

.action-btn.group:hover {
    background: #cf9218;
}

.map-card {
    background: #fff;
    border: 1px solid #e0e7e4;
    border-radius: 15px;
    overflow: hidden;
}

.map-header {
    min-height: 70px;
    padding: 15px 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #e7ecea;
}

.map-title {
    display: flex;
    align-items: center;
    gap: 10px;
}

.map-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: #e9f4f1;
    color: #117d69;
    display: flex;
    align-items: center;
    justify-content: center;
}

.map-title h2 {
    margin: 0;
    font-size: 15px;
}

.map-title p {
    margin: 3px 0 0;
    color: #899395;
    font-size: 10px;
}

.connection {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 10px;
    color: #687477;
}

.connection-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: #c94b4b;
}

.connection-dot.online {
    background: #10b981;
}

#map {
    width: 100%;
    height: 560px;
}

.map-info {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    border-top: 1px solid #e7ecea;
}

.info-box {
    padding: 15px;
    border-right: 1px solid #e7ecea;
}

.info-box:last-child {
    border-right: 0;
}

.info-box span {
    display: block;
    color: #899395;
    font-size: 9px;
    margin-bottom: 5px;
}

.info-box strong {
    font-size: 13px;
}

.no-journey {
    background: #fff;
    border: 1px solid #e0e7e4;
    border-radius: 15px;
    padding: 50px 30px;
    text-align: center;
}

.no-journey-icon {
    width: 65px;
    height: 65px;
    margin: 0 auto 15px;
    border-radius: 50%;
    background: #e9f4f1;
    color: #117d69;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 25px;
}

.no-journey h2 {
    margin: 0 0 8px;
    font-size: 18px;
}

.no-journey p {
    color: #7b8789;
    font-size: 12px;
    margin-bottom: 20px;
}

.status-message {
    position: fixed;
    left: 50%;
    top: 90px;
    transform: translateX(-50%);
    background: #fff;
    border: 1px solid #dfe7e4;
    border-radius: 9px;
    padding: 11px 15px;
    font-size: 11px;
    box-shadow: 0 5px 20px rgba(0,0,0,.08);
    z-index: 2000;
    display: none;
}

@media(max-width:800px) {

    .sidebar {
        display: none;
    }

    .main {
        margin-left: 0;
        width: 100%;
    }

    .content {
        padding: 15px;
    }

    .actions {
        flex-direction: column;
    }

    .action-btn {
        justify-content: center;
    }

    #map {
        height: 450px;
    }

    .map-info {
        grid-template-columns: 1fr 1fr;
    }

    .info-box:nth-child(2) {
        border-right: 0;
    }

    .info-box:nth-child(3),
    .info-box:nth-child(4) {
        border-top: 1px solid #e7ecea;
    }
}

</style>

</head>

<body>

<div class="page">

<aside class="sidebar">

<div class="brand">

<div class="brand-icon">
<i class="fas fa-route"></i>
</div>

<div class="brand-text">
<strong>SafariTrak</strong>
<span>Travel smarter</span>
</div>

</div>

<nav class="nav">

<a href="dashboard.php">
<i class="fas fa-th-large"></i>
Dashboard
</a>

<a href="my-journeys.php">
<i class="fas fa-map-marked-alt"></i>
My Journeys
</a>

<a href="live-tracking.php" class="active">
<i class="fas fa-location-crosshairs"></i>
Live Tracking
</a>

<a href="places.php">
<i class="fas fa-map-pin"></i>
Places
</a>

<a href="messages.php">
<i class="far fa-comment-alt"></i>
Messages
</a>

<a href="trusted-contacts.php">
<i class="fas fa-user-group"></i>
Trusted Contacts
</a>

<a href="safety.php">
<i class="fas fa-shield-halved"></i>
Safety
</a>

<a href="settings.php">
<i class="fas fa-gear"></i>
Settings
</a>

<a href="logout.php">
<i class="fas fa-arrow-right-from-bracket"></i>
Logout
</a>

</nav>

<div class="user-area">

<div class="user-profile">

<div class="user-avatar">
<?= htmlspecialchars($userInitial) ?>
</div>

<div class="user-info">

<strong>
<?= htmlspecialchars($userName) ?>
</strong>

<span>
Traveler
</span>

</div>

</div>

</div>

</aside>

<main class="main">

<header class="topbar">

<div class="title">

<small>
Live Tracking
</small>

<h1>
Track your journey
</h1>

</div>

<div class="profile-circle">
<?= htmlspecialchars($userInitial) ?>
</div>

</header>

<div class="content">

<div class="actions">

<a
href="start-journey.php"
class="action-btn"
>

<i class="fas fa-route"></i>

Start Journey

</a>

<a
href="group-travel.php"
class="action-btn group"
>

<i class="fas fa-user-group"></i>

Group Travel

</a>

</div>

<?php if ($activeJourney): ?>

<div class="map-card">

<div class="map-header">

<div class="map-title">

<div class="map-icon">
<i class="fas fa-location-dot"></i>
</div>

<div>

<h2>
Live Journey Map
</h2>

<p>
Your current position and route to the destination
</p>

</div>

</div>

<div class="connection">

<span
id="connectionDot"
class="connection-dot"
></span>

<span id="trackingStatus">
Connecting...
</span>

</div>

</div>

<div id="map"></div>

<div class="map-info">

<div class="info-box">

<span>
Destination
</span>

<strong id="destinationName">
<?= htmlspecialchars($activeJourney['end_label']) ?>
</strong>

</div>

<div class="info-box">

<span>
Distance remaining
</span>

<strong id="distanceRemaining">
Calculating...
</strong>

</div>

<div class="info-box">

<span>
Speed
</span>

<strong id="currentSpeed">
0 km/h
</strong>

</div>

<div class="info-box">

<span>
Location accuracy
</span>

<strong id="locationAccuracy">
--
</strong>

</div>

</div>

</div>

<?php else: ?>

<div class="no-journey">

<div class="no-journey-icon">
<i class="fas fa-location-crosshairs"></i>
</div>

<h2>
No active journey
</h2>

<p>
You do not currently have an active journey. Start a journey to see your live location, route and destination on the map.
</p>

<a
href="start-journey.php"
class="action-btn"
>

<i class="fas fa-route"></i>

Start Journey

</a>

<a
href="group-travel.php"
class="action-btn group"
>

<i class="fas fa-user-group"></i>

Group Travel

</a>

</div>

<?php endif; ?>

</div>

</main>

</div>

<div
id="statusMessage"
class="status-message"
></div>

<script>

window.SAFARITRAK_JOURNEY =
<?= $journeyJson ?>;

window.SAFARITRAK_USER_ID =
<?= (int)$userId ?>;

</script>

<script
src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
></script>

<script src="dashboard-map.js"></script>

<script src="tracking.js"></script>

</body>

</html>