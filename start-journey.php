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

$error = '';
$trustedContacts = [];

try {
    $stmt = $db->prepare("
        SELECT
            tc.id,
            tc.contact_user_id,
            u.name,
            u.username
        FROM trusted_contacts tc
        INNER JOIN users u
            ON u.id = tc.contact_user_id
        WHERE tc.user_id = ?
        ORDER BY u.name ASC
    ");

    $stmt->execute([$userId]);
    $trustedContacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    $trustedContacts = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $startPoint = trim($_POST['start_point'] ?? '');
    $destination = trim($_POST['destination'] ?? '');

    $startLat = (
        isset($_POST['start_lat']) &&
        $_POST['start_lat'] !== ''
    ) ? (float) $_POST['start_lat'] : null;

    $startLng = (
        isset($_POST['start_lng']) &&
        $_POST['start_lng'] !== ''
    ) ? (float) $_POST['start_lng'] : null;

    $destinationLat = (
        isset($_POST['destination_lat']) &&
        $_POST['destination_lat'] !== ''
    ) ? (float) $_POST['destination_lat'] : null;

    $destinationLng = (
        isset($_POST['destination_lng']) &&
        $_POST['destination_lng'] !== ''
    ) ? (float) $_POST['destination_lng'] : null;

    $transportMode = trim(
        $_POST['transport_mode'] ?? 'car'
    );

    $plannedDeparture = trim(
        $_POST['planned_departure'] ?? ''
    );

    $journeyNote = trim(
        $_POST['journey_note'] ?? ''
    );

    $notifyDeviation =
        isset($_POST['notify_deviation']) ? 1 : 0;

    $shareMode =
        $_POST['share_mode'] ?? 'none';

    $shareContacts =
        $_POST['share_contacts'] ?? [];

    if (!is_array($shareContacts)) {
        $shareContacts = [];
    }

    if (
        !in_array(
            $shareMode,
            ['none', 'all', 'trusted'],
            true
        )
    ) {
        $shareMode = 'none';
    }

    if ($startPoint === '') {

        $error =
            'Please enter your starting location or use your current location.';

    } elseif (
        $startLat === null ||
        $startLng === null
    ) {

        $error =
            'Please search for your starting location and select a result.';

    } elseif ($destination === '') {

        $error =
            'Please enter your destination.';

    } elseif (
        $destinationLat === null ||
        $destinationLng === null
    ) {

        $error =
            'Please search for your destination and select a result.';

    } elseif (
        $shareMode === 'trusted' &&
        empty($shareContacts)
    ) {

        $error =
            'Please select at least one trusted contact or choose another sharing option.';

    } else {

        try {

            $db->beginTransaction();

            $plannedDepartureValue = null;

            if ($plannedDeparture !== '') {

                $timestamp =
                    strtotime($plannedDeparture);

                if ($timestamp !== false) {

                    $plannedDepartureValue =
                        date(
                            'Y-m-d H:i:s',
                            $timestamp
                        );
                }
            }

            $stmt = $db->prepare("
                INSERT INTO journeys (
                    user_id,
                    start_label,
                    start_lat,
                    start_lng,
                    end_label,
                    end_lat,
                    end_lng,
                    transport_mode,
                    note,
                    route_deviation_alert,
                    planned_departure_at,
                    status,
                    started_at
                )
                VALUES (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    'active',
                    NOW()
                )
            ");

            $stmt->execute([
                $userId,
                $startPoint,
                $startLat,
                $startLng,
                $destination,
                $destinationLat,
                $destinationLng,
                $transportMode,
                $journeyNote,
                $notifyDeviation,
                $plannedDepartureValue
            ]);

            $journeyId =
                (int) $db->lastInsertId();

            if ($journeyId <= 0) {
                throw new Exception(
                    'The journey was not created.'
                );
            }

            if ($shareMode !== 'none') {

                $tableCheck = $db->query("
                    SHOW TABLES LIKE 'journey_shares'
                ");

                if ($tableCheck->rowCount() === 0) {

                    throw new Exception(
                        'The journey_shares table does not exist.'
                    );
                }

                if ($shareMode === 'trusted') {

                    $shareStmt = $db->prepare("
                        INSERT INTO journey_shares (
                            journey_id,
                            shared_with_user_id,
                            status,
                            created_at
                        )
                        VALUES (
                            ?,
                            ?,
                            'accepted',
                            NOW()
                        )
                    ");

                    foreach ($shareContacts as $contactUserId) {

                        $contactUserId =
                            (int) $contactUserId;

                        if (
                            $contactUserId <= 0 ||
                            $contactUserId === (int) $userId
                        ) {
                            continue;
                        }

                        $verifyStmt =
                            $db->prepare("
                                SELECT id
                                FROM trusted_contacts
                                WHERE user_id = ?
                                AND contact_user_id = ?
                                LIMIT 1
                            ");

                        $verifyStmt->execute([
                            $userId,
                            $contactUserId
                        ]);

                        if (!$verifyStmt->fetch()) {
                            continue;
                        }

                        $shareStmt->execute([
                            $journeyId,
                            $contactUserId
                        ]);
                    }
                }
            }

            $db->commit();

            header(
                'Location: tracking.php?journey_id=' .
                urlencode((string) $journeyId)
            );

            exit;

        } catch (Throwable $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            $error =
                'Unable to start journey: ' .
                $e->getMessage();
        }
    }
}

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

<title>SafariTrak - Start Journey</title>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
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
    display: flex;
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

.error-box {
    max-width: 850px;
    margin: 0 auto 15px;
    padding: 14px 16px;
    border-radius: 9px;
    background: #fff0f0;
    border: 1px solid #f0c0c0;
    color: #a92323;
    font-size: 12px;
}

.form-card {
    max-width: 850px;
    margin: 0 auto;
    background: #fff;
    border: 1px solid #e0e7e4;
    border-radius: 15px;
    overflow: hidden;
}

.section {
    padding: 22px;
    border-bottom: 1px solid #e8eceb;
}

.section:last-of-type {
    border-bottom: 0;
}

.section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 18px;
}

.section-icon {
    width: 35px;
    height: 35px;
    border-radius: 9px;
    background: #e9f4f1;
    color: #117d69;
    display: flex;
    align-items: center;
    justify-content: center;
}

.section-title h2 {
    margin: 0;
    font-size: 16px;
}

.section-title p {
    margin: 3px 0 0;
    color: #899395;
    font-size: 10px;
}

label {
    display: block;
    font-size: 11px;
    font-weight: bold;
    margin-bottom: 8px;
}

.input-row {
    display: flex;
    align-items: flex-end;
    gap: 10px;
}

.input-group {
    flex: 1;
}

input,
select,
textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #dce4e1;
    border-radius: 9px;
    outline: none;
    background: #fff;
    font-family: inherit;
    font-size: 13px;
}

input:focus,
select:focus,
textarea:focus {
    border-color: #119c7a;
    box-shadow: 0 0 0 2px rgba(17,156,122,.08);
}

textarea {
    min-height: 80px;
    resize: vertical;
}

.primary-btn {
    border: 0;
    border-radius: 9px;
    padding: 12px 16px;
    background: #147968;
    color: #fff;
    font-size: 12px;
    font-weight: bold;
    cursor: pointer;
    display: inline-flex;
    justify-content: center;
    align-items: center;
    gap: 7px;
    white-space: nowrap;
}

.primary-btn:hover {
    background: #106557;
}

.primary-btn:disabled {
    opacity: .65;
    cursor: not-allowed;
}

.location-message,
.destination-message {
    margin-top: 8px;
    color: #7b8789;
    font-size: 10px;
}

.search-results {
    display: none;
    margin-top: 8px;
    border: 1px solid #dfe6e4;
    border-radius: 9px;
    overflow: hidden;
    background: #fff;
}

.search-result {
    padding: 11px;
    border-bottom: 1px solid #e9edec;
    cursor: pointer;
    font-size: 11px;
}

.search-result:last-child {
    border-bottom: 0;
}

.search-result:hover {
    background: #f2f7f5;
}

.two-columns {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.checkbox-line {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 14px;
    color: #687477;
    font-size: 11px;
}

.checkbox-line input {
    width: auto;
}

.share-options {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
}

.share-option {
    border: 1px solid #dfe6e4;
    border-radius: 10px;
    padding: 13px;
    cursor: pointer;
    display: flex;
    align-items: flex-start;
    gap: 11px;
    transition: .2s;
}

.share-option:hover {
    background: #f8fbfa;
    border-color: #10b981;
}

.share-option input {
    width: auto;
    margin-top: 3px;
}

.share-option-content {
    flex: 1;
}

.share-option-title {
    font-size: 12px;
    font-weight: bold;
    color: #263238;
}

.share-option-description {
    margin-top: 4px;
    font-size: 10px;
    color: #7b8789;
    line-height: 1.5;
}

.share-icon {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    background: #e9f4f1;
    color: #117d69;
    display: flex;
    align-items: center;
    justify-content: center;
}

.trusted-contacts-wrapper {
    display: none;
    margin-top: 15px;
    padding: 15px;
    background: #f8fbfa;
    border: 1px solid #e0e8e5;
    border-radius: 10px;
}

.trusted-title {
    font-size: 11px;
    font-weight: bold;
    margin-bottom: 10px;
}

.contact-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.contact-card {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    border: 1px solid #e0e6e4;
    border-radius: 9px;
    cursor: pointer;
    background: #fff;
}

.contact-card:hover {
    background: #f8fbfa;
}

.contact-card input {
    width: auto;
}

.contact-avatar {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background: #117d69;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.contact-name {
    font-size: 12px;
    font-weight: bold;
}

.contact-status {
    color: #7d888a;
    font-size: 9px;
    margin-top: 3px;
}

.no-contacts {
    padding: 12px;
    background: #f6f8f7;
    border-radius: 8px;
    color: #7b8587;
    font-size: 11px;
}

.share-note {
    margin-top: 12px;
    padding: 11px;
    border-radius: 8px;
    background: #eef8f5;
    color: #287565;
    font-size: 10px;
    line-height: 1.5;
}

.form-actions {
    padding: 18px 22px;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    background: #fafcfb;
}

.cancel-btn {
    padding: 12px 16px;
    border: 1px solid #dce3e1;
    border-radius: 9px;
    color: #596467;
    text-decoration: none;
    font-size: 12px;
    font-weight: bold;
}

.loading {
    display: none;
}

.footer {
    max-width: 850px;
    margin: 15px auto;
    display: flex;
    justify-content: space-between;
    color: #8a9496;
    font-size: 9px;
}

@media(max-width:750px) {

    .sidebar {
        display: none;
    }

    .main {
        width: 100%;
        margin-left: 0;
    }

    .content {
        padding: 15px;
    }

    .input-row {
        flex-direction: column;
        align-items: stretch;
    }

    .two-columns {
        grid-template-columns: 1fr;
    }

    .form-actions {
        flex-direction: column;
    }

    .cancel-btn,
    .primary-btn {
        width: 100%;
        text-align: center;
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
Journey Planner
</small>

<h1>
Start Journey
</h1>

</div>

<div class="profile-circle">
<?= htmlspecialchars($userInitial) ?>
</div>

</header>

<div class="content">

<?php if ($error !== ''): ?>

<div class="error-box">

<i class="fas fa-circle-exclamation"></i>

<?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>

<form
method="POST"
id="journeyForm"
class="form-card"
>

<section class="section">

<div class="section-title">

<div class="section-icon">
<i class="fas fa-location-dot"></i>
</div>

<div>

<h2>Starting location</h2>

<p>
Enter your location manually or use your current GPS position.
</p>

</div>

</div>

<label for="locationMethod">
Location method
</label>

<select id="locationMethod">

<option value="manual">
Enter location manually
</option>

<option value="current">
Use my current location
</option>

</select>

<div style="height:15px;"></div>

<div id="manualLocationGroup">

<div class="input-row">

<div class="input-group">

<label for="startPoint">
Starting location
</label>

<input
type="text"
id="startPoint"
name="start_point"
placeholder="e.g. Rongai, Nairobi"
value="<?= htmlspecialchars($_POST['start_point'] ?? '') ?>"
autocomplete="off"
>

</div>

<button
type="button"
class="primary-btn"
id="findStartLocationBtn"
>

<i class="fas fa-search"></i>
Find

</button>

</div>

<div
class="search-results"
id="startResults"
></div>

<div
class="location-message"
id="manualLocationStatus"
>
Enter a starting location and click Find.
</div>

</div>

<div
id="currentLocationGroup"
style="display:none;"
>

<button
type="button"
class="primary-btn"
id="useMyLocationBtn"
>

<i class="fas fa-location-crosshairs"></i>
Use my current location

</button>

<div
class="location-message"
id="locationStatus"
>
Click the button to get your current GPS position.
</div>

</div>

<input
type="hidden"
id="startLat"
name="start_lat"
value="<?= htmlspecialchars($_POST['start_lat'] ?? '') ?>"
>

<input
type="hidden"
id="startLng"
name="start_lng"
value="<?= htmlspecialchars($_POST['start_lng'] ?? '') ?>"
>

</section>

<section class="section">

<div class="section-title">

<div class="section-icon">
<i class="fas fa-flag-checkered"></i>
</div>

<div>

<h2>Destination</h2>

<p>
Enter a destination and select it from the search results.
</p>

</div>

</div>

<div class="input-row">

<div class="input-group">

<label for="endPoint">
Destination
</label>

<input
type="text"
id="endPoint"
name="destination"
placeholder="e.g. Nairobi CBD"
value="<?= htmlspecialchars($_POST['destination'] ?? '') ?>"
autocomplete="off"
>

</div>

<button
type="button"
class="primary-btn"
id="findDestinationBtn"
>

<i class="fas fa-search"></i>
Find

</button>

</div>

<input
type="hidden"
id="endLat"
name="destination_lat"
value="<?= htmlspecialchars($_POST['destination_lat'] ?? '') ?>"
>

<input
type="hidden"
id="endLng"
name="destination_lng"
value="<?= htmlspecialchars($_POST['destination_lng'] ?? '') ?>"
>

<div
id="destinationResults"
class="search-results"
></div>

<div
id="destinationStatus"
class="destination-message"
>
Enter a destination and click Find.
</div>

</section>

<section class="section">

<div class="section-title">

<div class="section-icon">
<i class="fas fa-car"></i>
</div>

<div>

<h2>Travel details</h2>

<p>
Transport mode is used for ETA and route estimation.
</p>

</div>

</div>

<div class="two-columns">

<div>

<label for="transportMode">
Mode of transport
</label>

<select
id="transportMode"
name="transport_mode"
>

<option value="car">Car</option>
<option value="motorcycle">Motorcycle</option>
<option value="bicycle">Bicycle</option>
<option value="walking">Walking</option>
<option value="public_transport">Public transport</option>

</select>

</div>

<div>

<label for="departureTime">
Planned departure
</label>

<input
type="datetime-local"
id="departureTime"
name="planned_departure"
value="<?= htmlspecialchars($_POST['planned_departure'] ?? '') ?>"
>

</div>

</div>

<div style="margin-top:15px;">

<label for="journeyNote">
Journey note
</label>

<textarea
id="journeyNote"
name="journey_note"
placeholder="Optional note about this journey..."
><?= htmlspecialchars($_POST['journey_note'] ?? '') ?></textarea>

</div>

<label class="checkbox-line">

<input
type="checkbox"
id="deviationAlert"
name="notify_deviation"
value="1"
checked
>

Notify me if I significantly deviate from the planned route.

</label>

</section>

<section class="section">

<div class="section-title">

<div class="section-icon">
<i class="fas fa-share-nodes"></i>
</div>

<div>

<h2>Share live location</h2>

<p>
Choose how your live journey location should be shared.
</p>

</div>

</div>

<div class="share-options">

<label class="share-option">

<input
type="radio"
name="share_mode"
value="none"
checked
>

<div class="share-icon">
<i class="fas fa-lock"></i>
</div>

<div class="share-option-content">

<div class="share-option-title">
Don't share location
</div>

<div class="share-option-description">
Your journey remains private. Only you can see your live route.
</div>

</div>

</label>

<label class="share-option">

<input
type="radio"
name="share_mode"
value="all"
>

<div class="share-icon">
<i class="fas fa-location-dot"></i>
</div>

<div class="share-option-content">

<div class="share-option-title">
Share my live location
</div>

<div class="share-option-description">
Enable live location sharing for this journey. You can stop sharing later from the live-tracking page.
</div>

</div>

</label>

<label class="share-option">

<input
type="radio"
name="share_mode"
value="trusted"
id="shareTrusted"
>

<div class="share-icon">
<i class="fas fa-user-group"></i>
</div>

<div class="share-option-content">

<div class="share-option-title">
Share with trusted contacts
</div>

<div class="share-option-description">
Select exactly which trusted contacts are allowed to watch your live location and route.
</div>

</div>

</label>

</div>

<div
class="trusted-contacts-wrapper"
id="trustedContactsWrapper"
>

<div class="trusted-title">

<i class="fas fa-user-shield"></i>

Select trusted contacts

</div>

<?php if (!empty($trustedContacts)): ?>

<div class="contact-list">

<?php foreach ($trustedContacts as $contact): ?>

<?php

$contactName =
$contact['name'] ??
$contact['username'] ??
'Contact';

$contactInitial =
strtoupper(
substr($contactName, 0, 1)
);

?>

<label class="contact-card">

<input
type="checkbox"
class="share-checkbox"
name="share_contacts[]"
value="<?= (int)$contact['contact_user_id'] ?>"
>

<div class="contact-avatar">

<?= htmlspecialchars($contactInitial) ?>

</div>

<div>

<div class="contact-name">

<?= htmlspecialchars($contactName) ?>

</div>

<div class="contact-status">

Trusted contact · Can view live route

</div>

</div>

</label>

<?php endforeach; ?>

</div>

<?php else: ?>

<div class="no-contacts">

<i class="fas fa-circle-info"></i>

You do not have any trusted contacts yet.

<br><br>

Add trusted contacts first before using this option.

</div>

<?php endif; ?>

</div>

<div class="share-note">

<i class="fas fa-circle-info"></i>

Your trusted contact will be able to view your current position, destination, route progress and distance remaining while sharing is active.

You can stop location sharing later without ending the journey.

</div>

</section>

<div class="form-actions">

<a
href="my-journeys.php"
class="cancel-btn"
>
Cancel
</a>

<button
type="submit"
class="primary-btn"
id="submitJourney"
>

<i class="fas fa-route"></i>

Start Journey

<span
class="loading"
id="loading"
>

<i class="fas fa-spinner fa-spin"></i>

</span>

</button>

</div>

</form>

<div class="footer">

<span>
© 2026 SafariTrak
</span>

<span>
Navigate. Track. Share. Connect. Stay Safe.
</span>

</div>

</div>

</main>

</div>

<script>

document.addEventListener('DOMContentLoaded', function () {

const locationMethod =
document.getElementById('locationMethod');

const manualLocationGroup =
document.getElementById('manualLocationGroup');

const currentLocationGroup =
document.getElementById('currentLocationGroup');

const startPoint =
document.getElementById('startPoint');

const startLat =
document.getElementById('startLat');

const startLng =
document.getElementById('startLng');

const startResults =
document.getElementById('startResults');

const findStartLocationBtn =
document.getElementById('findStartLocationBtn');

const useMyLocationBtn =
document.getElementById('useMyLocationBtn');

const manualLocationStatus =
document.getElementById('manualLocationStatus');

const locationStatus =
document.getElementById('locationStatus');

const endPoint =
document.getElementById('endPoint');

const endLat =
document.getElementById('endLat');

const endLng =
document.getElementById('endLng');

const findDestinationBtn =
document.getElementById('findDestinationBtn');

const destinationStatus =
document.getElementById('destinationStatus');

const destinationResults =
document.getElementById('destinationResults');

const journeyForm =
document.getElementById('journeyForm');

const submitJourney =
document.getElementById('submitJourney');

const loading =
document.getElementById('loading');

const trustedContactsWrapper =
document.getElementById('trustedContactsWrapper');

const shareOptions =
document.querySelectorAll(
    'input[name="share_mode"]'
);

function switchLocationMethod() {

if (locationMethod.value === 'manual') {

manualLocationGroup.style.display =
'block';

currentLocationGroup.style.display =
'none';

} else {

manualLocationGroup.style.display =
'none';

currentLocationGroup.style.display =
'block';

}

}

locationMethod.addEventListener(
'change',
switchLocationMethod
);

switchLocationMethod();

function updateSharingInterface() {

const selected =
document.querySelector(
    'input[name="share_mode"]:checked'
);

if (!selected) {
    return;
}

if (selected.value === 'trusted') {

trustedContactsWrapper.style.display =
'block';

} else {

trustedContactsWrapper.style.display =
'none';

document
.querySelectorAll('.share-checkbox')
.forEach(function (checkbox) {

checkbox.checked = false;

});

}

}

shareOptions.forEach(function (option) {

option.addEventListener(
    'change',
    updateSharingInterface
);

});

updateSharingInterface();

async function searchPlace(query) {

const url =
'https://nominatim.openstreetmap.org/search?' +
'format=json' +
'&q=' +
encodeURIComponent(query) +
'&limit=5' +
'&addressdetails=1' +
'&countrycodes=ke';

const response =
await fetch(
url,
{
headers: {
'Accept': 'application/json',
'Accept-Language': 'en'
}
}
);

if (!response.ok) {

throw new Error(
'Location search failed.'
);

}

return await response.json();

}

function showResults(
results,
container,
onSelect
) {

container.innerHTML = '';

if (!results.length) {

container.style.display =
'none';

return;

}

container.style.display =
'block';

results.forEach(function(result) {

const item =
document.createElement('div');

item.className =
'search-result';

item.textContent =
result.display_name;

item.addEventListener(
'click',
function () {

onSelect(result);

container.style.display =
'none';

}
);

container.appendChild(item);

});

}

findStartLocationBtn.addEventListener(
'click',
async function () {

const query =
startPoint.value.trim();

if (!query) {

manualLocationStatus.textContent =
'Please enter a starting location first.';

manualLocationStatus.style.color =
'#c94b4b';

startPoint.focus();

return;

}

findStartLocationBtn.disabled =
true;

findStartLocationBtn.innerHTML =
'<i class="fas fa-spinner fa-spin"></i> Searching...';

manualLocationStatus.textContent =
'Searching OpenStreetMap...';

manualLocationStatus.style.color =
'#7b8789';

startLat.value = '';
startLng.value = '';

try {

const results =
await searchPlace(query);

if (!results.length) {

manualLocationStatus.textContent =
'Starting location was not found. Try a more specific location.';

manualLocationStatus.style.color =
'#c94b4b';

return;

}

showResults(
results,
startResults,
function (result) {

startPoint.value =
result.display_name;

startLat.value =
result.lat;

startLng.value =
result.lon;

manualLocationStatus.innerHTML =
'<i class="fas fa-circle-check"></i> Starting location selected.';

manualLocationStatus.style.color =
'#10b981';

}
);

manualLocationStatus.textContent =
'Select your starting location from the results below.';

} catch (error) {

manualLocationStatus.textContent =
'Unable to search for the starting location. Check your internet connection.';

manualLocationStatus.style.color =
'#c94b4b';

} finally {

findStartLocationBtn.disabled =
false;

findStartLocationBtn.innerHTML =
'<i class="fas fa-search"></i> Find';

}

});

startPoint.addEventListener(
'keydown',
function (event) {

if (event.key === 'Enter') {

event.preventDefault();

findStartLocationBtn.click();

}

});

startPoint.addEventListener(
'input',
function () {

startLat.value = '';
startLng.value = '';

startResults.style.display =
'none';

manualLocationStatus.textContent =
'Location changed. Click Find to search again.';

manualLocationStatus.style.color =
'#7b8789';

});

useMyLocationBtn.addEventListener(
'click',
function () {

if (!navigator.geolocation) {

locationStatus.textContent =
'Geolocation is not supported by this browser.';

locationStatus.style.color =
'#c94b4b';

return;

}

useMyLocationBtn.disabled =
true;

useMyLocationBtn.innerHTML =
'<i class="fas fa-spinner fa-spin"></i> Getting location...';

locationStatus.textContent =
'Getting your current GPS location...';

locationStatus.style.color =
'#7b8789';

navigator.geolocation.getCurrentPosition(

async function (position) {

const lat =
position.coords.latitude;

const lng =
position.coords.longitude;

const accuracy =
position.coords.accuracy;

startLat.value =
lat;

startLng.value =
lng;

try {

const response =
await fetch(
'https://nominatim.openstreetmap.org/reverse?' +
'format=json' +
'&lat=' +
encodeURIComponent(lat) +
'&lon=' +
encodeURIComponent(lng) +
'&zoom=18' +
'&addressdetails=1',
{
headers: {
'Accept-Language': 'en'
}
}
);

const data =
await response.json();

startPoint.value =
data.display_name ||
lat.toFixed(6) +
', ' +
lng.toFixed(6);

} catch (error) {

startPoint.value =
lat.toFixed(6) +
', ' +
lng.toFixed(6);

}

locationStatus.innerHTML =
'<i class="fas fa-circle-check"></i> Current location detected. Accuracy: approximately ' +
Math.round(accuracy) +
' metres.';

locationStatus.style.color =
'#10b981';

useMyLocationBtn.disabled =
false;

useMyLocationBtn.innerHTML =
'<i class="fas fa-location-crosshairs"></i> Use my current location';

},

function (error) {

useMyLocationBtn.disabled =
false;

useMyLocationBtn.innerHTML =
'<i class="fas fa-location-crosshairs"></i> Use my current location';

if (error.code === 1) {

locationStatus.textContent =
'Location permission was denied. Please allow location access.';

} else if (error.code === 2) {

locationStatus.textContent =
'Your current location could not be determined.';

} else {

locationStatus.textContent =
'Unable to obtain your current location.';

}

locationStatus.style.color =
'#c94b4b';

},

{
enableHighAccuracy: true,
timeout: 15000,
maximumAge: 0
}

);

});

findDestinationBtn.addEventListener(
'click',
async function () {

const query =
endPoint.value.trim();

if (!query) {

destinationStatus.textContent =
'Please enter a destination first.';

destinationStatus.style.color =
'#c94b4b';

endPoint.focus();

return;

}

findDestinationBtn.disabled =
true;

findDestinationBtn.innerHTML =
'<i class="fas fa-spinner fa-spin"></i> Searching...';

destinationStatus.textContent =
'Searching OpenStreetMap...';

destinationStatus.style.color =
'#7b8789';

endLat.value = '';
endLng.value = '';

try {

const results =
await searchPlace(query);

if (!results.length) {

destinationStatus.textContent =
'Destination was not found. Try another search.';

destinationStatus.style.color =
'#c94b4b';

return;

}

showResults(
results,
destinationResults,
function (result) {

endPoint.value =
result.display_name;

endLat.value =
result.lat;

endLng.value =
result.lon;

destinationStatus.innerHTML =
'<i class="fas fa-circle-check"></i> Destination selected successfully.';

destinationStatus.style.color =
'#10b981';

}
);

destinationStatus.textContent =
'Select your destination from the results below.';

} catch (error) {

destinationStatus.textContent =
'Unable to search for the destination. Check your internet connection.';

destinationStatus.style.color =
'#c94b4b';

} finally {

findDestinationBtn.disabled =
false;

findDestinationBtn.innerHTML =
'<i class="fas fa-search"></i> Find';

}

});

endPoint.addEventListener(
'keydown',
function (event) {

if (event.key === 'Enter') {

event.preventDefault();

findDestinationBtn.click();

}

});

endPoint.addEventListener(
'input',
function () {

endLat.value = '';
endLng.value = '';

destinationResults.style.display =
'none';

destinationStatus.textContent =
'Destination changed. Click Find to search again.';

destinationStatus.style.color =
'#7b8789';

});

journeyForm.addEventListener(
'submit',
async function (event) {

event.preventDefault();

if (!startPoint.value.trim()) {

alert(
'Please enter your starting location or use your current location.'
);

startPoint.focus();

return;

}

if (
!startLat.value ||
!startLng.value
) {

alert(
'Please select your starting location.'
);

return;

}

if (!endPoint.value.trim()) {

alert(
'Please enter your destination.'
);

endPoint.focus();

return;

}

if (
!endLat.value ||
!endLng.value
) {

alert(
'Please select your destination from the search results.'
);

return;

}

const shareMode =
document.querySelector(
'input[name="share_mode"]:checked'
);

if (
shareMode &&
shareMode.value === 'trusted'
) {

const selectedContacts =
document.querySelectorAll(
'.share-checkbox:checked'
);

if (selectedContacts.length === 0) {

alert(
'Please select at least one trusted contact.'
);

trustedContactsWrapper.scrollIntoView({
behavior: 'smooth',
block: 'center'
});

return;

}

}

submitJourney.disabled =
true;

loading.style.display =
'inline-block';

try {

const formData =
new FormData(journeyForm);

const response =
await fetch(
window.location.href,
{
method: 'POST',
body: formData
}
);

if (response.redirected) {

window.location.href =
response.url;

return;

}

const html =
await response.text();

document.open();

document.write(html);

document.close();

} catch (error) {

console.error(error);

alert(
'Something went wrong starting the journey. Please try again.'
);

submitJourney.disabled =
false;

loading.style.display =
'none';

}

});

});
</script>

</body>
</html>