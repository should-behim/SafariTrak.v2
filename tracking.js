document.addEventListener('DOMContentLoaded', function () {

    let map = null;
    let currentMarker = null;
    let destinationMarker = null;
    let startMarker = null;
    let routeLine = null;
    let travelledLine = null;
    let accuracyCircle = null;

    let watchId = null;

    let journeyId = null;
    let journeyActive = true;

    let currentPosition = null;
    let lastPosition = null;

    let startLat = null;
    let startLng = null;

    let destinationLat = null;
    let destinationLng = null;

    let travelledCoordinates = [];
    let totalDistance = 0;

    let lastRouteLat = null;
    let lastRouteLng = null;
    let routeRecalculationInProgress = false;

    const mapElement = document.getElementById('map');

    const journeyElement =
        document.getElementById('journeyId') ||
        document.getElementById('journey_id');

    if (journeyElement) {
        journeyId = parseInt(
            journeyElement.value ||
            journeyElement.dataset.journeyId ||
            journeyElement.textContent,
            10
        );
    }

    if (!journeyId && window.journeyId) {
        journeyId = parseInt(window.journeyId, 10);
    }

    const startLatElement =
        document.getElementById('startLat') ||
        document.getElementById('start_lat');

    const startLngElement =
        document.getElementById('startLng') ||
        document.getElementById('start_lng');

    const destinationLatElement =
        document.getElementById('destinationLat') ||
        document.getElementById('destination_lat');

    const destinationLngElement =
        document.getElementById('destinationLng') ||
        document.getElementById('destination_lng');

    if (startLatElement) {
        startLat = parseFloat(
            startLatElement.value ||
            startLatElement.dataset.value ||
            startLatElement.textContent
        );
    }

    if (startLngElement) {
        startLng = parseFloat(
            startLngElement.value ||
            startLngElement.dataset.value ||
            startLngElement.textContent
        );
    }

    if (destinationLatElement) {
        destinationLat = parseFloat(
            destinationLatElement.value ||
            destinationLatElement.dataset.value ||
            destinationLatElement.textContent
        );
    }

    if (destinationLngElement) {
        destinationLng = parseFloat(
            destinationLngElement.value ||
            destinationLngElement.dataset.value ||
            destinationLngElement.textContent
        );
    }

    if (
        (!Number.isFinite(startLat) ||
        !Number.isFinite(startLng)) &&
        window.startCoordinates
    ) {
        startLat = parseFloat(window.startCoordinates.lat);
        startLng = parseFloat(window.startCoordinates.lng);
    }

    if (
        (!Number.isFinite(destinationLat) ||
        !Number.isFinite(destinationLng)) &&
        window.destinationCoordinates
    ) {
        destinationLat =
            parseFloat(window.destinationCoordinates.lat);

        destinationLng =
            parseFloat(window.destinationCoordinates.lng);
    }

    function validCoordinates(lat, lng) {
        return (
            Number.isFinite(lat) &&
            Number.isFinite(lng) &&
            lat >= -90 &&
            lat <= 90 &&
            lng >= -180 &&
            lng <= 180
        );
    }

    function initialiseMap() {

        if (!mapElement) {
            console.error('Map element was not found.');
            return;
        }

        if (typeof L === 'undefined') {
            console.error('Leaflet has not been loaded.');
            return;
        }

        if (map) {
            return;
        }

        map = L.map(mapElement, {
            zoomControl: true,
            attributionControl: true
        });

        L.tileLayer(
            'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            {
                maxZoom: 19,
                attribution:
                    '&copy; OpenStreetMap contributors'
            }
        ).addTo(map);

        if (
            validCoordinates(
                startLat,
                startLng
            )
        ) {

            map.setView(
                [
                    startLat,
                    startLng
                ],
                14
            );

            createStartMarker(
                startLat,
                startLng
            );

        } else {

            map.setView(
                [
                    -1.286389,
                    36.817223
                ],
                13
            );
        }

        if (
            validCoordinates(
                destinationLat,
                destinationLng
            )
        ) {

            createDestinationMarker(
                destinationLat,
                destinationLng
            );
        }

        drawRoute();

        setTimeout(function () {
            map.invalidateSize();
        }, 300);

        setTimeout(function () {
            map.invalidateSize();
        }, 1000);
    }

    function createStartMarker(lat, lng) {

        if (startMarker) {
            map.removeLayer(startMarker);
        }

        const icon = L.divIcon({
            className:
                'safaritrak-start-marker',

            html: `
                <div style="
                    width:34px;
                    height:34px;
                    border-radius:50%;
                    background:#087f6b;
                    border:4px solid white;
                    box-shadow:0 2px 8px rgba(0,0,0,.35);
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    color:white;
                    font-size:15px;
                ">
                    ●
                </div>
            `,

            iconSize: [
                34,
                34
            ],

            iconAnchor: [
                17,
                17
            ]
        });

        startMarker =
            L.marker(
                [
                    lat,
                    lng
                ],
                {
                    icon: icon
                }
            )
            .addTo(map)
            .bindPopup(
                '<strong>Starting Point</strong>'
            );
    }

    function createDestinationMarker(
        lat,
        lng
    ) {

        if (destinationMarker) {
            map.removeLayer(destinationMarker);
        }

        const icon =
            L.divIcon({
                className:
                    'safaritrak-destination-marker',

                html: `
                    <div style="
                        width:38px;
                        height:38px;
                        border-radius:50%;
                        background:#c0392b;
                        border:4px solid white;
                        box-shadow:0 2px 8px rgba(0,0,0,.4);
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        color:white;
                        font-size:18px;
                    ">
                        ⚑
                    </div>
                `,

                iconSize: [
                    38,
                    38
                ],

                iconAnchor: [
                    19,
                    19
                ]
            });

        destinationMarker =
            L.marker(
                [
                    lat,
                    lng
                ],
                {
                    icon: icon
                }
            )
            .addTo(map)
            .bindPopup(
                '<strong>Destination</strong>'
            );
    }

    function updateCurrentMarker(
        lat,
        lng
    ) {

        const icon =
            L.divIcon({
                className:
                    'safaritrak-current-marker',

                html: `
                    <div style="
                        width:22px;
                        height:22px;
                        border-radius:50%;
                        background:#148f77;
                        border:4px solid white;
                        box-shadow:
                            0 0 0 6px rgba(20,143,119,.20),
                            0 2px 8px rgba(0,0,0,.35);
                    "></div>
                `,

                iconSize: [
                    22,
                    22
                ],

                iconAnchor: [
                    11,
                    11
                ]
            });

        if (!currentMarker) {

            currentMarker =
                L.marker(
                    [
                        lat,
                        lng
                    ],
                    {
                        icon: icon,
                        zIndexOffset: 1000
                    }
                )
                .addTo(map)
                .bindPopup(
                    '<strong>Your Current Location</strong>'
                );

        } else {

            currentMarker.setLatLng(
                [
                    lat,
                    lng
                ]
            );
        }
    }

    function updateAccuracy(
        lat,
        lng,
        accuracy
    ) {

        if (
            !Number.isFinite(accuracy) ||
            accuracy <= 0
        ) {
            return;
        }

        if (!accuracyCircle) {

            accuracyCircle =
                L.circle(
                    [
                        lat,
                        lng
                    ],
                    {
                        radius:
                            accuracy,

                        color:
                            '#148f77',

                        fillColor:
                            '#148f77',

                        fillOpacity:
                            0.08,

                        weight:
                            1
                    }
                ).addTo(map);

        } else {

            accuracyCircle.setLatLng(
                [
                    lat,
                    lng
                ]
            );

            accuracyCircle.setRadius(
                accuracy
            );
        }
    }

    async function requestRoute(
        fromLat,
        fromLng,
        toLat,
        toLng
    ) {

        const url =
            'https://router.project-osrm.org/route/v1/driving/' +
            encodeURIComponent(fromLng) +
            ',' +
            encodeURIComponent(fromLat) +
            ';' +
            encodeURIComponent(toLng) +
            ',' +
            encodeURIComponent(toLat) +
            '?overview=full&geometries=geojson';

        const response =
            await fetch(url);

        if (!response.ok) {
            throw new Error(
                'Route request failed.'
            );
        }

        return await response.json();
    }

    async function drawRoute(
        fromCurrentPosition = false
    ) {

        if (!map) {
            return;
        }

        if (
            !validCoordinates(
                destinationLat,
                destinationLng
            )
        ) {
            return;
        }

        let routeStartLat = startLat;
        let routeStartLng = startLng;

        if (
            fromCurrentPosition &&
            currentPosition
        ) {

            routeStartLat =
                currentPosition.coords.latitude;

            routeStartLng =
                currentPosition.coords.longitude;
        }

        if (
            !validCoordinates(
                routeStartLat,
                routeStartLng
            )
        ) {
            return;
        }

        if (routeRecalculationInProgress) {
            return;
        }

        routeRecalculationInProgress = true;

        try {

            const data =
                await requestRoute(
                    routeStartLat,
                    routeStartLng,
                    destinationLat,
                    destinationLng
                );

            if (
                !data.routes ||
                !data.routes.length
            ) {
                drawStraightRoute(
                    routeStartLat,
                    routeStartLng
                );

                return;
            }

            const coordinates =
                data.routes[0]
                    .geometry
                    .coordinates
                    .map(function (point) {

                        return [
                            point[1],
                            point[0]
                        ];

                    });

            if (routeLine) {
                map.removeLayer(
                    routeLine
                );
            }

            routeLine =
                L.polyline(
                    coordinates,
                    {
                        color:
                            '#087f6b',

                        weight:
                            7,

                        opacity:
                            0.90,

                        lineJoin:
                            'round',

                        lineCap:
                            'round'
                    }
                ).addTo(map);

            if (!currentPosition) {

                map.fitBounds(
                    routeLine.getBounds(),
                    {
                        padding: [
                            40,
                            40
                        ]
                    }
                );
            }

            lastRouteLat =
                routeStartLat;

            lastRouteLng =
                routeStartLng;

        } catch (error) {

            console.warn(
                'OSRM route unavailable.',
                error
            );

            drawStraightRoute(
                routeStartLat,
                routeStartLng
            );

        } finally {

            routeRecalculationInProgress =
                false;
        }
    }

    function drawStraightRoute(
        routeStartLat = startLat,
        routeStartLng = startLng
    ) {

        if (
            !validCoordinates(
                routeStartLat,
                routeStartLng
            ) ||
            !validCoordinates(
                destinationLat,
                destinationLng
            )
        ) {
            return;
        }

        if (routeLine) {
            map.removeLayer(
                routeLine
            );
        }

        routeLine =
            L.polyline(
                [
                    [
                        routeStartLat,
                        routeStartLng
                    ],
                    [
                        destinationLat,
                        destinationLng
                    ]
                ],
                {
                    color:
                        '#087f6b',

                    weight:
                        7,

                    opacity:
                        0.85,

                    dashArray:
                        '12,8'
                }
            ).addTo(map);

        if (!currentPosition) {

            map.fitBounds(
                routeLine.getBounds(),
                {
                    padding: [
                        40,
                        40
                    ]
                }
            );
        }

        lastRouteLat =
            routeStartLat;

        lastRouteLng =
            routeStartLng;
    }

    function updateTravelledRoute(
        lat,
        lng
    ) {

        travelledCoordinates.push(
            [
                lat,
                lng
            ]
        );

        if (
            travelledCoordinates.length >
            5000
        ) {

            travelledCoordinates.shift();
        }

        if (!travelledLine) {

            travelledLine =
                L.polyline(
                    travelledCoordinates,
                    {
                        color:
                            '#064f43',

                        weight:
                            6,

                        opacity:
                            0.95,

                        lineJoin:
                            'round',

                        lineCap:
                            'round'
                    }
                ).addTo(map);

        } else {

            travelledLine.setLatLngs(
                travelledCoordinates
            );
        }
    }

    function distanceBetween(
        lat1,
        lng1,
        lat2,
        lng2
    ) {

        const earthRadius =
            6371;

        const dLat =
            (
                lat2 -
                lat1
            ) *
            Math.PI /
            180;

        const dLng =
            (
                lng2 -
                lng1
            ) *
            Math.PI /
            180;

        const a =
            Math.sin(
                dLat / 2
            ) *
            Math.sin(
                dLat / 2
            ) +
            Math.cos(
                lat1 *
                Math.PI /
                180
            ) *
            Math.cos(
                lat2 *
                Math.PI /
                180
            ) *
            Math.sin(
                dLng / 2
            ) *
            Math.sin(
                dLng / 2
            );

        const c =
            2 *
            Math.atan2(
                Math.sqrt(a),
                Math.sqrt(1 - a)
            );

        return earthRadius * c;
    }

    function formatDistance(
        distance
    ) {

        if (
            distance === null ||
            distance === undefined ||
            isNaN(distance)
        ) {
            return '--';
        }

        if (distance < 1) {

            return (
                Math.round(
                    distance * 1000
                ) +
                ' m'
            );
        }

        return (
            distance.toFixed(2) +
            ' km'
        );
    }

    function setText(
        ids,
        value
    ) {

        ids.forEach(
            function (id) {

                const element =
                    document.getElementById(
                        id
                    );

                if (element) {

                    element.textContent =
                        value;
                }
            }
        );
    }

    function updateInformation() {

        if (!currentPosition) {
            return;
        }

        const lat =
            currentPosition.coords.latitude;

        const lng =
            currentPosition.coords.longitude;

        const accuracy =
            currentPosition.coords.accuracy;

        let speed = 0;

        if (
            currentPosition.coords.speed !== null &&
            currentPosition.coords.speed >= 0
        ) {

            speed =
                currentPosition.coords.speed *
                3.6;
        }

        setText(
            [
                'currentSpeed',
                'current-speed',
                'speed'
            ],
            speed.toFixed(1) +
            ' km/h'
        );

        setText(
            [
                'distanceTravelled',
                'distance-travelled',
                'coveredKm'
            ],
            formatDistance(
                totalDistance
            )
        );

        if (
            Number.isFinite(accuracy)
        ) {

            setText(
                [
                    'gpsAccuracy',
                    'gps-accuracy',
                    'accuracy',
                    'locationAccuracy'
                ],
                Math.round(
                    accuracy
                ) +
                ' m'
            );
        }

        if (
            validCoordinates(
                destinationLat,
                destinationLng
            )
        ) {

            const remaining =
                distanceBetween(
                    lat,
                    lng,
                    destinationLat,
                    destinationLng
                );

            setText(
                [
                    'distanceRemaining',
                    'distance-remaining',
                    'remainingDistance',
                    'remainingKm'
                ],
                formatDistance(
                    remaining
                )
            );
        }

        setText(
            [
                'trackingStatus',
                'tracking-status'
            ],
            journeyActive ?
                'Live tracking active' :
                'Journey ended'
        );
    }

    function maybeRecalculateRoute(
        lat,
        lng
    ) {

        if (
            !validCoordinates(
                destinationLat,
                destinationLng
            )
        ) {
            return;
        }

        if (
            !validCoordinates(
                lastRouteLat,
                lastRouteLng
            )
        ) {

            drawRoute(true);
            return;
        }

        const movedSinceRoute =
            distanceBetween(
                lastRouteLat,
                lastRouteLng,
                lat,
                lng
            );

        if (
            movedSinceRoute >= 0.25
        ) {

            drawRoute(true);
        }
    }

    function centreOnCurrentLocation() {

        if (
            !map ||
            !currentPosition
        ) {
            return;
        }

        map.setView(
            [
                currentPosition.coords.latitude,
                currentPosition.coords.longitude
            ],
            Math.max(
                map.getZoom(),
                15
            )
        );
    }

    function startGPS() {

        if (
            !navigator.geolocation
        ) {

            showModal(
                'GPS Unavailable',
                'Your browser does not support location services.'
            );

            return;
        }

        if (watchId !== null) {
            return;
        }

        watchId =
            navigator.geolocation.watchPosition(

                function (position) {

                    if (!journeyActive) {
                        return;
                    }

                    currentPosition =
                        position;

                    const lat =
                        position.coords.latitude;

                    const lng =
                        position.coords.longitude;

                    const accuracy =
                        position.coords.accuracy;

                    if (
                        !validCoordinates(
                            lat,
                            lng
                        )
                    ) {
                        return;
                    }

                    if (lastPosition) {

                        const moved =
                            distanceBetween(
                                lastPosition.lat,
                                lastPosition.lng,
                                lat,
                                lng
                            );

                        if (
                            moved > 0.003 &&
                            moved < 2
                        ) {

                            totalDistance +=
                                moved;
                        }
                    }

                    lastPosition = {
                        lat: lat,
                        lng: lng
                    };

                    updateCurrentMarker(
                        lat,
                        lng
                    );

                    updateAccuracy(
                        lat,
                        lng,
                        accuracy
                    );

                    updateTravelledRoute(
                        lat,
                        lng
                    );

                    updateInformation();

                    maybeRecalculateRoute(
                        lat,
                        lng
                    );

                    sendPosition(
                        lat,
                        lng,
                        accuracy,
                        position.coords.speed
                    );

                    if (
                        !startMarker &&
                        !validCoordinates(
                            startLat,
                            startLng
                        )
                    ) {

                        startLat = lat;
                        startLng = lng;

                        createStartMarker(
                            lat,
                            lng
                        );
                    }

                },

                function (error) {

                    console.error(
                        'GPS error:',
                        error
                    );

                    let message =
                        'Unable to obtain your current location.';

                    if (
                        error.code === 1
                    ) {

                        message =
                            'Location permission was denied. Please allow location access in your browser.';

                    } else if (
                        error.code === 2
                    ) {

                        message =
                            'Your current location could not be determined.';

                    } else if (
                        error.code === 3
                    ) {

                        message =
                            'The GPS request timed out. Trying again...';
                    }

                    setText(
                        [
                            'trackingStatus',
                            'tracking-status'
                        ],
                        message
                    );
                },

                {
                    enableHighAccuracy:
                        true,

                    maximumAge:
                        3000,

                    timeout:
                        15000
                }
            );
    }

    function stopGPS() {

        if (
            watchId !== null &&
            navigator.geolocation
        ) {

            navigator.geolocation.clearWatch(
                watchId
            );

            watchId = null;
        }
    }

    function sendPosition(
        lat,
        lng,
        accuracy,
        speed
    ) {

        if (
            !journeyId ||
            !journeyActive
        ) {
            return;
        }

        let speedKmh = 0;

        if (
            speed !== null &&
            speed !== undefined &&
            speed >= 0
        ) {

            speedKmh =
                speed * 3.6;
        }

        fetch(
            // FIX: this used to point at
            // backend/api/journey/update-position.php (singular
            // "journey"), which does not exist. The real endpoint,
            // per the project's file tree, is under the plural
            // backend/api/journeys/ directory.
            'backend/api/journeys/update-position.php',
            {
                method:
                    'POST',

                headers: {
                    'Content-Type':
                        'application/json',

                    'Accept':
                        'application/json'
                },

                body:
                    JSON.stringify({
                        journey_id:
                            journeyId,

                        latitude:
                            lat,

                        longitude:
                            lng,

                        accuracy:
                            accuracy || 0,

                        speed_kmh:
                            speedKmh
                    })
            }
        )
        .then(
            function (response) {

                return response.text();
            }
        )
        .then(
            function (text) {

                if (!text) {
                    return;
                }

                try {

                    const data =
                        JSON.parse(text);

                    if (
                        !data.success
                    ) {

                        console.error(
                            'Position update failed:',
                            data.message
                        );
                    }

                } catch (error) {

                    console.error(
                        'Invalid position response:',
                        text
                    );
                }
            }
        )
        .catch(
            function (error) {

                console.error(
                    'Position update error:',
                    error
                );
            }
        );
    }

    function endJourney() {

        if (!journeyId) {

            showModal(
                'Unable to End Journey',
                'The journey ID could not be found.'
            );

            return;
        }

        showConfirmModal(
            'End Journey',
            'Are you sure you want to end this journey?',
            function () {
                processEndJourney();
            }
        );
    }

    async function processEndJourney() {

        const button =
            document.getElementById(
                'endJourneyBtn'
            ) ||
            document.getElementById(
                'end-journey-btn'
            );

        if (button) {

            button.disabled = true;

            button.textContent =
                'Ending Journey...';
        }

        try {

            const response =
                await fetch(
                    'backend/api/journeys/end.php',
                    {
                        method:
                            'POST',

                        headers: {
                            'Content-Type':
                                'application/json',

                            'Accept':
                                'application/json'
                        },

                        body:
                            JSON.stringify({
                                journey_id:
                                    journeyId
                            })
                    }
                );

            const text =
                await response.text();

            console.log(
                'End Journey server response:',
                text
            );

            let data;

            try {

                data =
                    JSON.parse(text);

            } catch (error) {

                if (button) {

                    button.disabled =
                        false;

                    button.textContent =
                        'End Journey';
                }

                showModal(
                    'Unable to End Journey',
                    'The End Journey server returned an invalid response.'
                );

                return;
            }

            if (
                !response.ok ||
                !data.success
            ) {

                if (button) {

                    button.disabled =
                        false;

                    button.textContent =
                        'End Journey';
                }

                showModal(
                    'Unable to End Journey',
                    data.message ||
                    'The journey could not be ended.'
                );

                return;
            }

            journeyActive =
                false;

            stopGPS();

            if (button) {

                button.disabled =
                    true;

                button.textContent =
                    'Journey Ended';
            }

            setText(
                [
                    'trackingStatus',
                    'tracking-status'
                ],
                'Journey ended'
            );

            showModal(
                'Journey Ended',
                data.message ||
                'Your journey has been successfully ended.',
                true
            );

            setTimeout(
                function () {

                    window.location.href =
                        'my-journeys.php';

                },
                1800
            );

        } catch (error) {

            console.error(
                'End Journey request error:',
                error
            );

            if (button) {

                button.disabled =
                    false;

                button.textContent =
                    'End Journey';
            }

            showModal(
                'Unable to End Journey',
                'The server could not be reached. Please check that backend/api/journeys/end.php exists.'
            );
        }
    }

    function showModal(
        title,
        message,
        success = false
    ) {

        let modal =
            document.getElementById(
                'trackingModal'
            );

        if (!modal) {

            modal =
                document.createElement(
                    'div'
                );

            modal.id =
                'trackingModal';

            modal.innerHTML = `
                <div class="tracking-modal-overlay">
                    <div class="tracking-modal-box">

                        <div
                            id="trackingModalIcon"
                            class="tracking-modal-icon">
                        </div>

                        <h2
                            id="trackingModalTitle">
                        </h2>

                        <p
                            id="trackingModalMessage">
                        </p>

                        <button
                            id="trackingModalOK">
                            OK
                        </button>

                    </div>
                </div>
            `;

            document.body.appendChild(
                modal
            );

            addModalStyles();
        }

        document.getElementById(
            'trackingModalIcon'
        ).textContent =
            success ? '✓' : '!';

        document.getElementById(
            'trackingModalTitle'
        ).textContent =
            title;

        document.getElementById(
            'trackingModalMessage'
        ).textContent =
            message;

        modal.style.display =
            'flex';

        document.getElementById(
            'trackingModalOK'
        ).onclick =
            function () {

                modal.style.display =
                    'none';
            };
    }

    function showConfirmModal(
        title,
        message,
        callback
    ) {

        let modal =
            document.getElementById(
                'trackingConfirmModal'
            );

        if (!modal) {

            modal =
                document.createElement(
                    'div'
                );

            modal.id =
                'trackingConfirmModal';

            modal.innerHTML = `
                <div class="tracking-modal-overlay">

                    <div class="tracking-modal-box">

                        <div class="tracking-modal-icon warning">
                            !
                        </div>

                        <h2>
                            ${title}
                        </h2>

                        <p>
                            ${message}
                        </p>

                        <div class="tracking-actions">

                            <button
                                id="trackingCancel">
                                Cancel
                            </button>

                            <button
                                id="trackingConfirm">
                                End Journey
                            </button>

                        </div>

                    </div>

                </div>
            `;

            document.body.appendChild(
                modal
            );

            addModalStyles();
        }

        modal.style.display =
            'flex';

        document.getElementById(
            'trackingCancel'
        ).onclick =
            function () {

                modal.style.display =
                    'none';
            };

        document.getElementById(
            'trackingConfirm'
        ).onclick =
            function () {

                modal.style.display =
                    'none';

                callback();
            };
    }

    function addModalStyles() {

        if (
            document.getElementById(
                'trackingModalStyles'
            )
        ) {
            return;
        }

        const style =
            document.createElement(
                'style'
            );

        style.id =
            'trackingModalStyles';

        style.textContent = `
            .tracking-modal-overlay {
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,.55);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 999999;
                padding: 20px;
            }

            .tracking-modal-box {
                width: 100%;
                max-width: 430px;
                background: white;
                border-radius: 18px;
                padding: 30px;
                text-align: center;
                box-shadow:
                    0 20px 60px rgba(0,0,0,.3);
            }

            .tracking-modal-icon {
                width: 58px;
                height: 58px;
                border-radius: 50%;
                background: #e8f7f2;
                color: #087f6b;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 18px;
                font-size: 28px;
                font-weight: bold;
            }

            .tracking-modal-icon.warning {
                background: #fdeaea;
                color: #c0392b;
            }

            .tracking-modal-box h2 {
                margin: 0 0 12px;
                color: #1f2933;
            }

            .tracking-modal-box p {
                color: #667085;
                line-height: 1.6;
                margin: 0;
            }

            #trackingModalOK {
                margin-top: 22px;
                padding: 11px 30px;
                border: 0;
                border-radius: 8px;
                background: #087f6b;
                color: white;
                cursor: pointer;
                font-weight: 600;
            }

            .tracking-actions {
                display: flex;
                justify-content: center;
                gap: 12px;
                margin-top: 22px;
            }

            .tracking-actions button {
                padding: 11px 22px;
                border-radius: 8px;
                cursor: pointer;
                font-weight: 600;
            }

            #trackingCancel {
                border: 1px solid #d0d5dd;
                background: white;
                color: #344054;
            }

            #trackingConfirm {
                border: 0;
                background: #c0392b;
                color: white;
            }
        `;

        document.head.appendChild(
            style
        );
    }

    function connectEndButton() {

        const buttons =
            document.querySelectorAll(
                '#endJourneyBtn, #end-journey-btn, .end-journey-btn'
            );

        buttons.forEach(
            function (button) {

                button.addEventListener(
                    'click',
                    function (event) {

                        event.preventDefault();

                        endJourney();
                    }
                );
            }
        );
    }

    function connectLocateButton() {

        const buttons =
            document.querySelectorAll(
                '#locateMeBtn, #myLocationBtn, .locate-me-btn'
            );

        buttons.forEach(
            function (button) {

                button.addEventListener(
                    'click',
                    function (event) {

                        event.preventDefault();

                        centreOnCurrentLocation();
                    }
                );
            }
        );
    }

    window.endSafariTrakJourney =
        endJourney;

    window.safariTrakTracking = {
        getMap: function () {
            return map;
        },

        getJourneyId: function () {
            return journeyId;
        },

        getCurrentPosition: function () {
            return currentPosition;
        },

        recalculateRoute: function () {
            drawRoute(true);
        },

        centreOnCurrentLocation:
            centreOnCurrentLocation,

        stopGPS:
            stopGPS,

        // FIX: exposed so a page that already has its own
        // confirmation modal (like live-tracking.php's
        // #endJourneyModal) can end the journey directly, without
        // triggering this script's separate showConfirmModal()
        // dialog on top of it.
        endJourneyDirect:
            processEndJourney
    };

    initialiseMap();

    connectEndButton();

    connectLocateButton();

    if (journeyId) {
        startGPS();
    } else {

        setText(
            [
                'trackingStatus',
                'tracking-status'
            ],
            'No active journey'
        );
    }

});