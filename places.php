<?php

require_once __DIR__ . '/backend/config/database.php';
require_once __DIR__ . '/backend/includes/session.php';
require_once __DIR__ . '/backend/includes/auth-guard.php';

$userName =
    $_SESSION['name'] ??
    $_SESSION['username'] ??
    'Traveler';

$userInitial =
    strtoupper(
        substr($userName, 0, 1)
    );

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0"
>

<title>SafariTrak - Places</title>

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

.sidebar {
    width: 230px;
    background: #10b981;
    color: #fff;
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
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
}

.brand-text span {
    font-size: 9px;
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

.nav a:hover,
.nav a.active {
    background: rgba(255,255,255,.1);
}

.main {
    margin-left: 230px;
}

.topbar {
    height: 75px;
    background: #fff;
    border-bottom: 1px solid #e1e7e5;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 30px;
}

.title small {
    color: #10a77e;
    font-size: 10px;
    font-weight: bold;
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

.search-card {
    max-width: 1000px;
    margin: auto;
    background: #fff;
    border: 1px solid #e0e7e4;
    border-radius: 15px;
    overflow: hidden;
}

.search-area {
    padding: 20px;
    display: flex;
    gap: 10px;
}

.search-area input {
    flex: 1;
    padding: 13px;
    border: 1px solid #dce4e1;
    border-radius: 9px;
    font-size: 13px;
    outline: none;
}

.search-area button {
    border: 0;
    background: #147968;
    color: #fff;
    border-radius: 9px;
    padding: 0 20px;
    cursor: pointer;
    font-weight: bold;
}

#placesMap {
    width: 100%;
    height: 550px;
}

.results {
    max-height: 300px;
    overflow-y: auto;
}

.result {
    padding: 14px 18px;
    border-top: 1px solid #e8eceb;
    cursor: pointer;
}

.result:hover {
    background: #f4f8f6;
}

.result strong {
    display: block;
    font-size: 12px;
}

.result span {
    display: block;
    margin-top: 4px;
    color: #7b8789;
    font-size: 10px;
}

@media(max-width:750px) {

    .sidebar {
        display: none;
    }

    .main {
        margin-left: 0;
    }

    .content {
        padding: 15px;
    }

    .search-area {
        flex-direction: column;
    }

    .search-area button {
        padding: 12px;
    }
}

</style>

</head>

<body>

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

<a href="live-tracking.php">
<i class="fas fa-location-crosshairs"></i>
Live Tracking
</a>

<a href="places.php" class="active">
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

</aside>

<main class="main">

<header class="topbar">

<div class="title">

<small>
Location Search
</small>

<h1>
Places
</h1>

</div>

<div class="profile-circle">
<?= htmlspecialchars($userInitial) ?>
</div>

</header>

<div class="content">

<div class="search-card">

<div class="search-area">

<input
type="text"
id="placeSearch"
placeholder="Search a place in Kenya..."
autocomplete="off"
>

<button
type="button"
id="searchPlaceBtn"
>

<i class="fas fa-search"></i>

Search

</button>

</div>

<div id="placesMap"></div>

<div
id="placesResults"
class="results"
></div>

<<<<<<< HEAD
=======
<div class="grid">
  <div class="card map-card">
    <div class="card-head"><div><label>MAP</label><h3>Places near your route</h3></div></div>
    <div id="placesMap"></div>
  </div>

  <div class="card">
    <div class="card-head"><div><label>RESULTS</label><h3 id="resultsCount">Finding places near you...</h3></div></div>
    <div class="journey-list" id="placesList"></div>
    <p class="hint" id="placesStatus" style="padding:0 21px 21px;color:var(--muted);font-size:11px">Getting your location...</p>
  </div>
>>>>>>> f306403caf68a6f94a499dbe22f06277e8a0db92
</div>

</div>

</main>

<<<<<<< HEAD
<script
src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
></script>

=======
<div class="modal-overlay" id="placeDetailModal">
  <div class="modal">
    <div class="modal-head">
      <div>
        <h3 id="placeDetailTitle">Place name</h3>
        <p id="placeDetailSubtitle">Category &middot; distance</p>
      </div>
      <button class="modal-close" type="button" data-close-modal><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-body">
      <p id="placeDetailAddress"></p>
      <p style="margin-top:8px" id="placeDetailHours"></p>
    </div>
    <div class="modal-actions">
      <button type="button" class="ghost" data-close-modal>Close</button>
      <a id="placeDetailDirections" class="primary" target="_blank" rel="noopener" style="text-decoration:none;text-align:center">Get directions</a>
    </div>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="dashboard.js"></script>
<script src="notifications-widget.js"></script>
>>>>>>> f306403caf68a6f94a499dbe22f06277e8a0db92
<script src="places.js"></script>

</body>
<<<<<<< HEAD

=======
>>>>>>> f306403caf68a6f94a499dbe22f06277e8a0db92
</html>