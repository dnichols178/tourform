<?php
declare(strict_types=1);

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0755, true);
}

function clean(mixed $value): string {
    return htmlspecialchars(trim((string)($value ?? '')), ENT_QUOTES, 'UTF-8');
}

function formatTime12(string $timeStr): string {
    $timeStr = trim($timeStr);
    if ($timeStr === '') {
        return '';
    }
    $dt = DateTime::createFromFormat('H:i', $timeStr) ?: DateTime::createFromFormat('H:i:s', $timeStr);
    if ($dt) {
        return $dt->format('g:i A');
    }
    $ts = strtotime($timeStr);
    return ($ts !== false) ? date('g:i A', $ts) : $timeStr;
}

// Calculate departure time by adding duration minutes to arrival time
function calculateDepartureTime(string $arrivalStr, int $durationMinutes): string {
    $arrivalStr = trim($arrivalStr);
    if ($arrivalStr === '') return '';
    $dt = DateTime::createFromFormat('H:i', $arrivalStr) ?: DateTime::createFromFormat('H:i:s', $arrivalStr);
    if (!$dt) {
        $ts = strtotime($arrivalStr);
        if ($ts !== false) {
            $dt = (new DateTime())->setTimestamp($ts);
        }
    }
    if ($dt) {
        $dt->modify("+{$durationMinutes} minutes");
        return $dt->format('g:i A');
    }
    return '';
}

$tourRecord = null;
$tourId = '';

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
        header('Location: index.php?msg=not_found');
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $existingId = !empty($_POST['tour_id']) ? basename((string)$_POST['tour_id']) : '';
    $createdAt = date('c');
    $prevRecord = [];

    if ($existingId !== '') {
        $tourId = $existingId;
        $existingFile = $dataDir . '/' . $tourId . '.json';
        if (file_exists($existingFile)) {
            $prevJson = file_get_contents($existingFile);
            if ($prevJson !== false) {
                $prevRecord = json_decode($prevJson, true) ?: [];
                if (!empty($prevRecord['created_at'])) {
                    $createdAt = (string)$prevRecord['created_at'];
                }
            }
        }
    } else {
        $tourId = 'tour_' . time() . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
    }

    $tourTitle          = clean($_POST['tour_title'] ?? 'Tour Agenda & Report');
    $tourDate           = clean($_POST['tour_date'] ?? '');
    $timeStart          = clean($_POST['time_start'] ?? '');
    $timeEnd            = clean($_POST['time_end'] ?? '');
    $coordinatorName    = clean($_POST['coordinator_name'] ?? '');
    $coordinatorPhone   = clean($_POST['coordinator_phone'] ?? '');
    $coordinatorEmail   = clean($_POST['coordinator_email'] ?? '');
    $generalNotes       = clean($_POST['general_notes'] ?? '');
    $showArrows         = !empty($_POST['show_arrows']);

    // Capture, preserve, and sanitize route image payload
    $rawRoute = trim((string)($_POST['route_image_data'] ?? ''));
    if ($rawRoute === '' && !empty($prevRecord['route_image_data'])) {
        $rawRoute = trim((string)$prevRecord['route_image_data']);
    }

    $rawRoute = html_entity_decode($rawRoute, ENT_QUOTES, 'UTF-8');
    $rawRoute = trim($rawRoute, " \t\n\r\0\x0B\"'");

    if (str_starts_with($rawRoute, 'data:image/')) {
        $parts = explode(',', $rawRoute, 2);
        if (count($parts) === 2 && str_contains($parts[0], 'base64')) {
            $rawRoute = $parts[0] . ',' . str_replace(' ', '+', $parts[1]);
        }
    } elseif (str_starts_with($rawRoute, 'iVBORw0KGgo')) {
        $rawRoute = 'data:image/png;base64,' . str_replace(' ', '+', $rawRoute);
    }
    $routeImageData = $rawRoute;

    // Preserve map state for consistent viewport editing
    $rawMapState = $_POST['map_state'] ?? ($prevRecord['map_state'] ?? '');
    $mapState = is_array($rawMapState) ? json_encode($rawMapState) : (string)$rawMapState;

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

    $facilityNames        = is_array($_POST['facility_names'] ?? null) ? $_POST['facility_names'] : [];
    $facilityMinutes      = is_array($_POST['facility_minutes'] ?? null) ? $_POST['facility_minutes'] : [];
    $facilityArrivalTimes = is_array($_POST['facility_arrival_times'] ?? null) ? $_POST['facility_arrival_times'] : [];
    $facilitySmes         = is_array($_POST['facility_smes'] ?? null) ? $_POST['facility_smes'] : [];
    $facilityNotes        = is_array($_POST['facility_notes'] ?? null) ? $_POST['facility_notes'] : [];
    $facilityIsStart      = is_array($_POST['facility_is_start'] ?? null) ? $_POST['facility_is_start'] : [];

    $stops = [];
    $totalStops = count($facilityNames);
    for ($i = 0; $i < $totalStops; $i++) {
        $name = clean($facilityNames[$i] ?? '');
        if ($name === '') {
            continue;
        }

        $rawMins = isset($facilityMinutes[$i]) ? trim((string)$facilityMinutes[$i]) : '';
        $mins = ($rawMins !== '' && is_numeric($rawMins)) ? max(0, (int)$rawMins) : 0;
        $isStart = ($i === 0) || !empty($facilityIsStart[$i]);

        $stops[] = [
            'name'         => $name,
            'minutes'      => $mins,
            'arrival_time' => clean($facilityArrivalTimes[$i] ?? ''),
            'sme'          => clean($facilitySmes[$i] ?? ''),
            'notes'        => clean($facilityNotes[$i] ?? ''),
            'is_start'     => $isStart
        ];
    }

    $tourRecord = [
        'id'                  => $tourId,
        'created_at'          => $createdAt,
        'updated_at'          => date('c'),
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
        'map_state'           => $mapState,
    ];

    file_put_contents($dataDir . '/' . $tourId . '.json', json_encode($tourRecord, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
} else {
    header('Location: index.php');
    exit;
}

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
$routeImageData     = (string)($tourRecord['route_image_data'] ?? '');

if (str_starts_with($routeImageData, 'data:image/')) {
    $parts = explode(',', $routeImageData, 2);
    if (count($parts) === 2 && str_contains($parts[0], 'base64')) {
        $routeImageData = $parts[0] . ',' . str_replace(' ', '+', $parts[1]);
    }
} elseif (str_starts_with($routeImageData, 'iVBORw0KGgo')) {
    $routeImageData = 'data:image/png;base64,' . str_replace(' ', '+', $routeImageData);
}

$start12 = formatTime12($timeStart);
$end12   = formatTime12($timeEnd);

$formattedTime = '';
if ($start12 !== '' && $end12 !== '') {
    $formattedTime = $start12 . ' – ' . $end12;
} elseif ($start12 !== '') {
    $formattedTime = $start12;
}

$totalTourMinutes = 0;
if (!empty($timeStart) && !empty($timeEnd)) {
    $tStart = DateTime::createFromFormat('H:i', $timeStart);
    $tEnd   = DateTime::createFromFormat('H:i', $timeEnd);
    if ($tStart && $tEnd) {
        $interval = $tStart->diff($tEnd);
        $totalTourMinutes = ($interval->h * 60) + $interval->i;
        if ($interval->invert === 1) {
            $totalTourMinutes = (24 * 60) - $totalTourMinutes;
        }
    }
}

$formattedDate = $tourDate;
if (!empty($tourDate)) {
    $dt = DateTime::createFromFormat('Y-m-d', $tourDate);
    if ($dt) {
        $formattedDate = $dt->format('l, F j, Y');
    }
}

$stops = [];
$totalAllocatedMinutes = 0;
$stopCounter = 1;

foreach ($rawStops as $idx => $s) {
    $name = $s['name'] ?? '';
    $minutes = (int)($s['minutes'] ?? 0);
    // Only add to total minutes if it is NOT the first row
    if ($idx !== 0) {
        $totalAllocatedMinutes += $minutes;
    }
    $isStart = ($idx === 0) || !empty($s['is_start']);
    $arrivalTime = trim((string)($s['arrival_time'] ?? ''));

    // If this is the default Start location and no arrival time was explicitly provided,
    // default to the tour's scheduled start time ($timeStart)
    if ($isStart && $arrivalTime === '' && !empty($timeStart)) {
        $arrivalTime = $timeStart;
    }

    // 1. Arrival Time column: 9:30 AM format, or N/A if not listed
    $arrivalDisplay = 'N/A';
    if ($arrivalTime !== '') {
        $formattedArrival = formatTime12($arrivalTime);
        $arrivalDisplay = $formattedArrival !== '' ? $formattedArrival : 'N/A';
    }

    // 2. Duration column: Minutes with calculated departure time suffix
        if ($idx === 0) {
        $durationDisplay = 'Tour Start';
		} elseif ($minutes > 0) {
        $unit = ($minutes === 1) ? 'minute' : 'minutes';
        if ($arrivalTime !== '') {
            $depFormatted = calculateDepartureTime($arrivalTime, $minutes);
            if ($depFormatted !== '') {
                $durationDisplay = "{$minutes} {$unit} (depart at {$depFormatted})";
            } else {
                $durationDisplay = "{$minutes} {$unit}";
            }
        } else {
            $durationDisplay = "{$minutes} {$unit}";
        }
    } else {
        $durationDisplay = '0 minutes';
    }

    $stops[] = [
        'display_num'      => $stopCounter++,
        'name'             => $name,
        'minutes'          => $minutes,
        'arrival_display'  => $arrivalDisplay,
        'duration_display' => $durationDisplay,
        'arrival_time'     => $arrivalTime,
        'is_start'         => $isStart,
        'sme'              => $s['sme'] ?? '',
        'notes'            => $s['notes'] ?? '',
    ];
}

$hasRouteMap = is_string($routeImageData) && (
    str_starts_with($routeImageData, 'data:image/') ||
    str_starts_with($routeImageData, 'http://') ||
    str_starts_with($routeImageData, 'https://') ||
    str_starts_with($routeImageData, 'assets/') ||
    str_starts_with($routeImageData, '/') ||
    preg_match('/\.(png|jpe?g|gif|webp|svg)$/i', $routeImageData)
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $tourTitle ?> – Tour Agenda & Report</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .route-map-section {
            width: 100% !important;
            max-width: 100% !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
            box-sizing: border-box !important;
            break-inside: avoid !important;
            page-break-inside: avoid !important;
        }
        .route-map-section .section-title {
            break-after: avoid !important;
            page-break-after: avoid !important;
        }
        .route-map-section .map-container-compact {
            width: 100% !important;
            max-width: 100% !important;
            padding: 0 !important;
            margin: 0 !important;
            box-sizing: border-box !important;
            border: 1px solid #d1d5db !important;
            overflow: hidden !important;
            break-inside: avoid !important;
            page-break-inside: avoid !important;
        }
        .route-map-section .report-map-img {
            width: 100% !important;
            min-width: 100% !important;
            max-width: 100% !important;
            height: auto !important;
            max-height: none !important;
            display: block !important;
            margin: 0 !important;
            box-sizing: border-box !important;
            object-fit: contain !important;
            break-inside: avoid !important;
            page-break-inside: avoid !important;
        }

        .section-break-next-page {
            break-before: page !important;
            page-break-before: always !important;
            margin-top: 24px !important;
        }

        /* Default Start Location Row Highlight in Section 2 Itinerary */
        .report-table tr.highlight-start-row {
            background-color: #f0fdf4 !important;
        }
        .report-table tr.highlight-start-row td {
            border-bottom: 1px solid #bbf7d0 !important;
        }
        .start-location-badge {
            display: inline-block;
            background: #16a34a;
            color: #ffffff;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
            text-transform: uppercase;
            margin-right: 6px;
            letter-spacing: 0.5px;
            vertical-align: middle;
        }

        @media print {
            .cover-page-sheet {
                height: 98vh !important;
                min-height: 98vh !important;
                max-height: 98vh !important;
                page-break-after: always !important;
                break-after: page !important;
            }
            .cover-footer-note {
                margin-top: auto !important;
                position: relative !important;
                bottom: 0 !important;
            }
            .briefing-page-sheet {
                height: auto !important;
                min-height: auto !important;
                overflow: visible !important;
            }
            .route-map-section {
                width: 100% !important;
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }
            .route-map-section .section-title {
                break-after: avoid !important;
                page-break-after: avoid !important;
            }
            .route-map-section .report-map-img {
                width: 100% !important;
                max-width: 100% !important;
                max-height: none !important;
            }
            .report-table tr.highlight-start-row {
                background-color: #f0fdf4 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body class="report-body">
    <div class="report-toolbar no-print">
        <div class="toolbar-group">
            <a href="create.php?id=<?= $tourId ?>" class="btn btn-secondary">&larr; Edit Tour</a>
            <a href="index.php" class="btn btn-secondary">Tour Dashboard</a>
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
                    <div class="cover-time">
                        <?= $formattedTime ?>
                    </div>
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
                <span class="badge">TOUR AGENDA & BRIEFING</span>
                <span class="report-date">Date of Tour: <?= $formattedDate ?></span>
            </div>
            <h2 class="report-title-page2"><?= $tourTitle ?> Tour</h2>
            <div class="report-details-grid">
                <div>
                    <strong>Date & Time:</strong> <br/> <?= $formattedDate ?><?= !empty($formattedTime) ? ', ' . $formattedTime : '' ?>
                </div>
                <div>
                    <strong>Lead Coordinator:</strong> <?= $coordinatorName ?> <br/> <?= $coordinatorPhone ?> &bull; <?= $coordinatorEmail ?>
                </div>
            </div>
        </header>

        <!-- Personnel & Roster (Section 1) -->
        <section class="report-section compact-section" id="sectionRoster">
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

        <!-- Facility Itinerary (Section 2) -->
        <section class="report-section compact-section" id="sectionItinerary">
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
                            <th style="width: 14%;">Arrival Time</th>
                            <th style="width: 14%;">Duration</th>
                            <th style="width: 25%;">Location</th>
                            <th style="width: 19%;">Subject Matter Expert (SME)</th>
                            <th style="width: 19%;">PPE & Requirements</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stops as $stop): ?>
                            <tr class="<?= !empty($stop['is_start']) ? 'highlight-start-row' : '' ?>">
                                <td><?= clean($stop['arrival_display']) ?></td>
                                <td><?= clean($stop['duration_display']) ?></td>
                                <td>
                                    <!--<?php if (!empty($stop['is_start'])): ?>
                                        <span class="start-location-badge">Start</span>
                                    <?php endif; ?>-->
                                    <strong><?= clean($stop['name']) ?></strong>
                                </td>
                                <td><?= $stop['sme'] ?: '<em>None designated</em>' ?></td>
                                <td><?= !empty($stop['notes']) ? nl2br(clean($stop['notes'])) : '&nbsp;' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <!-- Route Map Section (Section 3) -->
        <?php if ($hasRouteMap): ?>
            <section class="report-section compact-section route-map-section" id="sectionRouteMap">
                <h3 class="section-title">3. Tour Route Map</h3>
                <div class="map-container-compact">
                    <img src="<?= htmlspecialchars($routeImageData, ENT_QUOTES, 'UTF-8') ?>" alt="Tour Route Map" class="report-map-img" id="routeMapImg">
                </div>
            </section>
        <?php endif; ?>

        <!-- Protocol & Safety Notes (Section 4) -->
        <?php if (!empty($generalNotes)): ?>
            <section class="report-section compact-section" id="sectionNotes">
                <h3 class="section-title">4. Operational Protocols, Safety & Contingency Plans</h3>
                <div class="notes-content-compact">
                    <?= nl2br($generalNotes) ?>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <script>
        function evaluateSection3PageBreak() {
            const sheet = document.querySelector('.briefing-page-sheet');
            const mapSection = document.getElementById('sectionRouteMap');
            if (!sheet || !mapSection) return;

            mapSection.classList.remove('section-break-next-page');

            const sheetRect = sheet.getBoundingClientRect();
            const mapRect = mapSection.getBoundingClientRect();
            const offsetTop = mapRect.top - sheetRect.top;
            const mapHeight = mapSection.offsetHeight;

            const maxPageHeight = 960;

            if (offsetTop + mapHeight > maxPageHeight) {
                mapSection.classList.add('section-break-next-page');
            }
        }

        window.addEventListener('load', evaluateSection3PageBreak);
        window.addEventListener('resize', evaluateSection3PageBreak);
        window.addEventListener('beforeprint', evaluateSection3PageBreak);

        const imgEl = document.getElementById('routeMapImg');
        if (imgEl) {
            if (imgEl.complete) {
                evaluateSection3PageBreak();
            } else {
                imgEl.addEventListener('load', evaluateSection3PageBreak);
            }
        }
    </script>
</body>
</html>
