<?php
declare(strict_types=1);

// Set timezone to Central Time
date_default_timezone_set('America/Chicago');

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data || !is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Invalid payload']);
    exit;
}

$tourTitle = trim((string)($data['tour_title'] ?? ''));
if ($tourTitle === '') {
    echo json_encode(['success' => false, 'error' => 'A tour title is required before saving a draft.']);
    exit;
}

// Save directly to /data matching index.php and generate.php
$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0755, true);
}

// Preserve existing ID or create new one
$existingId = !empty($data['tour_id']) ? basename(trim((string)$data['tour_id'])) : '';
$createdAt = date('c');

if ($existingId !== '') {
    $tourId = $existingId;
    $existingFile = $dataDir . '/' . $tourId . '.json';
    if (file_exists($existingFile)) {
        $prevData = json_decode((string)file_get_contents($existingFile), true);
        if (!empty($prevData['created_at'])) {
            $createdAt = (string)$prevData['created_at'];
        }
    }
} else {
    $tourId = 'tour_' . time() . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
}

function cleanVal(mixed $v): string {
    return htmlspecialchars(trim((string)($v ?? '')), ENT_QUOTES, 'UTF-8');
}

// Build leaders
$leaders = [];
$leaderNames = $data['leader_names'] ?? [];
$leaderOrgs  = $data['leader_orgs'] ?? [];
if (is_array($leaderNames)) {
    foreach ($leaderNames as $i => $name) {
        $cleanName = cleanVal($name);
        if ($cleanName !== '') {
            $leaders[] = [
                'name' => $cleanName,
                'org'  => cleanVal($leaderOrgs[$i] ?? '')
            ];
        }
    }
}

// Build participants
$participants = [];
$partNames = $data['participant_names'] ?? [];
$partOrgs  = $data['participant_orgs'] ?? [];
if (is_array($partNames)) {
    foreach ($partNames as $i => $name) {
        $cleanName = cleanVal($name);
        if ($cleanName !== '') {
            $participants[] = [
                'name' => $cleanName,
                'org'  => cleanVal($partOrgs[$i] ?? '')
            ];
        }
    }
}

// Build stops array so index.php and generate.php can read stop counts & minutes
$stops = [];
$facNames    = $data['facility_names'] ?? [];
$facMins     = $data['facility_minutes'] ?? [];
$facArrivals = $data['facility_arrival_times'] ?? [];
$facSmes     = $data['facility_smes'] ?? [];
$facNotes    = $data['facility_notes'] ?? [];
$facIsStart  = $data['facility_is_start'] ?? [];

if (is_array($facNames)) {
    $total = count($facNames);
    for ($i = 0; $i < $total; $i++) {
        $name = cleanVal($facNames[$i] ?? '');
        if ($name === '') continue;

        $rawMins = isset($facMins[$i]) ? trim((string)$facMins[$i]) : '';
        $mins = ($rawMins !== '' && is_numeric($rawMins)) ? max(0, (int)$rawMins) : 0;
        $isStart = ($i === 0) || !empty($facIsStart[$i]);

        $stops[] = [
            'name'         => $name,
            'minutes'      => $mins,
            'arrival_time' => cleanVal($facArrivals[$i] ?? ''),
            'sme'          => cleanVal($facSmes[$i] ?? ''),
            'notes'        => cleanVal($facNotes[$i] ?? ''),
            'is_start'     => $isStart
        ];
    }
}

// Clean map route data
$routeImg = trim((string)($data['route_image_data'] ?? ''));
$mapState = $data['map_state'] ?? '';
if (is_array($mapState)) {
    $mapState = json_encode($mapState);
}

$tourRecord = [
    'id'                  => $tourId,
    'status'              => 'draft',
    'created_at'          => $createdAt,
    'updated_at'          => date('c'),
    'tour_title'          => cleanVal($tourTitle),
    'tour_date'           => cleanVal($data['tour_date'] ?? ''),
    'time_start'          => cleanVal($data['time_start'] ?? ''),
    'time_end'            => cleanVal($data['time_end'] ?? ''),
    'coordinator_name'    => cleanVal($data['coordinator_name'] ?? ''),
    'coordinator_phone'   => cleanVal($data['coordinator_phone'] ?? ''),
    'coordinator_email'   => cleanVal($data['coordinator_email'] ?? ''),
    'general_notes'       => cleanVal($data['general_notes'] ?? ''),
    'is_limited_area'     => !empty($data['is_limited_area']),
    'show_arrows'         => !empty($data['show_arrows']),
    'leaders'             => $leaders,
    'participants'        => $participants,
    'stops'               => $stops,
    'route_image_data'    => $routeImg,
    'map_state'           => (string)$mapState,
];

$targetFile = $dataDir . '/' . $tourId . '.json';
$success = file_put_contents($targetFile, json_encode($tourRecord, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

if ($success !== false) {
    echo json_encode([
        'success'  => true,
        'tour_id'  => $tourId,
        'saved_at' => date('g:i:s A')
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error'   => 'Server write failed. Check write permissions on the data directory.'
    ]);
}
