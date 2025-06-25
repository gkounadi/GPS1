<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$session_id = intval($_GET['session_id'] ?? 0);
if (!$session_id) {
    die('Invalid round/session.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Round</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">
    <div class="container mx-auto p-4">
        <h1 class="text-2xl font-bold mb-4">Edit Round</h1>
        <form id="editRoundForm" class="bg-white p-4 rounded shadow max-w-lg mx-auto">
            <div id="editRoundHoles"></div>
            <div class="flex justify-between mt-4">
                <a href="index.php" class="bg-gray-400 text-white px-4 py-2 rounded">Cancel</a>
                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Save Changes</button>
            </div>
        </form>
    </div>
    <script>
    const sessionId = <?= $session_id ?>;
    let csrfToken = '';
    let totalHoles = 18; // default, will fetch actual course holes below

    async function fetchCsrfToken() {
        const response = await $.get('api.php?action=get_csrf_token');
        csrfToken = response.csrf_token;
    }

    // Load course hole count and round details, then render form
    async function loadRoundDetails() {
        // 1. Get course info (to get total holes)
        const courseResp = await $.get('api.php?action=get_course');
        if (courseResp && courseResp.holes) {
            totalHoles = parseInt(courseResp.holes);
        }
        // 2. Get round details
        const resp = await $.get('api.php?action=get_round_details&session_id=' + sessionId);
        if (resp.error) {
            alert(resp.error);
            window.location.href = "index.php";
            return;
        }
        // Map: hole_number -> score
        let scoreMap = {};
        if (resp.holes) {
            resp.holes.forEach(h => {
                scoreMap[h.hole_number] = h.score;
            });
        }
        let html = '';
        for (let i = 1; i <= totalHoles; i++) {
            const score = scoreMap[i] !== undefined && scoreMap[i] !== null ? scoreMap[i] : '';
            html += `
                <div class="mb-2 flex items-center">
                    <label class="mr-2 w-24">Hole ${i}:</label>
                    <input type="number" min="1" max="20" class="edit-round-score border rounded p-1 w-16" data-hole="${i}" value="${score}">
                </div>
            `;
        }
        $('#editRoundHoles').html(html);
    }

    // Save changes
    $('#editRoundForm').submit(async function(e) {
        e.preventDefault();
        let scores = [];
        $('.edit-round-score').each(function() {
            let val = $(this).val();
            scores.push({
                hole_number: $(this).data('hole'),
                score: val === '' ? null : val
            });
        });
        const resp = await $.post('api.php?action=edit_round', JSON.stringify({ session_id: sessionId, scores, csrf_token: csrfToken }));
        if (resp.success) {
            alert('Round updated!');
            window.location.href = "index.php";
        } else {
            alert(resp.error);
        }
    });

    (async () => {
        await fetchCsrfToken();
        await loadRoundDetails();
    })();
    </script>
</body>
</html>