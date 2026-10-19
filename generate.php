<?php
declare(strict_types=1);

$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0755, true);
}

function clean(mixed $value): string {
    return htmlspecialchars(trim((string)($value ?? '')), ENT_QUOTES, 'UTF-8');
}

// 12-Hour AM/PM conversion
function format12Hour(string $timeStr): string {
    $timeStr = trim($timeStr);
    if ($timeStr === '') return '';
    $timestamp = strtotime($timeStr);
    if ($timestamp === false) return $timeStr;
    return date('g:i A', $timestamp);
}

// Departure calculation: Arrival + Duration
function calculateDeparture(string $arrivalStr, int $durationMinutes): string {
    $arrivalStr = trim($arrivalStr);
    if ($arrivalStr === '') return '';
    $timestamp = strtotime($arrivalStr);
    if ($timestamp === false) return '';
    $depTimestamp = strtotime("+{$durationMinutes} minutes", $timestamp);
    return date('g:i A', $depTimestamp);
}

// Process POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tourId = !empty($_POST['tour_id']) ? basename((string)$_POST['tour_id']) : 'tour_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));
    
    $tourTitle        = trim((string)($_POST['tour_title'] ?? ''));
    $tourDate         = trim((string)($_POST['tour_date'] ?? ''));
    $timeStart        = trim((string)($_POST['time_start'] ?? ''));
    $timeEnd          = trim((string)($_POST['time_end'] ?? ''));
    $coordinatorName  = trim((string)($_POST['coordinator_name'] ?? ''));
    $coordinatorPhone = trim((string)($_POST['coordinator_phone'] ?? ''));
    $coordinatorEmail = trim((string)($_POST['coordinator_email'] ?? ''));
    $generalNotes     = trim((string)($_POST['general_notes'] ?? ''));
    $showArrows       = !empty($_POST['show_arrows']);
    $routeImageData   = trim((string)($_POST['route_image_data'] ?? ''));
    $mapStateRaw      = trim((string)($_POST['map_state'] ?? ''));
    
    // Assemble Leaders
    $leaders = [];
    $leaderNames = $_POST['leader_names'] ?? [];
    $leaderOrgs  = $_POST['leader_orgs'] ?? [];
    if (is_array($leaderNames)) {
        foreach ($leaderNames as $i => $name) {
            $n = trim((string)$name);
            $o = trim((string)($leaderOrgs[$i] ?? ''));
            if ($n !== '') {
                $leaders[] = ['name' => $n, 'org' => $o];
            }
        }
    }

    // Assemble Participants
    $participants = [];
    $partNames = $_POST['participant_names'] ?? [];
    $partOrgs  = $_POST['participant_orgs'] ?? [];
    if (is_array($partNames)) {
        foreach ($partNames as $i => $name) {
            $n = trim((string)$name);
            $o = trim((string)($partOrgs[$i] ?? ''));
            if ($n !== '') {
                $participants[] = ['name' => $n, 'org' => $o];
            }
        }
    }

    // Assemble Stops with Arrival Time & 0-Minute Duration support
    $stops = [];
    $facNames    = $_POST['facility_names'] ?? [];
    $facMins     = $_POST['facility_minutes'] ?? [];
    $facArrivals = $_POST['facility_arrival_times'] ?? [];
    $facSmes     = $_POST['facility_smes'] ?? [];
    $facNotes    = $_POST['facility_notes'] ?? [];
    $facIsStart  = $_POST['facility_is_start'] ?? [];

    if (is_array($facNames)) {
        foreach ($facNames as $i => $name) {
            $n = trim((string)$name);
            if ($n === '') continue;

            $isStart = ($i === 0) || !empty($facIsStart[$i]);
            $rawMins = isset($facMins[$i]) ? trim((string)$facMins[$i]) : '';
            $duration = ($rawMins !== '' && is_numeric($rawMins)) ? max(0, (int)$rawMins) : 0;
            $arrival = isset($facArrivals[$i]) ? trim((string)$facArrivals[$i]) : '';
            $sme = isset($facSmes[$i]) ? trim((string)$facSmes[$i]) : '';
            $notes = isset($facNotes[$i]) ? trim((string)$facNotes[$i]) : '';

            $stops[] = [
                'name'         => $n,
                'minutes'      => $duration,
                'arrival_time' => $arrival,
                'sme'          => $sme,
                'notes'        => $notes,
                'is_start'     => $isStart
            ];
        }
    }

    // Save JSON record
    $record = [
        'tour_id'           => $tourId,
        'tour_title'        => $tourTitle,
        'tour_date'         => $tourDate,
        'time_start'        => $timeStart,
        'time_end'          => $timeEnd,
        'coordinator_name'  => $coordinatorName,
        'coordinator_phone' => $coordinatorPhone,
        'coordinator_email' => $coordinatorEmail,
        'general_notes'     => $generalNotes,
        'show_arrows'       => $showArrows,
        'leaders'           => $leaders,
        'participants'      => $participants,
        'stops'             => $stops,
        'route_image_data'  => $routeImageData,
        'map_state'         => $mapStateRaw ? json_decode($mapStateRaw, true) : [],
        'updated_at'        => date('c')
    ];

    file_put_contents($dataDir . '/' . $tourId . '.json', json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['id'])) {
    $tourId = basename((string)$_GET['id']);
    $filePath = $dataDir . '/' . $tourId . '.json';
    if (!file_exists($filePath)) {
        die('Tour record not found.');
    }
    $record = json_decode((string)file_get_contents($filePath), true);
    $tourTitle        = (string)($record['tour_title'] ?? '');
    $tourDate         = (string)($record['tour_date'] ?? '');
    $timeStart        = (string)($record['time_start'] ?? '');
    $timeEnd          = (string)($record['time_end'] ?? '');
    $coordinatorName  = (string)($record['coordinator_name'] ?? '');
    $coordinatorPhone = (string)($record['coordinator_phone'] ?? '');
    $coordinatorEmail = (string)($record['coordinator_email'] ?? '');
    $generalNotes     = (string)($record['general_notes'] ?? '');
    $showArrows       = !empty($record['show_arrows']);
    $leaders          = is_array($record['leaders'] ?? null) ? $record['leaders'] : [];
    $participants     = is_array($record['participants'] ?? null) ? $record['participants'] : [];
    $stops            = is_array($record['stops'] ?? null) ? $record['stops'] : [];
    $routeImageData   = (string)($record['route_image_data'] ?? '');
} else {
    header('Location: index.php');
    exit;
}

// Calculate summary minutes
$totalTourMinutes = 0;
if ($timeStart !== '' && $timeEnd !== '') {
    $ts = strtotime($timeStart);
    $te = strtotime($timeEnd);
    if ($ts !== false && $te !== false) {
        $diff = ($te - $ts) / 60;
        $totalTourMinutes = (int)($diff < 0 ? $diff + 1440 : $diff);
    }
}
$allocatedMinutes = array_sum(array_column($stops, 'minutes'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= clean($tourTitle) ?> - Official Tour Agenda</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #ffffff !important; margin: 0; padding: 0; }
            .report-container { box-shadow: none !important; margin: 0 !important; width: 100% !important; padding: 0 !important; }
            .page-break { page-break-before: always; }
        }
        .report-toolbar {
            position: sticky;
            top: 0;
            background: #0f172a;
            color: #ffffff;
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .report-page {
            background: #ffffff;
            max-width: 900px;
            margin: 24px auto;
            padding: 40px 48px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-radius: 8px;
            box-sizing: border-box;
            position: relative;
        }

        /* Cover Page Styling */
        .cover-page {
            min-height: 1050px;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
        }
        .cover-header {
            text-align: center;
            margin-top: 40px;
            margin-bottom: 50px;
        }
        .cover-logo {
            max-height: 90px;
            margin-bottom: 24px;
        }
        .cover-title {
            font-size: 32px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 16px;
            line-height: 1.25;
        }
        .cover-date {
            font-size: 20px;
            font-weight: 600;
            color: #2563eb;
            margin: 0 0 10px;
        }
        .cover-time {
            font-size: 16px;
            color: #475569;
            font-weight: 500;
        }
        .cover-meta-grid {
            margin-top: 40px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 24px;
        }
        .cover-footer-note {
            margin-top: auto;
            padding-top: 24px;
            border-top: 2px solid #e2e8f0;
            text-align: center;
            font-size: 12px;
            color: #64748b;
        }

        /* Briefing Report & Itinerary Table */
        .section-heading {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 6px;
            margin-top: 28px;
            margin-bottom: 14px;
            font-size: 17px;
            color: #0f172a;
            display: flex;
            justify-content: space-between;
            align-items: baseline;
        }
        .table-itinerary {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            margin-bottom: 24px;
        }
        .table-itinerary th {
            background: #f1f5f9;
            color: #1e293b;
            text-align: left;
            padding: 9px 12px;
            border-bottom: 2px solid #cbd5e1;
            font-weight: 700;
        }
        .table-itinerary td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }

        /* Highlight for the default Start location row */
        .table-itinerary tr.row-start-stop {
            background-color: #f0fdf4 !important;
        }
        .table-itinerary tr.row-start-stop td {
            border-bottom: 1px solid #bbf7d0;
        }
        .badge-start-pill {
            display: inline-block;
            background: #16a34a;
            color: #ffffff;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 3px;
            margin-right: 6px;
            text-transform: uppercase;
        }

        .schedule-window-cell {
            white-space: nowrap;
            font-weight: 600;
            color: #1e293b;
        }
        .schedule-window-cell .sub-window {
            display: block;
            font-size: 11px;
            color: #64748b;
            font-weight: normal;
            margin-top: 2px;
        }
        .roster-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .roster-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 14px 18px;
        }
        .roster-box h4 {
            margin: 0 0 10px;
            font-size: 14px;
            color: #0f172a;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
        }
        .roster-box ul {
            margin: 0;
            padding-left: 18px;
            font-size: 13px;
            color: #334155;
        }
        .roster-box li {
            margin-bottom: 4px;
        }
        .map-print-container {
            width: 100%;
            margin-top: 14px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            overflow: hidden;
            background: #ffffff;
        }
        .map-print-container img {
            display: block;
            width: 100%;
            height: auto;
        }
    </style>
</head>
<body>
    <div class="report-toolbar no-print">
        <div class="toolbar-title">
            <strong>Tour Report:</strong> <?= clean($tourTitle) ?> (<?= clean($tourDate) ?>)
        </div>
        <div class="toolbar-actions">
            <a href="create.php?id=<?= urlencode($tourId) ?>" class="btn btn-secondary btn-sm">&larr; Edit in Builder</a>
            <a href="index.php" class="btn btn-secondary btn-sm">All Tours</a>
            <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">Print / Save PDF</button>
        </div>
    </div>

    <!-- PAGE 1: Dedicated Executive Cover Page -->
    <div class="report-page cover-page">
        <div class="cover-header">
            <?php if (file_exists(__DIR__ . '/assets/doe_log.png')): ?>
                <img src="assets/doe_log.png" alt="Department of Energy Logo" class="cover-logo">
            <?php endif; ?>
            <h1 class="cover-title"><?= clean($tourTitle) ?></h1>
            <div class="cover-date"><?= clean($tourDate ? date('F j, Y', strtotime($tourDate)) : 'Date Unscheduled') ?></div>
            <div class="cover-time">
                <?= clean(format12Hour($timeStart)) ?> – <?= clean(format12Hour($timeEnd)) ?>
                <?php if ($totalTourMinutes > 0): ?>
                    (<?= $totalTourMinutes ?> Minutes Allocated)
                <?php endif; ?>
            </div>
        </div>

        <div class="cover-meta-grid">
            <div>
                <strong>Primary Coordinator:</strong><br>
                <?= clean($coordinatorName ?: 'Unassigned') ?><br>
                <?= clean($coordinatorPhone) ?><br>
                <?= clean($coordinatorEmail) ?>
            </div>
            <div>
                <strong>Tour Logistics Overview:</strong><br>
                Scheduled Stops: <?= count($stops) ?><br>
                Total Stop Time: <?= $allocatedMinutes ?> mins<br>
                Buffer / Transit: <?= max(0, $totalTourMinutes - $allocatedMinutes) ?> mins
            </div>
        </div>

        <?php if (!empty($leaders)): ?>
            <div class="roster-box" style="margin-top: 24px;">
                <h4>Official Tour Leaders / Escorts</h4>
                <ul>
                    <?php foreach ($leaders as $ldr): ?>
                        <li>
                            <strong><?= clean($ldr['name']) ?></strong>
                            <?= !empty($ldr['org']) ? ' &mdash; ' . clean($ldr['org']) : '' ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="cover-footer-note">
            Department of Energy Paducah Site Facility Tour Operations &bull; Official Use &bull; Generated <?= date('m/d/Y g:i A') ?>
        </div>
    </div>

    <!-- PAGE BREAK -->
    <div class="page-break"></div>

    <!-- PAGE 2: Briefing Details, Roster, Itinerary & Route Map -->
    <div class="report-page">
        <!-- Section 1: Attendance Roster -->
        <div class="section-heading">
            <span>1. Personnel & Attendance</span>
            <span style="font-size: 12px; font-weight: normal; color: #64748b;"><?= count($participants) ?> Confirmed Attendees</span>
        </div>
        <div class="roster-grid">
            <div class="roster-box" style="grid-column: span 2;">
                <h4>Visitor & Official Participant Roster</h4>
                <?php if (!empty($participants)): ?>
                    <ul style="column-count: 2; column-gap: 24px;">
                        <?php foreach ($participants as $part): ?>
                            <li>
                                <strong><?= clean($part['name']) ?></strong>
                                <?= !empty($part['org']) ? ' (' . clean($part['org']) . ')' : '' ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p style="margin: 0; font-size: 13px; color: #64748b;">No external attendees registered.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Section 2: Facility Itinerary Table -->
        <div class="section-heading">
            <span>2. Tour Itinerary & Facility Schedule</span>
            <span style="font-size: 12px; font-weight: normal; color: #64748b;"><?= count($stops) ?> Stops Scheduled</span>
        </div>
        <table class="table-itinerary">
            <thead>
                <tr>
                    <th style="width: 32%;">Location / Facility</th>
                    <th style="width: 28%;">Schedule Window</th>
                    <th style="width: 20%;">Subject Matter Expert</th>
                    <th style="width: 20%;">Notes & Protocols</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($stops)): ?>
                    <?php foreach ($stops as $idx => $s): 
                        $isStart = !empty($s['is_start']) || ($idx === 0);
                        $mins = (int)($s['minutes'] ?? 0);
                        $arrival = trim((string)($s['arrival_time'] ?? ''));

                        // Schedule window logic
                        $scheduleWindowHtml = 'N/A';
                        if ($mins > 0) {
                            if ($arrival !== '') {
                                $arrFmt = format12Hour($arrival);
                                $depFmt = calculateDeparture($arrival, $mins);
                                $scheduleWindowHtml = "Arrive at {$arrFmt}, {$mins} minute stop / Depart at {$depFmt}";
                            } else {
                                $scheduleWindowHtml = "{$mins} minute stop";
                            }
                        } else {
                            $scheduleWindowHtml = 'N/A';
                        }
                    ?>
                        <tr class="<?= $isStart ? 'row-start-stop' : '' ?>">
                            <td>
                                <?php if ($isStart): ?>
                                    <span class="badge-start-pill">Start</span>
                                    <strong><?= clean($s['name']) ?></strong>
                                <?php else: ?>
                                    <strong><?= clean($s['name']) ?></strong>
                                <?php endif; ?>
                            </td>
                            <td class="schedule-window-cell">
                                <?= clean($scheduleWindowHtml) ?>
                            </td>
                            <td>
                                <?= clean($s['sme'] ?: '—') ?>
                            </td>
                            <td>
                                <?= clean($s['notes'] ?: '—') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align: center; color: #64748b;">No stops listed for this tour.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Section 3: Route Map -->
        <div class="section-heading">
            <span>3. Tour Route Map</span>
            <span style="font-size: 12px; font-weight: normal; color: #64748b;">Paducah Facility Geographic Extents</span>
        </div>
        <?php if (!empty($routeImageData)): ?>
            <div class="map-print-container">
                <img src="<?= $routeImageData ?>" alt="Tour Route Map Extent">
            </div>
        <?php else: ?>
            <p style="font-size: 13px; color: #64748b;">No route map snapshot captured.</p>
        <?php endif; ?>

        <!-- Section 4: General Notes -->
        <?php if (!empty($generalNotes)): ?>
            <div class="section-heading" style="margin-top: 24px;">
                <span>4. Additional Instructions & Safety Protocols</span>
            </div>
            <div class="roster-box">
                <p style="margin: 0; font-size: 13px; line-height: 1.5; white-space: pre-line;"><?= clean($generalNotes) ?></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
