<?php

require __DIR__ . '/backend/includes/auth-guard.php';

$db = safaritrak_db();

$journeysStmt = $db->prepare(
    'SELECT
        j.*,
        COUNT(DISTINCT js.id) AS share_count,
        GROUP_CONCAT(
            DISTINCT COALESCE(u.full_name, tc.invite_name)
            SEPARATOR ", "
        ) AS shared_names
     FROM journeys j
     LEFT JOIN journey_shares js
        ON js.journey_id = j.id
     LEFT JOIN trusted_contacts tc
        ON tc.id = js.trusted_contact_id
     LEFT JOIN users u
        ON u.id = tc.contact_user_id
     WHERE j.user_id = ?
     GROUP BY j.id
     ORDER BY j.started_at DESC'
);

$journeysStmt->execute([
    $currentUser['id']
]);

$journeys = $journeysStmt->fetchAll(PDO::FETCH_ASSOC);

$groupJourneys = [];

try {

    $groupStmt = $db->prepare(
        'SELECT
            gj.*,
            (
                SELECT COUNT(*)
                FROM group_members gm
                WHERE gm.group_journey_id = gj.id
            ) AS member_count
         FROM group_journeys gj
         LEFT JOIN group_members gm_user
            ON gm_user.group_journey_id = gj.id
         WHERE gj.organizer_id = ?
            OR gm_user.user_id = ?
         GROUP BY gj.id
         ORDER BY gj.departure_at DESC'
    );

    $groupStmt->execute([
        $currentUser['id'],
        $currentUser['id']
    ]);

    $groupJourneys = $groupStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $groupJourneys = [];

}

function journey_duration($start, $end): string
{
    if (!$start || !$end) {
        return '';
    }

    try {

        $startDate = new DateTime($start);
        $endDate = new DateTime($end);

        $diff = $startDate->diff($endDate);

        $parts = [];

        if ($diff->d > 0) {
            $parts[] = $diff->d . 'd';
        }

        if ($diff->h > 0) {
            $parts[] = $diff->h . 'h';
        }

        if ($diff->i > 0 || empty($parts)) {
            $parts[] = $diff->i . 'm';
        }

        return implode(' ', $parts);

    } catch (Throwable $e) {

        return '';
    }
}

function journey_status_class($status): string
{
    switch ($status) {

        case 'completed':
            return 'completed';

        case 'cancelled':
            return 'cancelled';

        case 'active':
        case 'in_progress':
            return 'active';

        default:
            return 'active';
    }
}

function journey_status_label($status): string
{
    switch ($status) {

        case 'completed':
            return 'Completed';

        case 'cancelled':
            return 'Cancelled';

        case 'active':
        case 'in_progress':
            return 'In progress';

        default:
            return ucfirst((string)$status);
    }
}

function journey_status_icon($status): string
{
    switch ($status) {

        case 'completed':
            return 'fa-check';

        case 'cancelled':
            return 'fa-xmark';

        default:
            return 'fa-route';
    }
}

?>
<!doctype html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width,initial-scale=1"
>

<title>SafariTrak | My Journeys</title>

<link
    rel="stylesheet"
    href="dashboard.css"
>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>

<style>

.journey-row {
    cursor: pointer;
    transition:
        background .2s ease,
        transform .15s ease;
}

.journey-row:hover {
    background: #f7fbf9;
}

.jicon {
    transition: .2s ease;
}

.journey-row[data-status="completed"] .jicon {
    background: #e5f7ee;
    color: #148a65;
}

.journey-row[data-status="cancelled"] .jicon {
    background: #fdecec;
    color: #c94c4c;
}

.badge.completed {
    background: #dff5e9 !important;
    color: #13845f !important;
    border: 1px solid #b9e8d0;
}

.badge.active {
    background: #e4f7f1 !important;
    color: #087f6b !important;
}

.badge.cancelled {
    background: #fdecec !important;
    color: #c0392b !important;
}

.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(17, 30, 27, .58);
    z-index: 99999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal-overlay.show {
    display: flex;
}

.modal {
    width: 100%;
    max-width: 460px;
    background: #ffffff;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 25px 70px rgba(0,0,0,.30);
}

.modal-head {
    padding: 22px;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 15px;
    border-bottom: 1px solid #edf1f0;
}

.modal-head h3 {
    margin: 0;
    font-size: 16px;
    color: #243631;
}

.modal-head p {
    margin: 6px 0 0;
    color: #7d8986;
    font-size: 10px;
}

.modal-close {
    width: 32px;
    height: 32px;
    border: none;
    border-radius: 50%;
    background: #f1f5f3;
    color: #596762;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-body {
    padding: 22px;
}

.modal-body p {
    margin: 0;
    color: #596762;
    font-size: 11px;
    line-height: 1.7;
}

.modal-body p + p {
    margin-top: 9px;
}

.completed-status-box {
    margin-bottom: 18px;
    padding: 13px 15px;
    border-radius: 11px;
    background: #e4f7ed;
    border: 1px solid #bce8d1;
    color: #147a5a;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 11px;
    font-weight: 700;
}

.completed-status-icon {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #15956d;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
}

.active-status-box {
    margin-bottom: 18px;
    padding: 13px 15px;
    border-radius: 11px;
    background: #e4f7f1;
    border: 1px solid #b9e7db;
    color: #087f6b;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 11px;
    font-weight: 700;
}

.cancelled-status-box {
    margin-bottom: 18px;
    padding: 13px 15px;
    border-radius: 11px;
    background: #fdecec;
    border: 1px solid #f3caca;
    color: #bd4141;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 11px;
    font-weight: 700;
}

.modal-actions {
    padding: 17px 22px;
    border-top: 1px solid #edf1f0;
    display: flex;
    justify-content: flex-end;
    gap: 9px;
}

.modal-actions button,
.modal-actions a {
    text-decoration: none;
    border: none;
    border-radius: 8px;
    padding: 10px 17px;
    font-size: 10px;
    font-weight: 700;
    cursor: pointer;
}

.modal-actions .ghost {
    background: #edf3f1;
    color: #40524c;
}

.modal-actions .primary {
    background: #087f6b;
    color: white;
}

.modal-actions .completed-button {
    background: #15956d;
    color: white;
}

</style>

</head>

<body>

<div class="app">

<aside class="sidebar" id="sidebar">

<div class="brand">

<div class="logo">
<i class="fa-solid fa-route"></i>
</div>

<div>
<b>SafariTrak</b>
<small>Travel smarter</small>
</div>

</div>

<nav>

<a href="index.php">
<i class="fa-solid fa-grid-2"></i>
Dashboard
</a>

<a
    class="active"
    href="my-journeys.php"
>
<i class="fa-solid fa-map-location-dot"></i>
My Journeys
</a>

<a href="live-tracking.php">
<i class="fa-solid fa-location-crosshairs"></i>
Live Tracking
</a>

<a href="places.php">
<i class="fa-solid fa-map-pin"></i>
Places
</a>

<a href="messages.php">
<i class="fa-regular fa-message"></i>
Messages
<?= $unreadConversationCount > 0
    ? " <em>" . (int)$unreadConversationCount . "</em>"
    : ""
?>
</a>

<a href="trusted-contacts.php">
<i class="fa-solid fa-user-group"></i>
Trusted Contacts
</a>

<a href="safety.php">
<i class="fa-solid fa-shield-halved"></i>
Safety
</a>

</nav>

<div class="bottom">

<a href="settings.php">
<i class="fa-solid fa-gear"></i>
Settings
</a>

<a href="logout.php">
<i class="fa-solid fa-arrow-right-from-bracket"></i>
Logout
</a>

<div class="account">

<span>
<?= st_avatar_inner($currentUser) ?>
</span>

<div>

<b>
<?= htmlspecialchars($userName) ?>
</b>

<small>
Traveler
</small>

</div>

</div>

</div>

</aside>

<main>

<header>

<button
    class="menu"
    id="menu"
>
<i class="fa-solid fa-bars"></i>
</button>

<div>

<label>
YOUR TRIPS
</label>

<h1>
My Journeys
</h1>

</div>

<div class="head-actions">

<div class="notif-wrap">

<button
    type="button"
    class="notif-bell"
    id="notifBell"
>

<i class="fa-regular fa-bell"></i>

<span
    class="notif-dot"
    id="notifDot"
></span>

</button>

<div
    class="notif-dropdown"
    id="notifDropdown"
>

<div class="notif-dropdown-head">

<b>
Notifications
</b>

<a href="notifications.php">
View all
</a>

</div>

<div
    class="notif-list"
    id="notifDropdownList"
>

<p class="notif-empty">
Loading...
</p>

</div>

</div>

</div>

<div class="avatar">
<?= st_avatar_inner($currentUser) ?>
</div>

</div>

</header>

<div class="content">

<div class="page-head">

<div>

<h2>
Everywhere you have travelled
</h2>

<p>
See your active trip, look back at past journeys, or plan a new one.
</p>

</div>

<div
    style="
        display:flex;
        gap:10px;
        flex-wrap:wrap
    "
>

<a
    class="btn-ghost"
    href="group-travel.php"
>

<i class="fa-solid fa-user-group"></i>

Group travel

</a>

<a
    class="btn-primary"
    href="start-journey.php"
>

<i class="fa-solid fa-plus"></i>

Start a journey

</a>

</div>

</div>

<div
    class="tabs"
    data-tab-group="journeys"
>

<button
    type="button"
    class="tab active"
    data-tab="all"
>
All
</button>

<button
    type="button"
    class="tab"
    data-tab="active"
>
Active
</button>

<button
    type="button"
    class="tab"
    data-tab="completed"
>
Completed
</button>

<button
    type="button"
    class="tab"
    data-tab="cancelled"
>
Cancelled
</button>

<button
    type="button"
    class="tab"
    data-tab="group"
>
Group
</button>

</div>

<div class="card">

<div
    class="journey-list"
    id="journeyList"
>

<?php if (
    empty($journeys) &&
    empty($groupJourneys)
): ?>

<p
    class="hint"
    style="
        padding:20px 21px;
        color:var(--muted);
        font-size:11px
    "
>
You have not started any journeys yet.
When you do, they will show up here.
</p>

<?php endif; ?>

<?php foreach ($journeys as $j): ?>

<?php

$status =
    strtolower(
        trim(
            $j['status'] ?? ''
        )
    );

if ($status === 'in_progress') {
    $status = 'active';
}

$statusBadge =
    journey_status_class($status);

$statusLabel =
    journey_status_label($status);

$icon =
    journey_status_icon($status);

if ($status === 'active') {

    $subLine =
        'Started ' .
        (new DateTime(
            $j['started_at']
        ))->format('g:i A');

    if (
        (int)$j['share_count'] > 0
    ) {

        $subLine .=
            ' · Shared with ' .
            (int)$j['share_count'] .
            ' ' .
            (
                (int)$j['share_count'] === 1
                ? 'contact'
                : 'contacts'
            );
    }

} elseif ($status === 'completed') {

    $subLine =
        'Completed · started ' .
        (new DateTime(
            $j['started_at']
        ))->format('j M, g:i A');

} elseif ($status === 'cancelled') {

    $subLine =
        'Cancelled · started ' .
        (new DateTime(
            $j['started_at']
        ))->format('j M, g:i A');

} else {

    $subLine =
        ucfirst($status) .
        ' · ' .
        (new DateTime(
            $j['started_at']
        ))->format('j M, g:i A');
}

?>

<div
    class="journey-row"
    data-status="<?= htmlspecialchars($status) ?>"
    data-open-modal="journeyModal<?= (int)$j['id'] ?>"
>

<div class="jicon">

<i class="fa-solid <?= $icon ?>"></i>

</div>

<div class="jinfo">

<b>

<?= htmlspecialchars(
    $j['start_label']
) ?>

&rarr;

<?= htmlspecialchars(
    $j['end_label']
) ?>

</b>

<small>
<?= htmlspecialchars($subLine) ?>
</small>

</div>

<div class="jmeta">

<strong>

<?= $j['distance_km'] !== null
    ? number_format(
        (float)$j['distance_km'],
        1
      ) . ' km'
    : '-'
?>

</strong>

<span
    class="badge <?= $statusBadge ?>"
>
<?= htmlspecialchars($statusLabel) ?>
</span>

</div>

</div>

<?php endforeach; ?>

<?php foreach ($groupJourneys as $g): ?>

<div
    class="journey-row"
    data-status="group"
    data-open-modal="groupModal<?= (int)$g['id'] ?>"
>

<div class="jicon">

<i class="fa-solid fa-user-group"></i>

</div>

<div class="jinfo">

<b>
<?= htmlspecialchars(
    $g['title']
) ?>
</b>

<small>

Group journey ·

<?= (int)$g['member_count'] ?>

members ·

<?= htmlspecialchars(
    ucfirst($g['status'])
) ?>

</small>

</div>

<div class="jmeta">

<strong>

<?= $g['distance_km'] !== null
    ? number_format(
        (float)$g['distance_km'],
        1
      ) . ' km'
    : '-'
?>

</strong>

<span class="badge active">
Group
</span>

</div>

</div>

<?php endforeach; ?>

</div>

<p
    class="hint"
    id="emptyState"
    style="
        display:none;
        padding:0 21px 21px;
        color:var(--muted);
        font-size:11px
    "
>
No journeys in this category yet.
</p>

</div>

</div>

<footer>

&copy; <?= date('Y') ?>
SafariTrak

<span>
Navigate. Track. Share. Connect. Stay Safe.
</span>

</footer>

</main>

</div>

<?php foreach ($journeys as $j): ?>

<?php

$status =
    strtolower(
        trim(
            $j['status'] ?? ''
        )
    );

if ($status === 'in_progress') {
    $status = 'active';
}

?>

<div
    class="modal-overlay"
    id="journeyModal<?= (int)$j['id'] ?>"
>

<div class="modal">

<div class="modal-head">

<div>

<h3>

<?= htmlspecialchars(
    $j['start_label']
) ?>

&rarr;

<?= htmlspecialchars(
    $j['end_label']
) ?>

</h3>

<p>

<?php if ($status === 'completed'): ?>

Completed · started
<?= (new DateTime(
    $j['started_at']
))->format('j M, g:i A') ?>

<?php elseif ($status === 'active'): ?>

In progress · started
<?= (new DateTime(
    $j['started_at']
))->format('g:i A') ?>

<?php elseif ($status === 'cancelled'): ?>

Cancelled · started
<?= (new DateTime(
    $j['started_at']
))->format('j M, g:i A') ?>

<?php endif; ?>

</p>

</div>

<button
    class="modal-close"
    type="button"
    data-close-modal
>

<i class="fa-solid fa-xmark"></i>

</button>

</div>

<div class="modal-body">

<?php if ($status === 'completed'): ?>

<div class="completed-status-box">

<div class="completed-status-icon">

<i class="fa-solid fa-check"></i>

</div>

<span>
Journey completed
</span>

</div>

<?php elseif ($status === 'active'): ?>

<div class="active-status-box">

<div class="completed-status-icon">

<i class="fa-solid fa-location-dot"></i>

</div>

<span>
Journey in progress
</span>

</div>

<?php elseif ($status === 'cancelled'): ?>

<div class="cancelled-status-box">

<div class="completed-status-icon">

<i class="fa-solid fa-xmark"></i>

</div>

<span>
Journey cancelled
</span>

</div>

<?php endif; ?>

<p>

<b>
Distance:
</b>

<?= $j['distance_km'] !== null
    ? number_format(
        (float)$j['distance_km'],
        1
      ) . ' km'
    : 'Not available'
?>

<?php if (
    !empty($j['ended_at'])
): ?>

&nbsp; · &nbsp;

<b>
Duration:
</b>

<?= htmlspecialchars(
    journey_duration(
        $j['started_at'],
        $j['ended_at']
    )
) ?>

<?php endif; ?>

</p>

<?php if (
    !empty($j['shared_names'])
): ?>

<p>

<b>
Shared with:
</b>

<?= htmlspecialchars(
    $j['shared_names']
) ?>

</p>

<?php endif; ?>

<?php if (
    !empty($j['note'])
): ?>

<p>

<b>
Note:
</b>

<?= htmlspecialchars(
    $j['note']
) ?>

</p>

<?php endif; ?>

</div>

<div class="modal-actions">

<button
    type="button"
    class="ghost"
    data-close-modal
>
Close
</button>

<?php if ($status === 'active'): ?>

<a
    class="primary"
    href="tracking.php?journey_id=<?= (int)$j['id'] ?>"
>
View Journey
</a>

<?php elseif ($status === 'completed'): ?>

<a
    class="completed-button"
    href="tracking.php?journey_id=<?= (int)$j['id'] ?>"
>
View Journey
</a>

<?php endif; ?>

</div>

</div>

</div>

<?php endforeach; ?>

<?php foreach ($groupJourneys as $g): ?>

<div
    class="modal-overlay"
    id="groupModal<?= (int)$g['id'] ?>"
>

<div class="modal">

<div class="modal-head">

<div>

<h3>
<?= htmlspecialchars(
    $g['title']
) ?>
</h3>

<p>

<?= (int)$g['member_count'] ?>
members ·

<?= htmlspecialchars(
    ucfirst($g['status'])
) ?>

</p>

</div>

<button
    class="modal-close"
    type="button"
    data-close-modal
>

<i class="fa-solid fa-xmark"></i>

</button>

</div>

<div class="modal-body">

<p>

<b>
Destination:
</b>

<?= htmlspecialchars(
    $g['destination_label']
) ?>

</p>

</div>

<div class="modal-actions">

<button
    type="button"
    class="ghost"
    data-close-modal
>
Close
</button>

<a
    class="primary"
    href="group-travel.php"
>
Manage group
</a>

</div>

</div>

</div>

<?php endforeach; ?>

<script src="dashboard.js"></script>

<script src="notifications-widget.js"></script>

<script src="journeys.js"></script>

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const rows =
            document.querySelectorAll(
                '[data-open-modal]'
            );

        rows.forEach(
            function (row) {

                row.addEventListener(
                    'click',
                    function (event) {

                        if (
                            event.target.closest('a') ||
                            event.target.closest('button')
                        ) {
                            return;
                        }

                        const modalId =
                            row.getAttribute(
                                'data-open-modal'
                            );

                        const modal =
                            document.getElementById(
                                modalId
                            );

                        if (modal) {

                            modal.classList.add(
                                'show'
                            );
                        }

                    }
                );

            }
        );

        const closeButtons =
            document.querySelectorAll(
                '[data-close-modal]'
            );

        closeButtons.forEach(
            function (button) {

                button.addEventListener(
                    'click',
                    function () {

                        const modal =
                            button.closest(
                                '.modal-overlay'
                            );

                        if (modal) {

                            modal.classList.remove(
                                'show'
                            );
                        }

                    }
                );

            }
        );

        document
            .querySelectorAll(
                '.modal-overlay'
            )
            .forEach(
                function (modal) {

                    modal.addEventListener(
                        'click',
                        function (event) {

                            if (
                                event.target === modal
                            ) {

                                modal.classList.remove(
                                    'show'
                                );
                            }

                        }
                    );

                }
            );

        document.addEventListener(
            'keydown',
            function (event) {

                if (
                    event.key === 'Escape'
                ) {

                    document
                        .querySelectorAll(
                            '.modal-overlay.show'
                        )
                        .forEach(
                            function (modal) {

                                modal.classList.remove(
                                    'show'
                                );

                            }
                        );
                }

            }
        );

    }
);

</script>

</body>

</html>