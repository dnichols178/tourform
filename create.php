<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facility Tour Agenda & Report Builder</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-container">
        <header class="app-header flex-between">
            <div class="header-content">
                <h1>Facility Tour Agenda & Report Builder</h1>
                <p class="subtitle">Generate print-ready tour itineraries, leader briefings, and route maps</p>
            </div>
            <div class="header-nav">
                <a href="index.php" class="btn btn-secondary">View Saved Tours &rarr;</a>
            </div>
        </header>

        <form id="tourForm" action="generate.php" method="POST">
            <!-- Section 1: General Information, Logistics & Tour Leaders -->
            <section class="card">
                <div class="card-header">
                    <h2>1. General Information & Logistics</h2>
                </div>
                <div class="card-body">
                    <div class="grid-2">
                        <div class="form-group span-2">
                            <label for="tour_title">Tour Title / Purpose <span class="required">*</span></label>
                            <input type="text" id="tour_title" name="tour_title" placeholder="e.g., Annual Regulatory Facility Overview" required>
                        </div>

                        <!-- Date, Start Time, and Stop Time side by side on the same line -->
                        <div class="grid-3 span-2">
                            <div class="form-group">
                                <label for="tour_date">Tour Date <span class="required">*</span></label>
                                <input type="date" id="tour_date" name="tour_date" required>
                            </div>
                            <div class="form-group">
                                <label for="time_start">Start Time <span class="required">*</span></label>
                                <input type="time" id="time_start" name="time_start" required>
                            </div>
                            <div class="form-group">
                                <label for="time_end">Stop / End Time <span class="required">*</span></label>
                                <input type="time" id="time_end" name="time_end" required>
                            </div>
                        </div>

                        <div class="form-group span-2">
                            <label for="coordinator_name">Lead Coordinator <span class="required">*</span></label>
                            <input type="text" id="coordinator_name" name="coordinator_name" placeholder="Coordinator Full Name" required>
                        </div>
                        <div class="form-group">
                            <label for="coordinator_phone">Coordinator Phone <span class="required">*</span></label>
                            <input type="tel" id="coordinator_phone" name="coordinator_phone" placeholder="(000) 000-0000" maxlength="14" required>
                        </div>
                        <div class="form-group">
                            <label for="coordinator_email">Coordinator Email <span class="required">*</span></label>
                            <input type="email" id="coordinator_email" name="coordinator_email" placeholder="name@domain.gov" required>
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
                            <div class="dynamic-row-personnel">
                                <div class="person-name-field">
                                    <input type="text" name="leader_names[]" placeholder="Leader Full Name & Title" required>
                                </div>
                                <div class="person-org-field">
                                    <input type="text" name="leader_orgs[]" placeholder="Organization (e.g., DOE / FRNP)" required>
                                </div>
                                <button type="button" class="btn btn-icon btn-remove" title="Remove Leader">&times;</button>
                            </div>
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
                    <!-- Same Organization Toggle Control -->
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
                        <div class="dynamic-row-personnel">
                            <div class="person-name-field">
                                <input type="text" name="participant_names[]" placeholder="Participant Full Name" required>
                            </div>
                            <div class="person-org-field">
                                <input type="text" name="participant_orgs[]" class="participant-org-input" placeholder="Organization / Agency" required>
                            </div>
                            <button type="button" class="btn btn-icon btn-remove" title="Remove Participant">&times;</button>
                        </div>
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
                            <label><input type="checkbox" id="showArrows" name="show_arrows" value="1" checked> Arrows</label>
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
                        <!-- Floating On-Canvas Mode & Navigation Controls -->
                        <div class="canvas-viewport-controls">
                            <button type="button" class="zoom-btn active" id="panToolBtn" title="Pan / Move Map (Default)">✋</button>
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
                            <button type="button" class="zoom-btn" id="zoomOutBtn" title="Zoom Out (−)">&minus;</button>
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

                    <input type="hidden" id="route_image_data" name="route_image_data">
                </div>
            </section>

            <!-- Section 4: Facility Stops & Itinerary Times -->
            <section class="card">
                <div class="card-header flex-between">
                    <div>
                        <h2>4. Facility Stops & Itinerary Times</h2>
                        <p class="section-desc">Reorder stops using ▲/▼ buttons. Each card can collapse or expand for easier management.</p>
                    </div>
                    <div class="toolbar-group">
                        <button type="button" class="btn btn-secondary btn-sm" id="collapseAllBtn">Collapse All</button>
                        <button type="button" class="btn btn-secondary btn-sm" id="expandAllBtn">Expand All</button>
                        <button type="button" class="btn btn-primary btn-sm" id="addStopBtn">+ Add Stop</button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Real-Time Time Budget Tracker -->
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

                    <!-- Exceeded Time Alert Box -->
                    <div id="timeAlertBox" class="alert-box alert-danger" style="display: none;">
                        <strong>Time Exceeded Warning:</strong> The scheduled stops total <span id="alertAllocatedMins">0</span> minutes, which exceeds your total tour window of <span id="alertTotalMins">0</span> minutes by <span id="alertOverMins">0</span> minutes. Please edit the minute values below before finalizing.
                    </div>

                    <div id="stopsList" class="stops-container">
                        <!-- Initial Stop Card: Collapsed by default, no pre-populated name or minutes -->
                        <div class="stop-card dynamic-stop" data-expanded="false">
                            <div class="stop-header">
                                <div class="stop-header-left">
                                    <button type="button" class="btn-toggle-collapse" title="Collapse / Expand">&#9662;</button>
                                    <span class="stop-number">Stop 1</span>
                                    <span class="stop-header-preview">New Stop</span>
                                    <span class="stop-header-time-pill">-- min</span>
                                </div>
                                <div class="stop-header-actions">
                                    <button type="button" class="btn-reorder btn-move-up" title="Move Up">&#9650;</button>
                                    <button type="button" class="btn-reorder btn-move-down" title="Move Down">&#9660;</button>
                                    <button type="button" class="btn btn-icon btn-remove-stop" title="Remove Stop">&times;</button>
                                </div>
                            </div>
                            <div class="stop-body">
                                <div class="stop-grid">
                                    <div class="form-group span-2">
                                        <label>Facility / Location Name <span class="required">*</span></label>
                                        <input type="text" name="facility_names[]" class="facility-name-input" placeholder="e.g., C-300 Central Control Facility" required>
                                    </div>
                                    <div class="form-group span-2">
                                        <label>Scheduled Duration (Minutes) <span class="required">*</span></label>
                                        <input type="number" name="facility_minutes[]" class="facility-minutes-input" min="0" max="1440" placeholder="e.g., 30" required>
                                    </div>
                                    <div class="form-group span-2">
                                        <label>Subject Matter Expert (SME) at Stop (Optional)</label>
                                        <input type="text" name="facility_smes[]" placeholder="e.g., Jane Smith, Senior Operations Engineer">
                                    </div>
                                    <div class="form-group span-2">
                                        <label>Stop Agenda / Briefing Focus / Safety Requirements</label>
                                        <textarea name="facility_notes[]" rows="2" placeholder="Key topics covered, PPE required, or specific route instructions..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
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
                        <textarea name="general_notes" rows="4" placeholder="Enter badging requirements, security instructions, assembly points, transportation arrangements, or weather contingency plans..."></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-lg">Generate Tour Agenda & Report</button>
                    </div>
                </div>
            </section>
        </form>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>