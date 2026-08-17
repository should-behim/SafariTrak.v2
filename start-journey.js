document.addEventListener('DOMContentLoaded', function () {

    const startInput =
        document.getElementById('startPoint') ||
        document.getElementById('start_point') ||
        document.getElementById('startLocation') ||
        document.getElementById('start_location');

    const destinationInput =
        document.getElementById('endPoint') ||
        document.getElementById('end_point') ||
        document.getElementById('destination') ||
        document.getElementById('destinationInput');

    const currentLocationBtn =
        document.getElementById('useMyLocationBtn') ||
        document.getElementById('useCurrentLocationBtn') ||
        document.getElementById('currentLocationBtn') ||
        document.getElementById('getCurrentLocationBtn');

    const startSearchBtn =
        document.getElementById('searchLocationBtn') ||
        document.getElementById('searchStartBtn') ||
        document.getElementById('findStartLocationBtn');

    const destinationSearchBtn =
        document.getElementById('destinationSearchBtn') ||
        document.getElementById('searchDestinationBtn') ||
        document.getElementById('findDestinationBtn');

    const mapElement =
        document.getElementById('map') ||
        document.getElementById('journeyMap') ||
        document.getElementById('startJourneyMap');

    const startLatInput =
        document.getElementById('startLat') ||
        document.getElementById('start_lat') ||
        document.getElementById('currentLat') ||
        document.getElementById('current_lat');

    const startLngInput =
        document.getElementById('startLng') ||
        document.getElementById('start_lng') ||
        document.getElementById('currentLng') ||
        document.getElementById('current_lng');

    const destinationLatInput =
        document.getElementById('destinationLat') ||
        document.getElementById('destination_lat');

    const destinationLngInput =
        document.getElementById('destinationLng') ||
        document.getElementById('destination_lng');

    let map = null;

    let currentMarker = null;
    let destinationMarker = null;

    let routeLine = null;

    let currentLocation = null;
    let destinationLocation = null;

    function showMessage(message, type = 'info') {

        let messageBox =
            document.getElementById('locationMessage');

        if (!messageBox) {

            messageBox =
                document.createElement('div');

            messageBox.id =
                'locationMessage';

            messageBox.style.marginTop =
                '8px';

            messageBox.style.padding =
                '10px';

            messageBox.style.borderRadius =
                '8px';

            messageBox.style.fontSize =
                '13px';

            messageBox.style.lineHeight =
                '1.5';

            if (
                startInput &&
                startInput.parentNode
            ) {

                startInput.parentNode.appendChild(
                    messageBox
                );

            } else if (
                currentLocationBtn &&
                currentLocationBtn.parentNode
            ) {

                currentLocationBtn.parentNode.appendChild(
                    messageBox
                );

            } else {

                document.body.appendChild(
                    messageBox
                );
            }
        }

        messageBox.textContent =
            message;

        if (type === 'success') {

            messageBox.style.background =
                '#e8f7f2';

            messageBox.style.color =
                '#087f6b';

        } else if (type === 'error') {

            messageBox.style.background =
                '#fdecec';

            messageBox.style.color =
                '#c0392b';

        } else {

            messageBox.style.background =
                '#f2f4f7';

            messageBox.style.color =
                '#344054';
        }
    }

    function createMap() {

        if (!mapElement) {

            console.warn(
                'Map element was not found.'
            );

            return;
        }

        if (
            typeof L === 'undefined'
        ) {

            console.error(
                'Leaflet is not loaded.'
            );

            showMessage(
                'Map library failed to load.',
                'error'
            );

            return;
        }

        map =
            L.map(
                mapElement,
                {
                    zoomControl: true
                }
            ).setView(
                [-1.286389, 36.817223],
                12
            );

        L.tileLayer(
            'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            {
                maxZoom: 19,
                attribution:
                    '&copy; OpenStreetMap contributors'
            }
        ).addTo(map);

        setTimeout(
            function () {

                map.invalidateSize();

            },
            300
        );
    }

    function setInputValue(
        input,
        value
    ) {

        if (!input) {
            return;
        }

        input.value =
            value;

        input.dispatchEvent(
            new Event(
                'input',
                {
                    bubbles: true
                }
            )
        );

        input.dispatchEvent(
            new Event(
                'change',
                {
                    bubbles: true
                }
            )
        );
    }

    function saveStartCoordinates(
        lat,
        lng
    ) {

        if (startLatInput) {
            startLatInput.value =
                lat;
        }

        if (startLngInput) {
            startLngInput.value =
                lng;
        }

        const currentLat =
            document.getElementById(
                'current_lat'
            );

        const currentLng =
            document.getElementById(
                'current_lng'
            );

        if (currentLat) {
            currentLat.value =
                lat;
        }

        if (currentLng) {
            currentLng.value =
                lng;
        }
    }

    function saveDestinationCoordinates(
        lat,
        lng
    ) {

        if (destinationLatInput) {

            destinationLatInput.value =
                lat;
        }

        if (destinationLngInput) {

            destinationLngInput.value =
                lng;
        }
    }

    function createCurrentMarker(
        lat,
        lng
    ) {

        if (!map) {
            return;
        }

        const icon =
            L.divIcon({
                className: '',
                html: `
                    <div style="
                        width:42px;
                        height:42px;
                        border-radius:50%;
                        background:#087f6b;
                        border:4px solid white;
                        box-shadow:0 3px 10px rgba(0,0,0,.35);
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        color:white;
                        font-size:20px;
                        font-weight:bold;
                    ">
                        ●
                    </div>
                `,
                iconSize: [42, 42],
                iconAnchor: [21, 21]
            });

        if (currentMarker) {

            currentMarker.setLatLng([
                lat,
                lng
            ]);

        } else {

            currentMarker =
                L.marker(
                    [lat, lng],
                    {
                        icon: icon
                    }
                ).addTo(map);

            currentMarker.bindPopup(
                '<strong>Your Current Location</strong>'
            );
        }
    }

    function createDestinationMarker(
        lat,
        lng
    ) {

        if (!map) {
            return;
        }

        const icon =
            L.divIcon({
                className: '',
                html: `
                    <div style="
                        width:42px;
                        height:42px;
                        border-radius:50%;
                        background:#c0392b;
                        border:4px solid white;
                        box-shadow:0 3px 10px rgba(0,0,0,.35);
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        color:white;
                        font-size:20px;
                        font-weight:bold;
                    ">
                        ⚑
                    </div>
                `,
                iconSize: [42, 42],
                iconAnchor: [21, 21]
            });

        if (destinationMarker) {

            destinationMarker.setLatLng([
                lat,
                lng
            ]);

        } else {

            destinationMarker =
                L.marker(
                    [lat, lng],
                    {
                        icon: icon
                    }
                ).addTo(map);

            destinationMarker.bindPopup(
                '<strong>Destination</strong>'
            );
        }
    }

    async function reverseGeocode(
        lat,
        lng
    ) {

        try {

            const url =
                'https://nominatim.openstreetmap.org/reverse' +
                '?format=jsonv2' +
                '&lat=' +
                encodeURIComponent(lat) +
                '&lon=' +
                encodeURIComponent(lng) +
                '&zoom=18' +
                '&addressdetails=1';

            const response =
                await fetch(
                    url,
                    {
                        method: 'GET',
                        headers: {
                            'Accept':
                                'application/json'
                        }
                    }
                );

            if (!response.ok) {

                throw new Error(
                    'Reverse geocoding failed.'
                );
            }

            const data =
                await response.json();

            if (!data) {
                return '';
            }

            if (
                data.address
            ) {

                const address =
                    data.address;

                const area =
                    address.suburb ||
                    address.neighbourhood ||
                    address.quarter ||
                    address.village ||
                    address.town ||
                    address.city_district ||
                    address.city ||
                    address.municipality ||
                    address.county ||
                    '';

                const city =
                    address.city ||
                    address.town ||
                    address.municipality ||
                    '';

                if (
                    area &&
                    city &&
                    area.toLowerCase() !==
                    city.toLowerCase()
                ) {

                    return (
                        area +
                        ', ' +
                        city
                    );
                }

                if (area) {
                    return area;
                }
            }

            return (
                data.display_name ||
                ''
            );

        } catch (error) {

            console.error(
                'Reverse geocoding error:',
                error
            );

            return '';
        }
    }

    async function detectCurrentLocation() {

        if (
            !navigator.geolocation
        ) {

            showMessage(
                'Your browser does not support location detection.',
                'error'
            );

            return;
        }

        if (currentLocationBtn) {

            currentLocationBtn.disabled =
                true;

            currentLocationBtn.textContent =
                'Detecting location...';
        }

        showMessage(
            'Detecting your current location...',
            'info'
        );

        navigator.geolocation.getCurrentPosition(
            async function (position) {

                const lat =
                    position.coords.latitude;

                const lng =
                    position.coords.longitude;

                currentLocation = {
                    lat: lat,
                    lng: lng
                };

                saveStartCoordinates(
                    lat,
                    lng
                );

                createCurrentMarker(
                    lat,
                    lng
                );

                if (map) {

                    map.setView(
                        [lat, lng],
                        16
                    );
                }

                showMessage(
                    'Location detected. Finding the area name...',
                    'info'
                );

                const locationName =
                    await reverseGeocode(
                        lat,
                        lng
                    );

                if (
                    locationName
                ) {

                    setInputValue(
                        startInput,
                        locationName
                    );

                    showMessage(
                        'Current location detected: ' +
                        locationName,
                        'success'
                    );

                } else {

                    const coordinates =
                        lat.toFixed(6) +
                        ', ' +
                        lng.toFixed(6);

                    setInputValue(
                        startInput,
                        coordinates
                    );

                    showMessage(
                        'Location detected, but the area name could not be found.',
                        'info'
                    );
                }

                drawRoute();

                if (currentLocationBtn) {

                    currentLocationBtn.disabled =
                        false;

                    currentLocationBtn.textContent =
                        'Enter My Current Location';
                }

            },
            function (error) {

                console.error(
                    'Geolocation error:',
                    error
                );

                let message =
                    'Unable to detect your current location.';

                if (
                    error.code ===
                    error.PERMISSION_DENIED
                ) {

                    message =
                        'Location permission was denied. Please allow location access in your browser.';

                } else if (
                    error.code ===
                    error.POSITION_UNAVAILABLE
                ) {

                    message =
                        'Your current location is unavailable. Please try again.';

                } else if (
                    error.code ===
                    error.TIMEOUT
                ) {

                    message =
                        'Location detection timed out. Please try again.';
                }

                showMessage(
                    message,
                    'error'
                );

                if (currentLocationBtn) {

                    currentLocationBtn.disabled =
                        false;

                    currentLocationBtn.textContent =
                        'Enter My Current Location';
                }
            },
            {
                enableHighAccuracy: true,
                timeout: 20000,
                maximumAge: 0
            }
        );
    }

    async function searchLocation(
        input,
        type
    ) {

        if (!input) {

            showMessage(
                'Location input was not found.',
                'error'
            );

            return;
        }

        const query =
            input.value.trim();

        if (!query) {

            showMessage(
                type === 'destination'
                    ? 'Please enter a destination.'
                    : 'Please enter a starting location.',
                'error'
            );

            input.focus();

            return;
        }

        showMessage(
            'Searching for "' +
            query +
            '"...',
            'info'
        );

        try {

            const url =
                'https://nominatim.openstreetmap.org/search' +
                '?format=jsonv2' +
                '&q=' +
                encodeURIComponent(query) +
                '&limit=1' +
                '&addressdetails=1';

            const response =
                await fetch(
                    url,
                    {
                        method: 'GET',
                        headers: {
                            'Accept':
                                'application/json'
                        }
                    }
                );

            if (!response.ok) {

                throw new Error(
                    'Location search failed.'
                );
            }

            const results =
                await response.json();

            if (
                !Array.isArray(results) ||
                results.length === 0
            ) {

                showMessage(
                    'Location could not be found. Please enter a more specific location.',
                    'error'
                );

                return;
            }

            const result =
                results[0];

            const lat =
                parseFloat(
                    result.lat
                );

            const lng =
                parseFloat(
                    result.lon
                );

            if (
                !Number.isFinite(lat) ||
                !Number.isFinite(lng)
            ) {

                showMessage(
                    'The selected location has invalid coordinates.',
                    'error'
                );

                return;
            }

            if (
                type === 'destination'
            ) {

                destinationLocation = {
                    lat: lat,
                    lng: lng
                };

                saveDestinationCoordinates(
                    lat,
                    lng
                );

                createDestinationMarker(
                    lat,
                    lng
                );

            } else {

                currentLocation = {
                    lat: lat,
                    lng: lng
                };

                saveStartCoordinates(
                    lat,
                    lng
                );

                createCurrentMarker(
                    lat,
                    lng
                );
            }

            if (map) {

                map.setView(
                    [lat, lng],
                    15
                );
            }

            showMessage(
                'Location found: ' +
                result.display_name,
                'success'
            );

            drawRoute();

        } catch (error) {

            console.error(
                'Location search error:',
                error
            );

            showMessage(
                'Unable to search for this location. Check your internet connection and try again.',
                'error'
            );
        }
    }

    function calculateDistance(
        lat1,
        lng1,
        lat2,
        lng2
    ) {

        const earthRadius =
            6371;

        const dLat =
            (lat2 - lat1) *
            Math.PI / 180;

        const dLng =
            (lng2 - lng1) *
            Math.PI / 180;

        const a =
            Math.sin(dLat / 2) *
            Math.sin(dLat / 2) +
            Math.cos(
                lat1 * Math.PI / 180
            ) *
            Math.cos(
                lat2 * Math.PI / 180
            ) *
            Math.sin(dLng / 2) *
            Math.sin(dLng / 2);

        const c =
            2 *
            Math.atan2(
                Math.sqrt(a),
                Math.sqrt(1 - a)
            );

        return earthRadius * c;
    }

    function drawRoute() {

        if (
            !map ||
            !currentLocation ||
            !destinationLocation
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
                        currentLocation.lat,
                        currentLocation.lng
                    ],
                    [
                        destinationLocation.lat,
                        destinationLocation.lng
                    ]
                ],
                {
                    color: '#2563eb',
                    weight: 4,
                    dashArray: '8,10',
                    opacity: 0.85
                }
            ).addTo(map);

        const bounds =
            L.latLngBounds([
                [
                    currentLocation.lat,
                    currentLocation.lng
                ],
                [
                    destinationLocation.lat,
                    destinationLocation.lng
                ]
            ]);

        map.fitBounds(
            bounds,
            {
                padding: [40, 40]
            }
        );
    }

    if (
        currentLocationBtn
    ) {

        currentLocationBtn.addEventListener(
            'click',
            function (event) {

                event.preventDefault();

                detectCurrentLocation();
            }
        );
    }

    if (
        startSearchBtn
    ) {

        startSearchBtn.addEventListener(
            'click',
            function (event) {

                event.preventDefault();

                searchLocation(
                    startInput,
                    'start'
                );
            }
        );
    }

    if (
        destinationSearchBtn
    ) {

        destinationSearchBtn.addEventListener(
            'click',
            function (event) {

                event.preventDefault();

                searchLocation(
                    destinationInput,
                    'destination'
                );
            }
        );
    }

    if (startInput) {

        startInput.addEventListener(
            'keydown',
            function (event) {

                if (
                    event.key ===
                    'Enter'
                ) {

                    event.preventDefault();

                    searchLocation(
                        startInput,
                        'start'
                    );
                }
            }
        );
    }

    if (destinationInput) {

        destinationInput.addEventListener(
            'keydown',
            function (event) {

                if (
                    event.key ===
                    'Enter'
                ) {

                    event.preventDefault();

                    searchLocation(
                        destinationInput,
                        'destination'
                    );
                }
            }
        );
    }

    createMap();

    console.log(
        'SafariTrak start-journey.js loaded successfully.'
    );

});