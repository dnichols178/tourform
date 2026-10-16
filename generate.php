<?php
declare(strict_types=1);

$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0755, true);
}

// Helper sanitization function
function clean(mixed $value): string {
    return htmlspecialchars(trim((string)($value ?? '')), ENT_QUOTES, 'UTF-8');
}

$tourRecord = null;
$tourId = '';

// Check if viewing a saved tour via GET ?id=
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['id'])) {
    $requestedId = basename((string)$_GET['id']);
    $filePath = $dataDir . '/' . $requestedId . '.json';
    if (file_exists($filePath)) {
        $jsonContents = file_get_contents($filePath);
        if ($jsonContents !== false) {
            $tourRecord = json_decode($jsonContents, true);
            $tourId = $requestedId;
        }
    }
    if (!$tourRecord) {
        header('Location: dashboard.php?msg=not_found');
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process form submission
    $tourTitle          = clean($_POST['tour_title'] ?? 'Tour Agenda & Report');
    $tourDate           = clean($_POST['tour_date'] ?? '');
    $timeStart          = clean($_POST['time_start'] ?? '');
    $timeEnd            = clean($_POST['time_end'] ?? '');
    $coordinatorName    = clean($_POST['coordinator_name'] ?? '');
    $coordinatorPhone   = clean($_POST['coordinator_phone'] ?? '');
    $coordinatorEmail   = clean($_POST['coordinator_email'] ?? '');
    $generalNotes       = clean($_POST['general_notes'] ?? '');
    $showArrows         = !empty($_POST['show_arrows']);
    $routeImageData     = clean($_POST['route_image_data'] ?? '');

    // Leaders (with Name & Organization)
    $rawLeaderNames = $_POST['leader_names'] ?? [];
    $rawLeaderOrgs  = $_POST['leader_orgs'] ?? [];
    $leaders = [];
    if (is_array($rawLeaderNames)) {
        foreach ($rawLeaderNames as $idx => $rawName) {
            $cleanName = clean($rawName);
            if ($cleanName !== '') {
                $leaders[] = [
                    'name' => $cleanName,
                    'org'  => clean($rawLeaderOrgs[$idx] ?? '')
                ];
            }
        }
    }

    // Participants (with Name & Organization)
    $rawPartNames = $_POST['participant_names'] ?? [];
    $rawPartOrgs  = $_POST['participant_orgs'] ?? [];
    $participants = [];
    if (is_array($rawPartNames)) {
        foreach ($rawPartNames as $idx => $rawName) {
            $cleanName = clean($rawName);
            if ($cleanName !== '') {
                $participants[] = [
                    'name' => $cleanName,
                    'org'  => clean($rawPartOrgs[$idx] ?? '')
                ];
            }
        }
    }

    // Facility Stops
    $facilityNames   = is_array($_POST['facility_names'] ?? null) ? $_POST['facility_names'] : [];
    $facilityMinutes = is_array($_POST['facility_minutes'] ?? null) ? $_POST['facility_minutes'] : [];
    $facilitySmes    = is_array($_POST['facility_smes'] ?? null) ? $_POST['facility_smes'] : [];
    $facilityNotes   = is_array($_POST['facility_notes'] ?? null) ? $_POST['facility_notes'] : [];

    $stops = [];
    $totalStops = count($facilityNames);
    for ($i = 0; $i < $totalStops; $i++) {
        $name = clean($facilityNames[$i] ?? '');
        if ($name === '') {
            continue;
        }
        $stops[] = [
            'name'    => $name,
            'minutes' => (int)($facilityMinutes[$i] ?? 0),
            'sme'     => clean($facilitySmes[$i] ?? ''),
            'notes'   => clean($facilityNotes[$i] ?? ''),
        ];
    }

    // Unique Identifier & Timestamp
    $tourId = 'tour_' . time() . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
    $tourRecord = [
        'id'                  => $tourId,
        'created_at'          => date('c'),
        'tour_title'          => $tourTitle,
        'tour_date'           => $tourDate,
        'time_start'          => $timeStart,
        'time_end'            => $timeEnd,
        'coordinator_name'    => $coordinatorName,
        'coordinator_phone'   => $coordinatorPhone,
        'coordinator_email'   => $coordinatorEmail,
        'general_notes'       => $generalNotes,
        'show_arrows'         => $showArrows,
        'leaders'             => $leaders,
        'participants'        => $participants,
        'stops'               => $stops,
        'route_image_data'    => $routeImageData,
    ];

    // Save JSON record
    file_put_contents($dataDir . '/' . $tourId . '.json', json_encode($tourRecord, JSON_PRETTY_PRINT));
} else {
    header('Location: index.php');
    exit;
}

// Map variables from active record
$tourTitle          = $tourRecord['tour_title'] ?? 'Tour Agenda & Report';
$tourDate           = $tourRecord['tour_date'] ?? '';
$timeStart          = $tourRecord['time_start'] ?? '';
$timeEnd            = $tourRecord['time_end'] ?? '';
$coordinatorName    = $tourRecord['coordinator_name'] ?? '';
$coordinatorPhone   = $tourRecord['coordinator_phone'] ?? '';
$coordinatorEmail   = $tourRecord['coordinator_email'] ?? '';
$generalNotes       = $tourRecord['general_notes'] ?? '';
$leaders            = $tourRecord['leaders'] ?? [];
$participants       = $tourRecord['participants'] ?? [];
$rawStops           = $tourRecord['stops'] ?? [];
$routeImageData     = $tourRecord['route_image_data'] ?? '';

// Calculate total tour duration in minutes
$totalTourMinutes = 0;
$formattedTime = '';
if (!empty($timeStart) && !empty($timeEnd)) {
    $formattedTime = $timeStart . ' – ' . $timeEnd;
    $tStart = DateTime::createFromFormat('H:i', $timeStart);
    $tEnd = DateTime::createFromFormat('H:i', $timeEnd);
    if ($tStart && $tEnd) {
        $interval = $tStart->diff($tEnd);
        $totalTourMinutes = ($interval->h * 60) + $interval->i;
        if ($interval->invert === 1) {
            $totalTourMinutes = (24 * 60) - $totalTourMinutes;
        }
    }
}

// Format Date for display
$formattedDate = $tourDate;
if (!empty($tourDate)) {
    $dt = DateTime::createFromFormat('Y-m-d', $tourDate);
    if ($dt) {
        $formattedDate = $dt->format('l, F j, Y');
    }
}

// Build Stop Table rows with cumulative schedules
$stops = [];
$totalAllocatedMinutes = 0;
$currentClock = !empty($timeStart) ? DateTime::createFromFormat('H:i', $timeStart) : null;

$stopCounter = 1;
foreach ($rawStops as $s) {
    $name = $s['name'] ?? '';
    $minutes = (int)($s['minutes'] ?? 0);
    $totalAllocatedMinutes += $minutes;

    $scheduleWindow = $minutes > 0 ? "{$minutes} min" : "Briefing Stop";
    if ($currentClock && $minutes > 0) {
        $windowStart = $currentClock->format('H:i');
        $currentClock->modify("+{$minutes} minutes");
        $windowEnd = $currentClock->format('H:i');
        $scheduleWindow = "{$windowStart} – {$windowEnd} ({$minutes} min)";
    }

    $stops[] = [
        'display_num' => $stopCounter++,
        'name'        => $name,
        'minutes'     => $minutes,
        'window'      => $scheduleWindow,
        'sme'         => $s['sme'] ?? '',
        'notes'       => $s['notes'] ?? '',
    ];
}

$hasRouteMap = is_string($routeImageData) && str_starts_with($routeImageData, 'data:image/png;base64,');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $tourTitle ?> — Official Tour Agenda & Report</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="report-body">
    <div class="report-toolbar no-print">
        <div class="toolbar-group">
            <a href="index.php" class="btn btn-secondary">&larr; Return to Builder</a>
            <a href="dashboard.php" class="btn btn-secondary">Tour Dashboard</a>
        </div>
        <div class="toolbar-group">
            <button onclick="window.print()" class="btn btn-primary">Print / Export to PDF</button>
        </div>
    </div>

    <!-- PAGE 1: COVER PAGE -->
    <div class="report-sheet cover-page-sheet">
        <div class="cover-page-inner">
            <div class="cover-logo-wrapper">
                <img src="assets/doe_logo.png" alt="U.S. Department of Energy" class="cover-logo">
            </div>
            <div class="cover-typography">
                <h1 class="cover-title"><?= $tourTitle ?></h1>
                <div class="cover-rule"></div>
                <div class="cover-date"><?= $formattedDate ?></div>
                <?php if (!empty($formattedTime)): ?>
                    <div class="cover-time"><?= $formattedTime ?> (<?= $totalTourMinutes ?> Minutes Total)</div>
                <?php endif; ?>
            </div>
            <div class="cover-footer-note">
                <strong>Tour Coordinator:</strong> <?= $coordinatorName ?: 'Tour Operations' ?>
                <?php if (!empty($coordinatorPhone)): ?>
                    <span class="bullet-sep">&bull;</span>
                    <span>Phone: <?= $coordinatorPhone ?></span>
                <?php endif; ?>
                <?php if (!empty($coordinatorEmail)): ?>
                    <span class="bullet-sep">&bull;</span>
                    <span>Email: <?= $coordinatorEmail ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- PAGE 2: BRIEFING AGENDA, ROSTER, MAP & PROTOCOLS -->
    <div class="report-sheet briefing-page-sheet">
        <header class="report-header">
            <div class="report-meta-top">
                <span class="badge">OFFICIAL TOUR AGENDA & BRIEFING</span>
                <span class="report-date"><?= $formattedDate ?></span>
            </div>
            <h2 class="report-title-page2"><?= $tourTitle ?> Tour</h2>
            <div class="report-details-grid">
                <div>
                    <strong>Date & Time Window:</strong> <br/> <?= $formattedDate ?> <?= $formattedTime ?>
                </div>
                <div>
                    <strong>Lead Coordinator:</strong> <?= $coordinatorName ?> <br/> <?= $coordinatorPhone ?> &bull; <?= $coordinatorEmail ?>
                </div>
            </div>
        </header>

        <!-- Personnel & Roster -->
        <section class="report-section compact-section">
            <h3 class="section-title">1. Personnel Roster</h3>
            <div class="grid-2 roster-tables">
                <div>
                    <h4 class="roster-heading">Tour Escorts & Leaders</h4>
                    <ul class="roster-list">
                        <?php if (empty($leaders)): ?>
                            <li class="empty-text">None specified</li>
                        <?php else: ?>
                            <?php foreach ($leaders as $l): ?>
                                <li>
                                    <strong><?= is_array($l) ? ($l['name'] ?? '') : (string)$l ?></strong>
                                    <?php if (is_array($l) && !empty($l['org'])): ?>
                                        <span class="person-org-label">(<?= $l['org'] ?>)</span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
                <div>
                    <h4 class="roster-heading">Attending Participants & Guests</h4>
                    <ul class="roster-list">
                        <?php if (empty($participants)): ?>
                            <li class="empty-text">None specified</li>
                        <?php else: ?>
                            <?php foreach ($participants as $p): ?>
                                <li>
                                    <strong><?= is_array($p) ? ($p['name'] ?? '') : (string)$p ?></strong>
                                    <?php if (is_array($p) && !empty($p['org'])): ?>
                                        <span class="person-org-label">(<?= $p['org'] ?>)</span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Facility Itinerary -->
        <section class="report-section compact-section">
            <div class="flex-between">
                <h3 class="section-title">2. Facility Itinerary & Subject Matter Experts</h3>
                <div class="time-summary-tag">
                    Allocated: <strong><?= $totalAllocatedMinutes ?></strong> / <strong><?= $totalTourMinutes ?></strong> mins
                </div>
            </div>

            <?php if (empty($stops)): ?>
                <p class="empty-text">No facility stops entered.</p>
            <?php else: ?>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th style="width: 22%;">Schedule Window</th>
                            <th style="width: 26%;">Facility / Location</th>
                            <th style="width: 22%;">Subject Matter Expert (SME)</th>
                            <th style="width: 22%;">Briefing Focus & Requirements</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stops as $stop): ?>
                            <tr>
                                <td><?= $stop['window'] ?></td>
                                <td><strong><?= $stop['name'] ?></strong></td>
                                <td><?= $stop['sme'] ?: '<em>None designated</em>' ?></td>
                                <td><?= !empty($stop['notes']) ? nl2br($stop['notes']) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <!-- Route Map Section -->
        <?php if ($hasRouteMap): ?>
            <section class="report-section compact-section">
                <h3 class="section-title">3. Tour Route Map</h3>
                <div class="map-container-compact">
                    <img src="<?= clean($routeImageData) ?>" alt="Tour Route Map" class="report-map-img">
                </div>
            </section>
        <?php endif; ?>

        <!-- Protocol & Safety Notes -->
        <?php if (!empty($generalNotes)): ?>
            <section class="report-section compact-section">
                <h3 class="section-title">4. Operational Protocols, Safety & Contingency Plans</h3>
                <div class="notes-content-compact">
                    <?= nl2br($generalNotes) ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
</body>
</html>
