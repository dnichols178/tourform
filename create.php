<?php
declare(strict_types=1);

$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0755, true);
}

function clean(mixed $value): string {
    return htmlspecialchars(trim((string)($value ?? '')), ENT_QUOTES, 'UTF-8');
}

$tourId = '';
$isEdit = false;
$tourTitle = '';
$tourDate = '';
$timeStart = '';
$timeEnd = '';
$coordinatorName = '';
$coordinatorPhone = '';
$coordinatorEmail = '';
$generalNotes = '';
$showArrows = true;
$leaders = [];
$participants = [];
$stops = [];
$routeImageData = '';
$mapState = '';

// Check if loading an existing tour for editing via GET ?id=
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['id'])) {
    $requestedId = basename((string)$_GET['id']);
    $filePath = $dataDir . '/' . $requestedId . '.json';
    if (file_exists($filePath)) {
        $raw = file_get_contents($filePath);
        if ($raw !== false) {
            $record = json_decode($raw, true);
            if (is_array($record)) {
                $tourId           = $requestedId;
                $isEdit           = true;
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
                $mapState         = is_string($record['map_state'] ?? null) ? $record['map_state'] : json_encode($record['map_state'] ?? []);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEdit ? 'Edit Tour Agenda: ' . clean($tourTitle) : 'Facility Tour Agenda & Report Builder' ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .canvas-viewport-controls .zoom-btn {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 32px !important;
            height: 32px !important;
            padding: 0 !important;
            cursor: pointer !important;
            box-sizing: border-box !important;
        }
        .canvas-viewport-controls .zoom-btn svg {
            display: block !important;
            pointer-events: none !important;
        }
        .canvas-viewport-controls .zoom-btn.active {
            background-color: #0066cc !important;
            border-color: #0052a3 !important;
            color: #ffffff !important;
        }
        .canvas-viewport-controls .zoom-btn.active svg,
        .canvas-viewport-controls .zoom-btn.active svg * {
            stroke: #ffffff !important;
            color: #ffffff !important;
        }
        .canvas-viewport-controls .zoom-btn:not(.active) {
            background-color: #ffffff !important;
            color: #333333 !important;
        }
        .canvas-viewport-controls .zoom-btn:not(.active) svg,
        .canvas-viewport-controls .zoom-btn:not(.active) svg * {
            stroke: #333333 !important;
            color: #333333 !important;
        }
        .pan-nav-icon {
            width: 17px !important;
            height: 17px !important;
            margin: auto !important;
        }
        .badge-edit-mode {
            display: inline-block;
            background: #2563eb;
            color: #ffffff;
            font-size: 12px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 4px;
            margin-left: 10px;
            vertical-align: middle;
        }

        /* Start Stop Distinct Styling */
        .stop-card.is-start-stop {
            border-left: 4px solid #16a34a !important;
            background: #fcfdfc;
        }
        .badge-start-stop {
            background: #16a34a;
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Highlight state for inputs flagged with missing duration or arrival */
        .input-error-highlight {
            border-color: #dc2626 !important;
            background-color: #fef2f2 !important;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.2) !important;
            transition: all 0.2s ease-in-out !important;
        }

        /* Missing Stop Requirements Feedback Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 999999;
            box-sizing: border-box;
            padding: 16px;
        }
        .modal-card {
            background: #ffffff;
            border-radius: 12px;
            width: 100%;
            max-width: 540px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.25), 0 8px 10px -6px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            border: 1px solid #e2e8f0;
            animation: modalScaleIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            flex-direction: column;
        }
        @keyframes modalScaleIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        .modal-header-banner {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 20px 24px 16px;
            border-bottom: 1px solid #f1f5f9;
        }
        .modal-icon-badge {
            width: 44px;
            height: 44px;
            min-width: 44px;
            border-radius: 50%;
            background: #fef3c7;
            color: #d97706;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-header-text h3 {
            margin: 0 0 4px;
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
        }
        .modal-header-text p {
            margin: 0;
            font-size: 13px;
            color: #64748b;
            line-height: 1.4;
        }
        .modal-body-content {
            padding: 18px 24px;
        }
        .modal-stop-list {
            list-style: none;
            margin: 10px 0 0;
            padding: 0;
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
        }
        .modal-stop-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13px;
        }
        .modal-stop-item:last-child {
            border-bottom: none;
        }
        .modal-stop-badge {
            display: inline-block;
            background: #e2e8f0;
            color: #334155;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            margin-right: 8px;
        }
        .modal-stop-needed {
            color: #dc2626;
            font-weight: 600;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .modal-footer-actions {
            padding: 14px 24px 20px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            background: #ffffff;
            border-top: 1px solid #f1f5f9;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <header class="app-header flex-between">
            <div class="header-content">
                <h1>
                    <?= $isEdit ? 'Edit Tour Agenda & Report' : 'Facility Tour Agenda & Report Builder' ?>
                    <?php if ($isEdit): ?>
                        <span class="badge-edit-mode">Editing ID: <?= clean($tourId) ?></span>
                    <?php endif; ?>
                </h1>
                <p class="subtitle"><?= $isEdit ? 'Modify itinerary details, roster, and route map, then save updates' : 'Generate print-ready tour itineraries, leader briefings, and route maps' ?></p>
            </div>
            <div class="header-nav">
                <a href="index.php" class="btn btn-secondary">View Saved Tours &rarr;</a>
            </div>
        </header>

        <form id="tourForm" action="generate.php" method="POST">
            <?php if ($isEdit): ?>
                <input type="hidden" name="tour_id" value="<?= clean($tourId) ?>">
            <?php endif; ?>

            <!-- Section 1: General Information, Logistics & Tour Leaders -->
            <section class="card">
                <div class="card-header">
                    <h2>1. General Information & Logistics</h2>
                </div>
                <div class="card-body">
                    <div class="grid-2">
                        <div class="form-group span-2">
                            <label for="tour_title">Tour Title / Purpose <span class="required">*</span></label>
                            <input type="text" id="tour_title" name="tour_title" value="<?= clean($tourTitle) ?>" placeholder="e.g., Annual Regulatory Facility Overview" required>
                        </div>
                        <div class="grid-3 span-2">
                            <div class="form-group">
                                <label for="tour_date">Tour Date <span class="required">*</span></label>
                                <input type="date" id="tour_date" name="tour_date" value="<?= clean($tourDate) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="time_start">Start Time <span class="required">*</span></label>
                                <input type="time" id="time_start" name="time_start" value="<?= clean($timeStart) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="time_end">Stop / End Time <span class="required">*</span></label>
                                <input type="time" id="time_end" name="time_end" value="<?= clean($timeEnd) ?>" required>
                            </div>
                        </div>
                        <div class="form-group span-2">
                            <label for="coordinator_name">Lead Coordinator <span class="required">*</span></label>
                            <input type="text" id="coordinator_name" name="coordinator_name" value="<?= clean($coordinatorName) ?>" placeholder="Coordinator Full Name" required>
                        </div>
                        <div class="form-group">
                            <label for="coordinator_phone">Coordinator Phone <span class="required">*</span></label>
                            <input type="tel" id="coordinator_phone" name="coordinator_phone" value="<?= clean($coordinatorPhone) ?>" placeholder="(000) 000-0000" maxlength="14" required>
                        </div>
                        <div class="form-group">
                            <label for="coordinator_email">Coordinator Email <span class="required">*</span></label>
                            <input type="email" id="coordinator_email" name="coordinator_email" value="<?= clean($coordinatorEmail) ?>" placeholder="name@domain.gov" required>
                        </div>
                    </div>

                    <!-- Tour Leaders -->
                    <div class="sub-section-divider">
                        <div class="section-subhead">
                            <div>
                                <h3>Tour Leaders / Escorts</h3>
                                <p class="section-desc">Specify official guides, security escorts, or tour leaders.</p>
                            </div>
                            <button type="button" class="btn btn-secondary btn-sm" id="addLeaderBtn">+ Add Leader</button>
                        </div>
                        <div id="leadersList" class="dynamic-list">
                            <?php if (!empty($leaders)): ?>
                                <?php foreach ($leaders as $l): ?>
                                    <div class="dynamic-row-personnel">
                                        <div class="person-name-field">
                                            <input type="text" name="leader_names[]" value="<?= clean(is_array($l) ? ($l['name'] ?? '') : (string)$l) ?>" placeholder="Leader Full Name & Title" required>
                                        </div>
                                        <div class="person-org-field">
                                            <input type="text" name="leader_orgs[]" value="<?= clean(is_array($l) ? ($l['org'] ?? '') : '') ?>" placeholder="Organization (e.g., DOE / FRNP)" required>
                                        </div>
                                        <button type="button" class="btn btn-icon btn-remove" title="Remove Leader">&times;</button>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="dynamic-row-personnel">
                                    <div class="person-name-field">
                                        <input type="text" name="leader_names[]" placeholder="Leader Full Name & Title" required>
                                    </div>
                                    <div class="person-org-field">
                                        <input type="text" name="leader_orgs[]" placeholder="Organization (e.g., DOE / FRNP)" required>
                                    </div>
                                    <button type="button" class="btn btn-icon btn-remove" title="Remove Leader">&times;</button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Section 2: Personnel & Attendance (Participants/Visitors) -->
            <section class="card">
                <div class="card-header flex-between">
                    <div>
                        <h2>2. Personnel & Attendance</h2>
                        <p class="section-desc">Record attending visitors, regulatory officials, and guests.</p>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" id="addParticipantBtn">+ Add Participant</button>
                </div>
                <div class="card-body">
                    <div class="same-org-panel">
                        <label class="checkbox-inline">
                            <input type="checkbox" id="sameOrgToggle">
                            <span>All participants are from the same organization</span>
                        </label>
                        <div id="commonOrgContainer" style="display: none; margin-top: 8px;">
                            <input type="text" id="commonOrgInput" placeholder="Enter common organization for all participants...">
                        </div>
                    </div>
                    <div id="participantsList" class="dynamic-list">
                        <?php if (!empty($participants)): ?>
                            <?php foreach ($participants as $p): ?>
                                <div class="dynamic-row-personnel">
                                    <div class="person-name-field">
                                        <input type="text" name="participant_names[]" value="<?= clean(is_array($p) ? ($p['name'] ?? '') : (string)$p) ?>" placeholder="Participant Full Name" required>
                                    </div>
                                    <div class="person-org-field">
                                        <input type="text" name="participant_orgs[]" class="participant-org-input" value="<?= clean(is_array($p) ? ($p['org'] ?? '') : '') ?>" placeholder="Organization / Agency" required>
                                    </div>
                                    <button type="button" class="btn btn-icon btn-remove" title="Remove Participant">&times;</button>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="dynamic-row-personnel">
                                <div class="person-name-field">
                                    <input type="text" name="participant_names[]" placeholder="Participant Full Name" required>
                                </div>
                                <div class="person-org-field">
                                    <input type="text" name="participant_orgs[]" class="participant-org-input" placeholder="Organization / Agency" required>
                                </div>
                                <button type="button" class="btn btn-icon btn-remove" title="Remove Participant">&times;</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- Section 3: Tour Route Mapping -->
            <section class="card">
                <div class="card-header">
                    <h2>3. Tour Route Mapping (GIS Layers & Navigation Tools)</h2>
                    <p class="section-desc">Select a tool: Pan to explore, Green Stop Tool to place stops, or Route Tool to draw paths.</p>
                </div>
                <div class="card-body">
                    <div class="canvas-controls">
                        <div class="control-item">
                            <label for="bgImageUpload" class="btn btn-secondary btn-sm">Load Base Map Image</label>
                            <input type="file" id="bgImageUpload" accept="image/*" style="display: none;">
                        </div>
                        <div class="control-item">
                            <label for="strokeColor">Route Color:</label>
                            <input type="color" id="strokeColor" value="#d9381e">
                        </div>
                        <div class="control-item">
                            <label for="lineWidth">Width:</label>
                            <select id="lineWidth">
                                <option value="2">Thin (2px)</option>
                                <option value="4" selected>Medium (4px)</option>
                                <option value="6">Thick (6px)</option>
                            </select>
                        </div>
                        <div class="control-item checkbox-item">
                            <label><input type="checkbox" id="showRoads" checked> Roads</label>
                        </div>
                        <div class="control-item checkbox-item">
                            <label><input type="checkbox" id="showFootprints" checked> Footprints</label>
                        </div>
                        <div class="control-item checkbox-item">
                            <label><input type="checkbox" id="showLabels"> Labels</label>
                        </div>
                        <div class="control-item checkbox-item">
                            <label><input type="checkbox" id="showArrows" name="show_arrows" value="1" <?= $showArrows ? 'checked' : '' ?>> Arrows</label>
                        </div>
                        <div class="control-actions">
                            <button type="button" class="btn btn-secondary btn-sm" id="undoPointBtn" title="Undo last action (Ctrl+Z)">&larr; Undo</button>
                            <button type="button" class="btn btn-secondary btn-sm" id="newLegBtn" title="Start a separate path segment">+ New Leg</button>
                            <button type="button" class="btn btn-secondary btn-sm" id="clearCanvasBtn">Clear Route</button>
                            <button type="button" class="btn btn-secondary btn-sm" id="clearStopsBtn">Clear Stops</button>
                            <button type="button" class="btn btn-secondary btn-sm" id="resetBgBtn">Reset Base Map</button>
                        </div>
                    </div>
                    <div class="canvas-wrapper" id="canvasWrapper">
                        <div class="canvas-viewport-controls">
                            <button type="button" class="zoom-btn active" id="panToolBtn" title="Pan / Move Map (Default)">
                                <svg class="pan-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="5 9 2 12 5 15"></polyline>
                                    <polyline points="9 5 12 2 15 5"></polyline>
                                    <polyline points="15 19 12 22 9 19"></polyline>
                                    <polyline points="19 9 22 12 19 15"></polyline>
                                    <line x1="2" y1="12" x2="22" y2="12"></line>
                                    <line x1="12" y1="2" x2="12" y2="22"></line>
                                </svg>
                            </button>
                            <button type="button" class="zoom-btn" id="stopToolBtn" title="Place Tour Stop (Green Circle)">
                                <span class="stop-tool-icon"></span>
                            </button>
                            <button type="button" class="zoom-btn" id="routeToolBtn" title="Draw Route Waypoints">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="4 19 9 9 15 15 20 5"/>
                                </svg>
                            </button>
                            <div class="viewport-divider"></div>
                            <button type="button" class="zoom-btn" id="zoomInBtn" title="Zoom In (+)">+</button>
                            <button type="button" class="zoom-btn" id="zoomOutBtn" title="Zoom Out (–)">&minus;</button>
                            <button type="button" class="zoom-btn" id="zoomResetBtn" title="Reset View (Extents)">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                                    <path d="M3 3v5h5"/>
                                </svg>
                            </button>
                            <span class="zoom-level-badge" id="zoomLevelBadge">100%</span>
                        </div>
                        <canvas id="routeCanvas" width="900" height="580"></canvas>
                        <div id="mapTooltip" class="map-tooltip" style="display: none;"></div>
                        <div id="gisStatusMessage" class="gis-status-banner" style="display: none;"></div>
                    </div>
                    <div class="canvas-hint flex-between">
                        <span id="canvasModeHelp"><strong>Pan Tool:</strong> Drag to move map. Scroll wheel to zoom in/out.</span>
                        <span id="canvasModeLabel" class="mode-label">Mode: Pan Map</span>
                    </div>
                    <input type="hidden" id="route_image_data" name="route_image_data" value="<?= clean($routeImageData) ?>">
                    <input type="hidden" id="map_state" name="map_state" value="<?= clean($mapState) ?>">
                </div>
            </section>

            <!-- Section 4: Facility Stops & Itinerary Times -->
            <section class="card">
                <div class="card-header flex-between">
                    <div>
                        <h2>4. Facility Stops & Itinerary Times</h2>
                        <p class="section-desc">Reorder stops using buttons. Each card can collapse or expand for easier management.</p>
                    </div>
                    <div class="toolbar-group">
                        <button type="button" class="btn btn-secondary btn-sm" id="collapseAllBtn">Collapse All</button>
                        <button type="button" class="btn btn-secondary btn-sm" id="expandAllBtn">Expand All</button>
                        <button type="button" class="btn btn-primary btn-sm" id="addStopBtn">+ Add Stop</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="time-budget-panel" id="timeBudgetPanel">
                        <div class="budget-stat">
                            <span class="budget-label">Total Tour Window:</span>
                            <span class="budget-val" id="statTotalTour">-- mins</span>
                        </div>
                        <div class="budget-stat">
                            <span class="budget-label">Allocated to Stops:</span>
                            <span class="budget-val" id="statAllocated">0 mins</span>
                        </div>
                        <div class="budget-stat">
                            <span class="budget-label">Remaining Buffer:</span>
                            <span class="budget-val" id="statRemaining">-- mins</span>
                        </div>
                    </div>
                    <div id="timeAlertBox" class="alert-box alert-danger" style="display: none;">
                        <strong>Time Exceeded Warning:</strong> The scheduled stops total <span id="alertAllocatedMins">0</span> minutes, which exceeds your total tour window of <span id="alertTotalMins">0</span> minutes by <span id="alertOverMins">0</span> minutes. Please edit the minute values below before finalizing.
                    </div>
                    <div id="stopsList" class="stops-container">
                        <?php if (!empty($stops)): ?>
                            <?php foreach ($stops as $idx => $s): 
                                $isStart = ($idx === 0) || !empty($s['is_start']);
                                $sNum    = $idx + 1;
                                $sName   = clean($s['name'] ?? '');
                                $sMins   = isset($s['minutes']) && $s['minutes'] !== '' ? (int)$s['minutes'] : '';
                                $sSme    = clean($s['sme'] ?? '');
                                $sNotes  = clean($s['notes'] ?? '');
                                $sArrival = clean($s['arrival_time'] ?? ($s['arrival'] ?? ''));
                            ?>
                                <div class="stop-card dynamic-stop <?= $isStart ? 'is-start-stop' : '' ?>" data-is-start="<?= $isStart ? '1' : '0' ?>" data-expanded="false">
                                    <input type="hidden" name="facility_is_start[]" value="<?= $isStart ? '1' : '0' ?>">
                                    <div class="stop-header">
                                        <div class="stop-header-left">
                                            <button type="button" class="btn-toggle-collapse" title="Collapse / Expand">&#9662;</button>
                                            <?php if ($isStart): ?>
                                                <span class="badge-start-stop">Start</span>
                                                <span class="stop-number">Start Location</span>
                                            <?php else: ?>
                                                <span class="stop-number">Stop <?= $sNum ?></span>
                                            <?php endif; ?>
                                            <span class="stop-header-preview"><?= $sName ?: ($isStart ? 'Start Location (Required)' : 'New Stop') ?></span>
                                            <span class="stop-header-time-pill"><?= ($sMins !== '' && $sMins >= 0) ? $sMins . ' min' : '-- min' ?></span>
                                        </div>
                                        <div class="stop-header-actions">
                                            <button type="button" class="btn-reorder btn-move-up" title="Move Up">&#9650;</button>
                                            <button type="button" class="btn-reorder btn-move-down" title="Move Down">&#9660;</button>
                                            <?php if (!$isStart): ?>
                                                <button type="button" class="btn btn-icon btn-remove-stop" title="Remove Stop">&times;</button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="stop-body">
                                        <div class="stop-grid">
                                            <div class="form-group span-2">
                                                <label><?= $isStart ? 'Start Facility / Muster Location Name' : 'Facility / Location Name' ?> <span class="required">*</span></label>
                                                <input type="text" name="facility_names[]" class="facility-name-input" value="<?= $sName ?>" placeholder="<?= $isStart ? 'e.g., C-100 Administration Building / Muster Point' : 'e.g., C-300 Central Control Facility' ?>" required>
                                            </div>
                                            <div class="form-group span-2">
                                                <label>Scheduled Duration (Minutes) <span class="required">*</span></label>
                                                <input type="number" name="facility_minutes[]" class="facility-minutes-input" value="<?= ($sMins !== '' && $sMins >= 0) ? $sMins : '' ?>" min="0" max="1440" placeholder="e.g., 0 for Muster/Briefing or 30" required>
                                            </div>
                                            <?php if (!$isStart): ?>
                                                <div class="form-group span-2 arrival-time-group">
                                                    <label>Arrival Time <span class="arrival-required-star required" style="<?= ($sMins !== '' && (int)$sMins > 0) ? 'display:inline;' : 'display:none;' ?>">*</span></label>
                                                    <input type="time" name="facility_arrival_times[]" class="facility-arrival-input" value="<?= $sArrival ?>" <?= ($sMins !== '' && (int)$sMins > 0) ? 'required' : '' ?>>
                                                    <small class="field-hint" style="color: #64748b; font-size: 11px; display: block; margin-top: 4px;">Required if duration is greater than 0 minutes. If 0 minutes, arrival time is optional.</small>
                                                </div>
                                            <?php else: ?>
                                                <input type="hidden" name="facility_arrival_times[]" class="facility-arrival-input" value="">
                                            <?php endif; ?>
                                            <div class="form-group span-2">
                                                <label>Subject Matter Expert (SME) at Stop (Optional)</label>
                                                <input type="text" name="facility_smes[]" value="<?= $sSme ?>" placeholder="e.g., Jane Smith, Senior Operations Engineer">
                                            </div>
                                            <div class="form-group span-2">
                                                <label>Stop Agenda / Briefing Focus / Safety Requirements</label>
                                                <textarea name="facility_notes[]" rows="2" placeholder="Key topics covered, PPE required, or specific route instructions..."><?= $sNotes ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <!-- Default Initial Start Stop (No inputs pre-filled, but required) -->
                            <div class="stop-card dynamic-stop is-start-stop" data-is-start="1" data-expanded="true">
                                <input type="hidden" name="facility_is_start[]" value="1">
                                <div class="stop-header">
                                    <div class="stop-header-left">
                                        <button type="button" class="btn-toggle-collapse" title="Collapse / Expand">&#9652;</button>
                                        <span class="badge-start-stop">Start</span>
                                        <span class="stop-number">Start Location</span>
                                        <span class="stop-header-preview">Start Location (Required)</span>
                                        <span class="stop-header-time-pill">-- min</span>
                                    </div>
                                    <div class="stop-header-actions">
                                        <button type="button" class="btn-reorder btn-move-up" title="Move Up" style="visibility: hidden;">&#9650;</button>
                                        <button type="button" class="btn-reorder btn-move-down" title="Move Down">&#9660;</button>
                                    </div>
                                </div>
                                <div class="stop-body" style="display: block;">
                                    <div class="stop-grid">
                                        <div class="form-group span-2">
                                            <label>Start Facility / Muster Location Name <span class="required">*</span></label>
                                            <input type="text" name="facility_names[]" class="facility-name-input" placeholder="e.g., C-100 Administration Building / Muster Point" required>
                                        </div>
                                        <div class="form-group span-2">
                                            <label>Scheduled Duration (Minutes) <span class="required">*</span></label>
                                            <input type="number" name="facility_minutes[]" class="facility-minutes-input" min="0" max="1440" placeholder="e.g., 0 for Muster or 15 for Briefing" required>
                                        </div>
                                        <input type="hidden" name="facility_arrival_times[]" class="facility-arrival-input" value="">
                                        <div class="form-group span-2">
                                            <label>Subject Matter Expert (SME) at Stop (Optional)</label>
                                            <input type="text" name="facility_smes[]" placeholder="e.g., Jane Smith, Tour Coordinator">
                                        </div>
                                        <div class="form-group span-2">
                                            <label>Stop Agenda / Briefing Focus / Safety Requirements</label>
                                            <textarea name="facility_notes[]" rows="2" placeholder="Badging, general site safety orientation, muster instructions..."></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- Section 5: Additional Instructions & Safety Protocols -->
            <section class="card">
                <div class="card-header">
                    <h2>5. Additional Instructions & Safety Protocols</h2>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <textarea name="general_notes" rows="4" placeholder="Enter badging requirements, security instructions, assembly points, transportation arrangements, or weather contingency plans..."><?= clean($generalNotes) ?></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-lg" id="submitTourBtn">
                            <?= $isEdit ? 'Save Changes & Update Tour' : 'Generate Tour Agenda & Report' ?>
                        </button>
                    </div>
                </div>
            </section>
        </form>
    </div>

    <!-- Missing Stop Requirements Validation Modal -->
    <div id="missingDurationModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal-card">
            <div class="modal-header-banner">
                <div class="modal-icon-badge">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                </div>
                <div class="modal-header-text">
                    <h3 id="modalTitle">Itinerary Details Required</h3>
                    <p>All general tour logistics are complete, but one or more itinerary stops need mandatory location, duration (0 or more mins), or arrival times.</p>
                </div>
            </div>
            <div class="modal-body-content">
                <span style="font-size: 13px; font-weight: 600; color: #334155;">Please complete the highlighted items for the following stops:</span>
                <ul class="modal-stop-list" id="modalMissingStopsList"></ul>
            </div>
            <div class="modal-footer-actions">
                <button type="button" class="btn btn-secondary btn-sm" id="modalDismissBtn">Dismiss</button>
                <button type="button" class="btn btn-primary btn-sm" id="modalFixDurationsBtn">Fix Stop Details &rarr;</button>
            </div>
        </div>
    </div>

    <script src="assets/js/app.js"></script>

    <!-- Client Script: Clear Stops, Time Budget, Exact Zoom Extent Sync, Dynamic Arrival Validation, and Modal Interceptor -->
    <script>
        (function() {
            const form = document.getElementById('tourForm');
            const routeInput = document.getElementById('route_image_data');
            const mapStateInput = document.getElementById('map_state');
            const canvas = document.getElementById('routeCanvas');
            const btn = document.getElementById('submitTourBtn');
            const clearStopsBtn = document.getElementById('clearStopsBtn');
            const addStopBtn = document.getElementById('addStopBtn');

            // Modal elements
            const modal = document.getElementById('missingDurationModal');
            const modalList = document.getElementById('modalMissingStopsList');
            const modalDismissBtn = document.getElementById('modalDismissBtn');
            const modalFixBtn = document.getElementById('modalFixDurationsBtn');

            let currentMissingStops = [];

            function escapeHtml(text) {
                if (!text) return '';
                const map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return String(text).replace(/[&<>"']/g, m => map[m]);
            }

            // --- Dynamic Arrival Time & Duration Coupling ---
            function syncStopFieldValidation(card) {
                if (!card) return;
                const isStart = card.getAttribute('data-is-start') === '1';
                const minsInput = card.querySelector('.facility-minutes-input');
                const arrivalInput = card.querySelector('.facility-arrival-input');
                const star = card.querySelector('.arrival-required-star');

                if (isStart || !arrivalInput) return;

                const val = minsInput ? parseInt(minsInput.value.trim(), 10) : 0;
                if (!isNaN(val) && val > 0) {
                    arrivalInput.required = true;
                    if (star) star.style.display = 'inline';
                } else {
                    arrivalInput.required = false;
                    arrivalInput.classList.remove('input-error-highlight');
                    if (star) star.style.display = 'none';
                }
            }

            function reindexStops() {
                const stopCards = document.querySelectorAll('#stopsList .stop-card');
                stopCards.forEach((card, idx) => {
                    const isStart = (idx === 0);
                    card.setAttribute('data-is-start', isStart ? '1' : '0');
                    const hiddenIsStart = card.querySelector('input[name="facility_is_start[]"]');
                    if (hiddenIsStart) hiddenIsStart.value = isStart ? '1' : '0';

                    const headerLeft = card.querySelector('.stop-header-left');
                    const numLabel = card.querySelector('.stop-number');
                    const preview = card.querySelector('.stop-header-preview');
                    const nameInput = card.querySelector('.facility-name-input');
                    const valName = nameInput ? nameInput.value.trim() : '';

                    let startBadge = card.querySelector('.badge-start-stop');
                    if (isStart) {
                        card.classList.add('is-start-stop');
                        if (!startBadge && headerLeft) {
                            startBadge = document.createElement('span');
                            startBadge.className = 'badge-start-stop';
                            startBadge.textContent = 'Start';
                            const toggleBtn = card.querySelector('.btn-toggle-collapse');
                            if (toggleBtn && toggleBtn.nextSibling) {
                                headerLeft.insertBefore(startBadge, toggleBtn.nextSibling);
                            }
                        }
                        if (numLabel) numLabel.textContent = 'Start Location';
                        if (preview) preview.textContent = valName || 'Start Location (Required)';
                        const removeBtn = card.querySelector('.btn-remove-stop');
                        if (removeBtn) removeBtn.style.display = 'none';
                    } else {
                        card.classList.remove('is-start-stop');
                        if (startBadge) startBadge.remove();
                        if (numLabel) numLabel.textContent = `Stop ${idx + 1}`;
                        if (preview) preview.textContent = valName || 'New Stop';
                        const removeBtn = card.querySelector('.btn-remove-stop');
                        if (removeBtn) removeBtn.style.display = 'inline-flex';
                    }
                    syncStopFieldValidation(card);
                });
            }

            // --- 1. Real-Time Time Budget Calculator ---
            function updateTimeBudget() {
                const timeStart = document.getElementById('time_start')?.value;
                const timeEnd = document.getElementById('time_end')?.value;
                const statTotalTour = document.getElementById('statTotalTour');
                const statAllocated = document.getElementById('statAllocated');
                const statRemaining = document.getElementById('statRemaining');
                const alertBox = document.getElementById('timeAlertBox');

                let totalMins = 0;
                if (timeStart && timeEnd) {
                    const [sh, sm] = timeStart.split(':').map(Number);
                    const [eh, em] = timeEnd.split(':').map(Number);
                    totalMins = (eh * 60 + em) - (sh * 60 + sm);
                    if (totalMins < 0) totalMins += 24 * 60;
                }

                let allocatedMins = 0;
                document.querySelectorAll('.facility-minutes-input').forEach(input => {
                    const val = parseInt(input.value, 10);
                    if (!isNaN(val) && val > 0) allocatedMins += val;
                });

                if (statTotalTour) statTotalTour.textContent = totalMins > 0 ? totalMins + ' mins' : '-- mins';
                if (statAllocated) statAllocated.textContent = allocatedMins + ' mins';
                if (statRemaining) {
                    if (totalMins > 0) {
                        const rem = totalMins - allocatedMins;
                        statRemaining.textContent = rem + ' mins';
                        statRemaining.style.color = rem < 0 ? '#dc2626' : '';
                    } else {
                        statRemaining.textContent = '-- mins';
                    }
                }
                if (alertBox) {
                    if (totalMins > 0 && allocatedMins > totalMins) {
                        alertBox.style.display = 'block';
                        const aAlloc = document.getElementById('alertAllocatedMins');
                        const aTotal = document.getElementById('alertTotalMins');
                        const aOver  = document.getElementById('alertOverMins');
                        if (aAlloc) aAlloc.textContent = allocatedMins;
                        if (aTotal) aTotal.textContent = totalMins;
                        if (aOver)  aOver.textContent  = allocatedMins - totalMins;
                    } else {
                        alertBox.style.display = 'none';
                    }
                }
            }

            // Generate default "Start" stop markup
            function getDefaultStartStopHtml() {
                return `
                    <div class="stop-card dynamic-stop is-start-stop" data-is-start="1" data-expanded="true">
                        <input type="hidden" name="facility_is_start[]" value="1">
                        <div class="stop-header">
                            <div class="stop-header-left">
                                <button type="button" class="btn-toggle-collapse" title="Collapse / Expand">&#9652;</button>
                                <span class="badge-start-stop">Start</span>
                                <span class="stop-number">Start Location</span>
                                <span class="stop-header-preview">Start Location (Required)</span>
                                <span class="stop-header-time-pill">-- min</span>
                            </div>
                            <div class="stop-header-actions">
                                <button type="button" class="btn-reorder btn-move-up" title="Move Up" style="visibility: hidden;">&#9650;</button>
                                <button type="button" class="btn-reorder btn-move-down" title="Move Down">&#9660;</button>
                            </div>
                        </div>
                        <div class="stop-body" style="display: block;">
                            <div class="stop-grid">
                                <div class="form-group span-2">
                                    <label>Start Facility / Muster Location Name <span class="required">*</span></label>
                                    <input type="text" name="facility_names[]" class="facility-name-input" placeholder="e.g., C-100 Administration Building / Muster Point" required>
                                </div>
                                <div class="form-group span-2">
                                    <label>Scheduled Duration (Minutes) <span class="required">*</span></label>
                                    <input type="number" name="facility_minutes[]" class="facility-minutes-input" min="0" max="1440" placeholder="e.g., 0 for Muster or 15 for Briefing" required>
                                </div>
                                <input type="hidden" name="facility_arrival_times[]" class="facility-arrival-input" value="">
                                <div class="form-group span-2">
                                    <label>Subject Matter Expert (SME) at Stop (Optional)</label>
                                    <input type="text" name="facility_smes[]" placeholder="e.g., Jane Smith, Tour Coordinator">
                                </div>
                                <div class="form-group span-2">
                                    <label>Stop Agenda / Briefing Focus / Safety Requirements</label>
                                    <textarea name="facility_notes[]" rows="2" placeholder="Badging, general site safety orientation, muster instructions..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }

            // Generate subsequent stop markup
            function getAdditionalStopHtml(stopIndex) {
                return `
                    <div class="stop-card dynamic-stop" data-is-start="0" data-expanded="true">
                        <input type="hidden" name="facility_is_start[]" value="0">
                        <div class="stop-header">
                            <div class="stop-header-left">
                                <button type="button" class="btn-toggle-collapse" title="Collapse / Expand">&#9652;</button>
                                <span class="stop-number">Stop ${stopIndex}</span>
                                <span class="stop-header-preview">New Stop</span>
                                <span class="stop-header-time-pill">-- min</span>
                            </div>
                            <div class="stop-header-actions">
                                <button type="button" class="btn-reorder btn-move-up" title="Move Up">&#9650;</button>
                                <button type="button" class="btn-reorder btn-move-down" title="Move Down">&#9660;</button>
                                <button type="button" class="btn btn-icon btn-remove-stop" title="Remove Stop">&times;</button>
                            </div>
                        </div>
                        <div class="stop-body" style="display: block;">
                            <div class="stop-grid">
                                <div class="form-group span-2">
                                    <label>Facility / Location Name <span class="required">*</span></label>
                                    <input type="text" name="facility_names[]" class="facility-name-input" placeholder="e.g., C-300 Central Control Facility" required>
                                </div>
                                <div class="form-group span-2">
                                    <label>Scheduled Duration (Minutes) <span class="required">*</span></label>
                                    <input type="number" name="facility_minutes[]" class="facility-minutes-input" min="0" max="1440" placeholder="e.g., 30 (or 0 for drive-by)" required>
                                </div>
                                <div class="form-group span-2 arrival-time-group">
                                    <label>Arrival Time <span class="arrival-required-star required" style="display: none;">*</span></label>
                                    <input type="time" name="facility_arrival_times[]" class="facility-arrival-input">
                                    <small class="field-hint" style="color: #64748b; font-size: 11px; display: block; margin-top: 4px;">Required if duration is greater than 0 minutes. If 0 minutes, arrival time is optional.</small>
                                </div>
                                <div class="form-group span-2">
                                    <label>Subject Matter Expert (SME) at Stop (Optional)</label>
                                    <input type="text" name="facility_smes[]" placeholder="e.g., Jane Smith, Operations Engineer">
                                </div>
                                <div class="form-group span-2">
                                    <label>Stop Agenda / Briefing Focus / Safety Requirements</label>
                                    <textarea name="facility_notes[]" rows="2" placeholder="Key topics covered, PPE required, or specific route instructions..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }

            // --- 2. Clear Stops: Resets to default blank Start stop ---
            if (clearStopsBtn) {
                clearStopsBtn.addEventListener('click', function() {
                    const stopsList = document.getElementById('stopsList');
                    if (stopsList) {
                        stopsList.innerHTML = getDefaultStartStopHtml();
                        reindexStops();
                        updateTimeBudget();
                    }
                });
            }

            // --- 3. Add Stop Support ---
            if (addStopBtn) {
                addStopBtn.addEventListener('click', function(e) {
                    const stopsList = document.getElementById('stopsList');
                    if (!stopsList) return;
                    const count = stopsList.querySelectorAll('.stop-card').length;
                    const temp = document.createElement('div');
                    temp.innerHTML = getAdditionalStopHtml(count + 1).trim();
                    const newCard = temp.firstElementChild;
                    stopsList.appendChild(newCard);
                    reindexStops();
                    updateTimeBudget();

                    newCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    const input = newCard.querySelector('.facility-name-input');
                    if (input) setTimeout(() => input.focus(), 150);
                });
            }

            document.addEventListener('input', function(e) {
                if (e.target.matches('#time_start, #time_end, .facility-minutes-input')) {
                    updateTimeBudget();
                }
                if (e.target.matches('.facility-minutes-input')) {
                    const card = e.target.closest('.stop-card');
                    if (card) {
                        syncStopFieldValidation(card);
                        const pill = card.querySelector('.stop-header-time-pill');
                        const v = parseInt(e.target.value.trim(), 10);
                        if (pill) pill.textContent = (!isNaN(v) && v >= 0) ? `${v} min` : '-- min';
                    }
                }
                if (e.target.matches('.facility-name-input')) {
                    const card = e.target.closest('.stop-card');
                    if (card) {
                        const preview = card.querySelector('.stop-header-preview');
                        const isStart = card.getAttribute('data-is-start') === '1';
                        if (preview) preview.textContent = e.target.value.trim() || (isStart ? 'Start Location (Required)' : 'New Stop');
                    }
                }
            });

            // --- 4. Itinerary Validation & Missing Details Interceptor ---
            function checkOtherRequiredFields() {
                const requiredInputs = form.querySelectorAll('input[required]:not(.facility-minutes-input):not(.facility-arrival-input), select[required], textarea[required]');
                for (let input of requiredInputs) {
                    if (!input.checkValidity() || !input.value.trim()) {
                        return false;
                    }
                }
                return true;
            }

            function getStopsWithMissingDetails() {
                const stopCards = document.querySelectorAll('#stopsList .stop-card');
                const issues = [];

                stopCards.forEach((card, index) => {
                    const isStart = card.getAttribute('data-is-start') === '1';
                    const nameInput = card.querySelector('.facility-name-input');
                    const minsInput = card.querySelector('.facility-minutes-input');
                    const arrivalInput = card.querySelector('.facility-arrival-input');
                    const stopNum = index + 1;
                    const stopLabel = isStart ? 'Start Location' : `Stop ${stopNum}`;

                    const nameVal = nameInput ? nameInput.value.trim() : '';
                    const rawMins = minsInput ? minsInput.value.trim() : '';
                    const parsedMins = parseInt(rawMins, 10);
                    const arrivalVal = arrivalInput ? arrivalInput.value.trim() : '';

                    let errorReason = '';
                    let targetInput = null;

                    if (!nameVal) {
                        errorReason = 'Location name required';
                        targetInput = nameInput;
                    } else if (rawMins === '' || isNaN(parsedMins) || parsedMins < 0) {
                        errorReason = 'Duration needed (0 or more mins)';
                        targetInput = minsInput;
                    } else if (!isStart && parsedMins > 0 && !arrivalVal) {
                        errorReason = 'Arrival time required (duration > 0)';
                        targetInput = arrivalInput;
                    }

                    if (errorReason) {
                        issues.push({
                            card: card,
                            stopNum: stopNum,
                            isStart: isStart,
                            name: nameVal || stopLabel,
                            errorReason: errorReason,
                            targetInput: targetInput
                        });
                    }
                });

                return issues;
            }

            function showModal(issues) {
                currentMissingStops = issues;
                if (!modal || !modalList) return;

                modalList.innerHTML = '';
                issues.forEach(item => {
                    const li = document.createElement('li');
                    li.className = 'modal-stop-item';
                    li.innerHTML = `
                        <div>
                            <span class="modal-stop-badge">${escapeHtml(item.isStart ? 'Start' : 'Stop ' + item.stopNum)}</span>
                            <strong>${escapeHtml(item.name)}</strong>
                        </div>
                        <span class="modal-stop-needed">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                            ${escapeHtml(item.errorReason)}
                        </span>
                    `;
                    modalList.appendChild(li);
                });

                modal.style.display = 'flex';
            }

            function hideModal() {
                if (modal) modal.style.display = 'none';
            }

            if (modalDismissBtn) modalDismissBtn.addEventListener('click', hideModal);

            if (modalFixBtn) {
                modalFixBtn.addEventListener('click', function() {
                    hideModal();
                    if (!currentMissingStops || currentMissingStops.length === 0) return;

                    currentMissingStops.forEach(item => {
                        item.card.setAttribute('data-expanded', 'true');
                        const body = item.card.querySelector('.stop-body');
                        if (body) body.style.display = 'block';

                        const toggleBtn = item.card.querySelector('.btn-toggle-collapse');
                        if (toggleBtn) toggleBtn.innerHTML = '&#9652;';

                        if (item.targetInput) {
                            item.targetInput.classList.add('input-error-highlight');
                            item.targetInput.addEventListener('input', function() {
                                item.targetInput.classList.remove('input-error-highlight');
                            }, { once: true });
                        }
                    });

                    const first = currentMissingStops[0];
                    if (first && first.targetInput) {
                        first.targetInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        setTimeout(() => first.targetInput.focus(), 250);
                    }
                });
            }

            window.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && modal && modal.style.display === 'flex') {
                    hideModal();
                }
            });
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) hideModal();
                });
            }

            // --- 5. Map Route & Extent Synchronization ---
            const initialSavedRoute = (routeInput?.value || '').trim();
            const isEditMode = initialSavedRoute.startsWith('data:image/');
            let userModifiedCanvas = false;
            let isSubmitting = false;

            let savedState = null;
            if (mapStateInput && mapStateInput.value) {
                try {
                    savedState = JSON.parse(mapStateInput.value);
                } catch (e) {}
            }

            function applyRestoredState() {
                if (!savedState) return;

                if (Array.isArray(savedState.routeLegs) && savedState.routeLegs.length > 0) {
                    if (Array.isArray(window.routeLegs)) {
                        window.routeLegs.length = 0;
                        savedState.routeLegs.forEach(l => window.routeLegs.push(l));
                    } else {
                        window.routeLegs = savedState.routeLegs.slice();
                    }
                }

                if (Array.isArray(savedState.tourStops) && savedState.tourStops.length > 0) {
                    if (Array.isArray(window.tourStops)) {
                        window.tourStops.length = 0;
                        savedState.tourStops.forEach(s => window.tourStops.push(s));
                    } else {
                        window.tourStops = savedState.tourStops.slice();
                    }
                }

                if (savedState.scale) {
                    window.scale = savedState.scale;
                    if (window.viewState) window.viewState.scale = savedState.scale;
                }
                if (savedState.offsetX !== undefined && savedState.offsetX !== null) {
                    window.offsetX = savedState.offsetX;
                    if (window.viewState) window.viewState.offsetX = savedState.offsetX;
                }
                if (savedState.offsetY !== undefined && savedState.offsetY !== null) {
                    window.offsetY = savedState.offsetY;
                    if (window.viewState) window.viewState.offsetY = savedState.offsetY;
                }
                if (savedState.viewState) {
                    window.viewState = Object.assign(window.viewState || {}, savedState.viewState);
                }

                if (savedState.zoomPercent) {
                    const badge = document.getElementById('zoomLevelBadge');
                    if (badge) badge.textContent = savedState.zoomPercent + '%';
                }

                if (typeof window.restoreMapEngineState === 'function') {
                    window.restoreMapEngineState(savedState);
                } else if (typeof window.redrawCanvas === 'function') {
                    window.redrawCanvas();
                } else if (typeof window.renderMap === 'function') {
                    window.renderMap();
                } else if (typeof window.redraw === 'function') {
                    window.redraw();
                }
            }

            let savedMapImageObj = null;
            if (isEditMode && (!savedState || !savedState.routeLegs || savedState.routeLegs.length === 0)) {
                savedMapImageObj = new Image();
                savedMapImageObj.src = initialSavedRoute;

                function renderSavedMapToCanvas() {
                    if (userModifiedCanvas || !canvas || !savedMapImageObj) return;
                    try {
                        const ctx = canvas.getContext('2d');
                        ctx.clearRect(0, 0, canvas.width, canvas.height);
                        ctx.drawImage(savedMapImageObj, 0, 0, canvas.width, canvas.height);
                    } catch (err) {}
                }

                savedMapImageObj.onload = renderSavedMapToCanvas;
                setTimeout(renderSavedMapToCanvas, 250);
                setTimeout(renderSavedMapToCanvas, 600);
            }

            applyRestoredState();
            window.addEventListener('load', () => {
                setTimeout(applyRestoredState, 150);
                reindexStops();
                updateTimeBudget();
            });

            if (canvas) {
                ['mousedown', 'touchstart', 'pointerdown', 'wheel'].forEach(evt => {
                    canvas.addEventListener(evt, () => {
                        userModifiedCanvas = true;
                    }, { passive: true });
                });
            }

            ['routeToolBtn', 'stopToolBtn', 'undoPointBtn', 'newLegBtn', 'clearCanvasBtn', 'clearStopsBtn', 'resetBgBtn', 'zoomInBtn', 'zoomOutBtn', 'zoomResetBtn'].forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.addEventListener('click', () => {
                        userModifiedCanvas = true;
                    });
                }
            });

            function captureExactCanvas() {
                if (!canvas) return '';
                try {
                    const offscreen = document.createElement('canvas');
                    offscreen.width = canvas.width;
                    offscreen.height = canvas.height;
                    const ctx = offscreen.getContext('2d');

                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, offscreen.width, offscreen.height);
                    ctx.drawImage(canvas, 0, 0);

                    const dataUrl = offscreen.toDataURL('image/png');
                    if (dataUrl && dataUrl.length > 200) {
                        return dataUrl;
                    }
                } catch (e) {
                    try {
                        return canvas.toDataURL('image/png');
                    } catch (err) {}
                }
                return '';
            }

            function syncCurrentMapExtent() {
                if (typeof window.getMapEngineState === 'function') {
                    try {
                        const engineState = window.getMapEngineState();
                        if (engineState) {
                            if (engineState.scale !== undefined) window.scale = engineState.scale;
                            if (engineState.offsetX !== undefined) window.offsetX = engineState.offsetX;
                            if (engineState.offsetY !== undefined) window.offsetY = engineState.offsetY;
                            if (engineState.routeLegs) window.routeLegs = engineState.routeLegs;
                            if (engineState.tourStops) window.tourStops = engineState.tourStops;
                        }
                    } catch (e) {}
                }

                const badge = document.getElementById('zoomLevelBadge');
                const badgeVal = badge ? parseInt(badge.textContent, 10) : null;

                const curScale = window.scale || (window.viewState ? window.viewState.scale : null);
                const curOffX = window.offsetX !== undefined ? window.offsetX : (window.viewState ? window.viewState.offsetX : null);
                const curOffY = window.offsetY !== undefined ? window.offsetY : (window.viewState ? window.viewState.offsetY : null);

                const state = {
                    viewState: window.viewState || null,
                    scale: curScale,
                    offsetX: curOffX,
                    offsetY: curOffY,
                    zoomPercent: badgeVal,
                    routeLegs: window.routeLegs || [],
                    tourStops: window.tourStops || []
                };

                if (mapStateInput) {
                    mapStateInput.value = JSON.stringify(state);
                }

                const snap = captureExactCanvas();
                if (snap && snap.length > 200) {
                    routeInput.value = snap;
                    routeInput.setAttribute('value', snap);
                }

                return snap;
            }
            window.syncCurrentMapExtent = syncCurrentMapExtent;

            function validateAndIntercept(e) {
                const othersValid = checkOtherRequiredFields();
                if (!othersValid) {
                    if (typeof form.reportValidity === 'function') {
                        form.reportValidity();
                    }
                    if (e) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                    return false;
                }

                const missing = getStopsWithMissingDetails();
                if (missing.length > 0) {
                    if (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                    }
                    showModal(missing);
                    return false;
                }

                isSubmitting = true;
                syncCurrentMapExtent();
                return true;
            }

            if (btn) {
                btn.addEventListener('click', function(e) {
                    if (!validateAndIntercept(e)) {
                        return false;
                    }
                }, true);
            }

            if (form) {
                form.addEventListener('submit', function(e) {
                    if (!validateAndIntercept(e)) {
                        return false;
                    }
                }, true);

                const origFormSubmit = form.submit;
                form.submit = function() {
                    if (validateAndIntercept(null)) {
                        return origFormSubmit.apply(form, arguments);
                    }
                };
            }
        })();
    </script>
</body>
</html>
