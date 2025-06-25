<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Golf Course GPS & Scoring</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        #map { height: 400px; width: 100%; }
        .hidden { display: none; }
        .z-dropdown { z-index: 9999 !important; }
        .modal-bg { background: rgba(0,0,0,0.5); }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <div class="container mx-auto p-4">
        <h1 class="text-2xl font-bold mb-4">Golf Course GPS & Scoring</h1>

        <!-- User Info Dropdown (Right Aligned) -->
        <div id="userInfo" class="flex justify-end mb-4 <?php echo !isset($_SESSION['user_id']) ? 'hidden' : ''; ?>">
          <div class="relative">
            <button id="userDropdownBtn" class="flex items-center bg-white p-3 rounded-full shadow hover:bg-gray-100 focus:outline-none transition">
              <span id="userName" class="mr-2 font-semibold text-gray-700"><?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?></span>
              <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/>
              </svg>
            </button>
            <div id="userDropdownMenu" class="absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded shadow-lg z-dropdown hidden">
              <button id="viewProfile" class="w-full text-left px-4 py-2 text-gray-700 hover:bg-gray-100">View Profile</button>
              <button id="logoutButton" class="w-full text-left px-4 py-2 text-red-600 hover:bg-gray-100">Logout</button>
            </div>
          </div>
        </div>

        <!-- Login Section -->
        <div id="authSection" class="bg-white p-4 rounded shadow mb-4 <?php echo isset($_SESSION['user_id']) ? 'hidden' : ''; ?>">
            <h2 class="text-xl font-semibold mb-4">Login or Register</h2>
            <div id="loginForm">
                <h3 class="text-lg font-semibold">Login</h3>
                <div class="mb-4">
                    <label for="loginEmail" class="block">Email</label>
                    <input type="email" id="loginEmail" class="w-full p-2 border rounded" required>
                </div>
                <div class="mb-4">
                    <label for="loginPassword" class="block">Password</label>
                    <input type="password" id="loginPassword" class="w-full p-2 border rounded" required>
                </div>
                <button id="loginButton" class="bg-blue-500 text-white px-4 py-2 rounded">Login</button>
                <p class="mt-2">No account? <a href="#" id="showRegister" class="text-blue-500">Register</a></p>
            </div>
            <div id="registerForm" class="hidden">
                <h3 class="text-lg font-semibold">Register</h3>
                <div class="mb-4">
                    <label for="registerName" class="block">Name</label>
                    <input type="text" id="registerName" class="w-full p-2 border rounded" required>
                </div>
                <div class="mb-4">
                    <label for="registerEmail" class="block">Email</label>
                    <input type="email" id="registerEmail" class="w-full p-2 border rounded" required>
                </div>
                <div class="mb-4">
                    <label for="registerPassword" class="block">Password</label>
                    <input type="password" id="registerPassword" class="w-full p-2 border rounded" required>
                </div>
                <button id="registerButton" class="bg-green-500 text-white px-4 py-2 rounded">Register</button>
                <p class="mt-2">Already have an account? <a href="#" id="showLogin" class="text-blue-500">Login</a></p>
            </div>
        </div>

        <!-- Profile Section -->
        <div id="profileSection" class="bg-white p-4 rounded shadow mb-4 hidden">
            <h2 class="text-xl font-semibold">Profile</h2>
            <p><strong>Email:</strong> <span id="profileEmail"></span></p>
            <p><strong>Name:</strong> <span id="profileName"></span></p>
            <h3 class="text-lg font-semibold mt-4">Round History</h3>
            <div id="roundHistory"></div>
            <button id="backToGame" class="bg-blue-500 text-white px-4 py-2 rounded mt-4">Back to Game</button>
        </div>

        <!-- Game Section -->
        <button id="startNewRound" class="bg-green-500 text-white px-4 py-2 rounded mb-4 hidden">Start New Round</button>
        <div id="gameSection" class="hidden <?php echo isset($_SESSION['user_id']) ? '' : 'hidden'; ?>">
            <div class="bg-white p-4 rounded shadow mb-4">
                <h2 class="text-xl font-semibold">Distance Calculator</h2>
                <div id="map" class="my-4"></div>
                <div class="flex items-center mb-2">
                    <label for="unitToggle" class="mr-2">Meters</label>
                    <input type="checkbox" id="unitToggle" class="toggle">
                    <label for="unitToggle" class="ml-2">Yards</label>
                </div>
                <p id="distanceToCurrentGreen" class="mb-2"></p>
                <p id="distanceToPoint" class="mb-2"></p>
                <p id="distanceToGreen" class="mb-2"></p>
                <p id="courseInfo" class="font-semibold"></p>
            </div>

            <div class="bg-white p-4 rounded shadow mb-4">
                <h2 class="text-xl font-semibold">Hole <span id="holeNumber">1</span></h2>
                <div class="mt-4">
                    <label for="scoreSlider" class="block font-semibold">Hole Score</label>
                    <input type="range" id="scoreSlider" min="1" max="10" value="4" class="w-full">
                    <p id="scoreDisplay" class="text-center mt-2">4</p>
                </div>
                <div class="flex justify-between mt-4">
                    <button id="prevHole" class="bg-blue-500 text-white px-4 py-2 rounded hidden">Previous Hole</button>
                    <button id="nextHole" class="bg-blue-500 text-white px-4 py-2 rounded">Next Hole</button>
                    <button id="saveScore" class="bg-green-500 text-white px-4 py-2 rounded">Save Score</button>
                </div>
            </div>

            <div class="bg-white p-4 rounded shadow">
                <h2 class="text-xl font-semibold">Round Summary</h2>
                <div id="summary" class="mt-4"></div>
                <button id="endRound" class="bg-red-500 text-white px-4 py-2 rounded mt-4">End Round</button>
            </div>
        </div>
    </div>

    <script>
        let map, currentPosition, fairwayMarker;
        let holeNumber = 1;
        let unit = 'meters';
        let courseData = null;
        let userInteracted = false;
        let lastPosition = null;
        let csrfToken = '';
        const POSITION_THRESHOLD = 10;
        let roundStarted = false;
        let stagedScores = {};

        async function checkRoundSession() {
            const resp = await $.get('api.php?action=check_round_session');
            if (resp.has_round) {
                $('#startNewRound').addClass('hidden');
                roundStarted = true;
                $('#gameSection').removeClass('hidden');
            } else {
                $('#startNewRound').removeClass('hidden');
                roundStarted = false;
                $('#gameSection').addClass('hidden');
            }
        }

        async function checkLogin() {
            const isLoggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;
            if (isLoggedIn) {
                $('#authSection').addClass('hidden');
                $('#userInfo').removeClass('hidden');
                await fetchCourseData();
                await checkRoundSession();
                updateButtonVisibility();
                getLocation();
                resetStagedScores();
                await updateSummary();
            } else {
                $('#authSection').removeClass('hidden');
                $('#userInfo').addClass('hidden');
                $('#gameSection').addClass('hidden');
                $('#profileSection').addClass('hidden');
                $('#startNewRound').addClass('hidden');
            }
        }

        $('#startNewRound').click(async function() {
            const response = await $.post('api.php?action=start_round');
            if (response.success) {
                $('#startNewRound').addClass('hidden');
                roundStarted = true;
                $('#gameSection').removeClass('hidden');
                resetStagedScores();
                holeNumber = 1;
                document.getElementById('holeNumber').textContent = holeNumber;
                updateButtonVisibility();
                updateScoreSlider();
                await updateSummary();
            } else {
                alert(response.error || "Couldn't start a new round.");
            }
        });

        async function fetchCsrfToken() {
            const response = await $.get('api.php?action=get_csrf_token');
            csrfToken = response.csrf_token;
        }

        function resetStagedScores() {
            stagedScores = {};
            if (courseData && courseData.holes_data) {
                courseData.holes_data.forEach(h => {
                    stagedScores[h.hole_number] = null;
                });
            }
        }

        function initMap(lat, lng) {
            map = L.map('map').setView([lat, lng], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            map.on('move', () => {
                userInteracted = true;
            });

            map.on('click', function(e) {
                if (fairwayMarker) map.removeLayer(fairwayMarker);
                fairwayMarker = L.marker(e.latlng).addTo(map);
                updateDistances(e.latlng);
            });
        }

        async function fetchCourseData() {
            const response = await $.get('api.php?action=get_course');
            courseData = response;
            document.getElementById('courseInfo').textContent = `${courseData.location} - ${courseData.holes} Holes, Par ${courseData.par}, ${courseData.length}m`;
            updateScoreSlider();
        }

        async function updateDistances(fairwayLatLng = null) {
            if (!currentPosition) return;
            const data = {
                player_lat: currentPosition[0],
                player_lon: currentPosition[1],
                hole_number: holeNumber,
                unit: unit,
                csrf_token: csrfToken
            };
            if (fairwayLatLng) {
                data.fairway_lat = fairwayLatLng.lat;
                data.fairway_lon = fairwayLatLng.lng;
            }

            const response = await $.post('api.php?action=calculate_distances', JSON.stringify(data));
            if (response.error) {
                alert(response.error);
                return;
            }
            document.getElementById('distanceToCurrentGreen').textContent = `From current location to green: Front ${response.front.toFixed(0)} ${unit}, Center ${response.center.toFixed(0)} ${unit}, Back ${response.back.toFixed(0)} ${unit}`;
            if (response.to_point) {
                document.getElementById('distanceToPoint').textContent = `Distance to selected point: ${response.to_point.toFixed(0)} ${unit}`;
                document.getElementById('distanceToGreen').textContent = `From point to green: Front ${response.from_point_to_green.front.toFixed(0)} ${unit}, Center ${response.from_point_to_green.center.toFixed(0)} ${unit}, Back ${response.from_point_to_green.back.toFixed(0)} ${unit}`;
            }
        }

        function getLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.watchPosition(
                    position => {
                        const { latitude, longitude } = position.coords;
                        currentPosition = [latitude, longitude];

                        if (!map) {
                            initMap(latitude, longitude);
                            L.marker(currentPosition).addTo(map).bindPopup('You are here').openPopup();
                            updateDistances();
                            return;
                        }

                        L.marker(currentPosition).addTo(map).bindPopup('You are here').openPopup();
                        updateDistances(fairwayMarker ? fairwayMarker.getLatLng() : null);

                        const distanceToLast = lastPosition ? haversineDistance(currentPosition[0], currentPosition[1], lastPosition[0], lastPosition[1]) : 0;
                        if (!userInteracted && (!lastPosition || distanceToLast > POSITION_THRESHOLD)) {
                            map.setView(currentPosition, map.getZoom());
                        }
                        lastPosition = currentPosition;
                    },
                    error => {
                        console.error('Geolocation error:', error);
                        if (!map) initMap(courseData.holes_data[0].green_center_lat, courseData.holes_data[0].green_center_lon);
                        alert('Unable to access location. Using default course location.');
                    }
                );
            } else {
                alert('Geolocation not supported.');
                if (!map) initMap(courseData.holes_data[0].green_center_lat, courseData.holes_data[0].green_center_lon);
            }
        }

        function haversineDistance(lat1, lon1, lat2, lon2) {
            const R = 6371e3;
            const φ1 = lat1 * Math.PI / 180;
            const φ2 = lat2 * Math.PI / 180;
            const Δφ = (lat2 - lat1) * Math.PI / 180;
            const Δλ = (lon2 - lon1) * Math.PI / 180;
            const a = Math.sin(Δφ / 2) * Math.sin(Δφ / 2) +
                      Math.cos(φ1) * Math.cos(φ2) *
                      Math.sin(Δλ / 2) * Math.sin(Δλ / 2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            return R * c;
        }

        function updateScoreSlider() {
            if (!courseData || !courseData.holes_data) return;
            const hole = courseData.holes_data.find(h => h.hole_number == holeNumber);
            const scoreSlider = document.getElementById('scoreSlider');
            if (stagedScores[holeNumber] !== null && stagedScores[holeNumber] !== undefined) {
                scoreSlider.value = stagedScores[holeNumber];
            } else {
                scoreSlider.value = hole ? hole.par : 4;
            }
            document.getElementById('scoreDisplay').textContent = scoreSlider.value;
        }

        function updateButtonVisibility() {
            if (!courseData) return;
            const prevHoleButton = document.getElementById('prevHole');
            const nextHoleButton = document.getElementById('nextHole');
            prevHoleButton.classList.toggle('hidden', holeNumber <= 1);
            nextHoleButton.classList.toggle('hidden', holeNumber >= courseData.holes);
        }

        async function updateSummary() {
            if (!courseData) return;
            const summaryDiv = document.getElementById('summary');
            let totalScore = 0;
            let summaryHTML = '';
            for (let i = 1; i <= courseData.holes; i++) {
                let score = stagedScores[i];
                if (score !== null && score !== undefined) {
                    totalScore += parseInt(score);
                    summaryHTML += `<p><strong>Hole ${i}</strong>: Score ${score}</p>`;
                } else {
                    summaryHTML += `<p><strong>Hole ${i}</strong>: Not set</p>`;
                }
            }
            summaryHTML += `
                <h3 class="text-lg font-semibold mt-4">Total</h3>
                <p>Score: ${totalScore}</p>
            `;
            summaryDiv.innerHTML = summaryHTML;
        }

        async function showProfile() {
            const response = await $.get('api.php?action=get_profile');
            if (response.error) {
                alert(response.error);
                return;
            }
            $('#gameSection').addClass('hidden');
            $('#profileSection').removeClass('hidden');
            $('#profileEmail').text(response.email);
            $('#profileName').text(response.name);

            let historyHTML = '';
            if (response.sessions && response.sessions.length > 0) {
                response.sessions.forEach(session => {
                    historyHTML += `<div class="mb-2 border-b pb-2">
                        <strong>${session.course_name}</strong> - ${new Date(session.created_at).toLocaleDateString()}<br/>
                        Total Score: ${session.total}<br/>
                        <details>
                            <summary>View Holes</summary>
                            ${session.holes.map(h => `Hole ${h.hole_number}: ${h.score}`).join('<br/>')}
                        </details>
                        <div class="flex space-x-2 mt-2">
                            <button class="edit-round-btn bg-blue-500 text-white px-2 py-1 rounded text-sm" data-session="${session.session_id}">Edit</button>
                            <button class="delete-round-btn bg-red-500 text-white px-2 py-1 rounded text-sm" data-session="${session.session_id}">Delete</button>
                        </div>
                    </div>`;
                });
            } else {
                historyHTML = '<p>No rounds played yet.</p>';
            }
            $('#roundHistory').html(historyHTML);
        }

        $(document).on('click', '.edit-round-btn', function() {
            const sessionId = $(this).data('session');
            window.location.href = 'edit_round.php?session_id=' + sessionId;
        });

        $(document).on('click', '.delete-round-btn', async function() {
            const sessionId = $(this).data('session');
            if (!confirm('Are you sure you want to delete this round?')) return;
            const resp = await $.post('api.php?action=delete_round', JSON.stringify({ session_id: sessionId, csrf_token: csrfToken }));
            if (resp.success) {
                alert('Round deleted!');
                showProfile();
            } else {
                alert(resp.error);
            }
        });

        $('#userDropdownBtn').on('click', function(e) {
            e.stopPropagation();
            $('#userDropdownMenu').toggleClass('hidden');
        });
        $(document).on('click', function() {
            $('#userDropdownMenu').addClass('hidden');
        });
        $('#userDropdownMenu').on('click', function(e) {
            e.stopPropagation();
        });

        $('#showRegister').click(() => {
            $('#loginForm').addClass('hidden');
            $('#registerForm').removeClass('hidden');
        });

        $('#showLogin').click(() => {
            $('#registerForm').addClass('hidden');
            $('#loginForm').removeClass('hidden');
        });

        $('#registerButton').click(async () => {
            const name = $('#registerName').val();
            const email = $('#registerEmail').val();
            const password = $('#registerPassword').val();
            const response = await $.post('api.php?action=register', JSON.stringify({ name, email, password }));
            if (response.success) {
                alert('Registration successful! Please log in.');
                $('#showLogin').click();
                await fetchCsrfToken(); // <--- Fetch a new token after registration
            } else {
                alert(response.error);
            }
        });

        $('#loginButton').click(async () => {
            const email = $('#loginEmail').val();
            const password = $('#loginPassword').val();
            const response = await $.post('api.php?action=login', JSON.stringify({ email, password }));
            if (response.success) {
                $('#userName').text(response.name);
                $('#authSection').addClass('hidden');
                $('#userInfo').removeClass('hidden');
                await fetchCsrfToken(); // <-- Fetch new token here!
                await fetchCourseData();
                await checkRoundSession();
                updateButtonVisibility();
                getLocation();
                resetStagedScores();
                await updateSummary();
            } else {
                alert(response.error);
            }
        });

        $('#logoutButton').click(async () => {
            await $.post('api.php?action=logout');
            $('#userInfo').addClass('hidden');
            $('#gameSection').addClass('hidden');
            $('#profileSection').addClass('hidden');
            $('#authSection').removeClass('hidden');
            $('#userName').text('');
            $('#startNewRound').addClass('hidden');
            map = null;
            currentPosition = null;
            fairwayMarker = null;
            holeNumber = 1;
            roundStarted = false;
            resetStagedScores();
            await fetchCsrfToken(); // <-- Fetch a new token after logout
        });

        $('#viewProfile').click(() => {
            showProfile();
            $('#userDropdownMenu').addClass('hidden');
        });

        $('#backToGame').click(() => {
            $('#profileSection').addClass('hidden');
            if (roundStarted) {
                $('#gameSection').removeClass('hidden');
            }
        });

        $('#prevHole').click(() => {
            if (holeNumber > 1) {
                holeNumber--;
                document.getElementById('holeNumber').textContent = holeNumber;
                updateButtonVisibility();
                updateScoreSlider();
                updateDistances();
                if (fairwayMarker) {
                    map.removeLayer(fairwayMarker);
                    fairwayMarker = null;
                    document.getElementById('distanceToPoint').textContent = '';
                    document.getElementById('distanceToGreen').textContent = '';
                }
            }
        });

        $('#nextHole').click(() => {
            if (courseData && holeNumber < courseData.holes) {
                holeNumber++;
                document.getElementById('holeNumber').textContent = holeNumber;
                updateButtonVisibility();
                updateScoreSlider();
                updateDistances();
                if (fairwayMarker) {
                    map.removeLayer(fairwayMarker);
                    fairwayMarker = null;
                    document.getElementById('distanceToPoint').textContent = '';
                    document.getElementById('distanceToGreen').textContent = '';
                }
            }
        });

        $('#saveScore').click(() => {
            const score = parseInt($('#scoreSlider').val());
            if (isNaN(score) || score <= 0) {
                alert('Please select a valid score.');
                return;
            }
            stagedScores[holeNumber] = score;
            updateSummary();
        });

        $('#endRound').click(async () => {
            if (!courseData) return;
            let missing = [];
            for (let i = 1; i <= courseData.holes; i++) {
                if (stagedScores[i] === null || stagedScores[i] === undefined) {
                    missing.push(i);
                }
            }
            if (missing.length > 0) {
                alert('Please set a score for all holes before ending the round.\nMissing: ' + missing.join(', '));
                return;
            }
            const roundData = [];
            for (let i = 1; i <= courseData.holes; i++) {
                roundData.push({ hole_number: i, score: stagedScores[i] });
            }
            // Always use the current csrfToken value
            const response = await $.post('api.php?action=submit_round', JSON.stringify({ scores: roundData, csrf_token: csrfToken }));
            if (response.success) {
                alert('Round saved!');
                resetStagedScores();
                await updateSummary();
                holeNumber = 1;
                document.getElementById('holeNumber').textContent = holeNumber;
                updateScoreSlider();
                updateButtonVisibility();
                roundStarted = false;
                $('#gameSection').addClass('hidden');
                $('#startNewRound').removeClass('hidden');
            } else {
                alert(response.error);
            }
        });

        $('#unitToggle').change((e) => {
            unit = e.target.checked ? 'yards' : 'meters';
            updateDistances(fairwayMarker ? fairwayMarker.getLatLng() : null);
        });

        $('#scoreSlider').on('input', () => {
            $('#scoreDisplay').text($('#scoreSlider').val());
        });

        (async () => {
            await fetchCsrfToken();
            await checkLogin();
        })();
    </script>
</body>
</html>