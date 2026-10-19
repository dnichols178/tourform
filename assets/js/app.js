document.addEventListener('DOMContentLoaded', () => {
    // -------------------------------------------------------------
    // Helper: Sentence/Title Casing for Facility Names
    // Formats: "C-331 - PROCESS BUILDING" -> "C-331 Process Building"
    // -------------------------------------------------------------
    function toTitleCaseWord(word) {
        if (!word) return '';
        if (['II', 'III', 'IV', 'VI', 'VII', 'VIII', 'IX', 'X', 'DOE', 'FRNP', 'PPE'].includes(word.toUpperCase())) {
            return word.toUpperCase();
        }
        return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
    }

    function formatFacilityName(facilityId, description) {
        const cleanId = (facilityId || '').trim();
        let cleanDesc = (description || '').trim();

        // Strip leading dashes, dots, or colons from description if present
        cleanDesc = cleanDesc.replace(/^[\s\-–—:]+/, '').trim();

        if (!cleanDesc) {
            return cleanId;
        }

        // Convert description string to Title/Sentence case
        const titleCasedDesc = cleanDesc
            .split(/\s+/)
            .map(toTitleCaseWord)
            .join(' ');

        // If ID already starts the description, do not repeat it
        if (titleCasedDesc.toUpperCase().startsWith(cleanId.toUpperCase())) {
            return titleCasedDesc;
        }

        // Return: FacilityNumber Description (no dash)
        return `${cleanId} ${titleCasedDesc}`;
    }

    // -------------------------------------------------------------
    // Coordinator Phone Number Mask Enforcer: (000) 000-0000
    // -------------------------------------------------------------
    const coordinatorPhoneInput = document.getElementById('coordinator_phone');
    if (coordinatorPhoneInput) {
        coordinatorPhoneInput.addEventListener('input', (e) => {
            const input = e.target;
            let digits = input.value.replace(/\D/g, '');
            if (digits.length > 10) {
                digits = digits.substring(0, 10);
            }

            let formatted = '';
            if (digits.length === 0) {
                formatted = '';
            } else if (digits.length <= 3) {
                formatted = `(${digits}`;
            } else if (digits.length <= 6) {
                formatted = `(${digits.slice(0, 3)}) ${digits.slice(3)}`;
            } else {
                formatted = `(${digits.slice(0, 3)}) ${digits.slice(3, 6)}-${digits.slice(6, 10)}`;
            }

            input.value = formatted;
        });

        // Maintain closing parenthesis format on backspace
        coordinatorPhoneInput.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && coordinatorPhoneInput.value.endsWith(') ')) {
                e.preventDefault();
                const digits = coordinatorPhoneInput.value.replace(/\D/g, '');
                coordinatorPhoneInput.value = `(${digits.slice(0, -1)}`;
            }
        });
    }

    // -------------------------------------------------------------
    // Tour Leaders & Personnel Handlers
    // -------------------------------------------------------------
    const leadersList = document.getElementById('leadersList');
    const addLeaderBtn = document.getElementById('addLeaderBtn');

    if (addLeaderBtn && leadersList) {
        addLeaderBtn.addEventListener('click', () => {
            const div = document.createElement('div');
            div.className = 'dynamic-row-personnel';
            div.innerHTML = `
                <div class="person-name-field">
                    <input type="text" name="leader_names[]" placeholder="Leader Full Name & Title" required>
                </div>
                <div class="person-org-field">
                    <input type="text" name="leader_orgs[]" placeholder="Organization (e.g., DOE / FRNP)" required>
                </div>
                <button type="button" class="btn btn-icon btn-remove" title="Remove Leader">&times;</button>
            `;
            leadersList.appendChild(div);
        });
    }

    // -------------------------------------------------------------
    // Participants & Same Organization Toggle
    // -------------------------------------------------------------
    const participantsList = document.getElementById('participantsList');
    const addParticipantBtn = document.getElementById('addParticipantBtn');
    const sameOrgToggle = document.getElementById('sameOrgToggle');
    const commonOrgContainer = document.getElementById('commonOrgContainer');
    const commonOrgInput = document.getElementById('commonOrgInput');

    function syncParticipantOrgs() {
        if (!sameOrgToggle || !sameOrgToggle.checked) return;
        const orgVal = commonOrgInput.value;
        const orgInputs = participantsList.querySelectorAll('.participant-org-input');
        orgInputs.forEach(input => {
            input.value = orgVal;
            input.disabled = true;
        });
    }

    if (sameOrgToggle && commonOrgContainer && commonOrgInput && participantsList) {
        sameOrgToggle.addEventListener('change', () => {
            const isSame = sameOrgToggle.checked;
            commonOrgContainer.style.display = isSame ? 'block' : 'none';
            const orgInputs = participantsList.querySelectorAll('.participant-org-input');

            if (isSame) {
                if (!commonOrgInput.value.trim() && orgInputs.length > 0 && orgInputs[0].value.trim()) {
                    commonOrgInput.value = orgInputs[0].value.trim();
                }
                syncParticipantOrgs();
                commonOrgInput.focus();
            } else {
                orgInputs.forEach(input => {
                    input.disabled = false;
                });
            }
        });

        commonOrgInput.addEventListener('input', syncParticipantOrgs);
    }

    if (addParticipantBtn && participantsList) {
        addParticipantBtn.addEventListener('click', () => {
            const div = document.createElement('div');
            div.className = 'dynamic-row-personnel';
            const isSame = sameOrgToggle && sameOrgToggle.checked;
            const orgValue = isSame ? commonOrgInput.value : '';
            const orgDisabled = isSame ? 'disabled' : '';

            div.innerHTML = `
                <div class="person-name-field">
                    <input type="text" name="participant_names[]" placeholder="Participant Full Name" required>
                </div>
                <div class="person-org-field">
                    <input type="text" name="participant_orgs[]" class="participant-org-input" value="${orgValue}" ${orgDisabled} placeholder="Organization / Agency" required>
                </div>
                <button type="button" class="btn btn-icon btn-remove" title="Remove Participant">&times;</button>
            `;
            participantsList.appendChild(div);
        });
    }

    // Delegate row removal for leaders & participants
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('btn-remove')) {
            const row = e.target.closest('.dynamic-row-personnel');
            if (row) {
                row.remove();
            }
        }
    });

    // -------------------------------------------------------------
    // Collapsible & Reorderable Stops Engine
    // -------------------------------------------------------------
    const stopsList = document.getElementById('stopsList');
    const addStopBtn = document.getElementById('addStopBtn');
    const collapseAllBtn = document.getElementById('collapseAllBtn');
    const expandAllBtn = document.getElementById('expandAllBtn');

    const timeStartInput = document.getElementById('time_start');
    const timeEndInput = document.getElementById('time_end');
    const statTotalTour = document.getElementById('statTotalTour');
    const statAllocated = document.getElementById('statAllocated');
    const statRemaining = document.getElementById('statRemaining');
    const timeAlertBox = document.getElementById('timeAlertBox');
    const alertAllocatedMins = document.getElementById('alertAllocatedMins');
    const alertTotalMins = document.getElementById('alertTotalMins');
    const alertOverMins = document.getElementById('alertOverMins');

    // Helper: Couple duration to arrival time requirement
    function updateCardArrivalRequirement(stopCard) {
        if (!stopCard) return;
        const isStart = stopCard.getAttribute('data-is-start') === '1';
        const minsInput = stopCard.querySelector('.facility-minutes-input');
        const arrivalInput = stopCard.querySelector('.facility-arrival-input');
        const star = stopCard.querySelector('.arrival-required-star');

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

    // Create a new Stop Card (Expanded by default when added)
    function addFacilityStop(prefillName = '') {
        if (!stopsList) return null;
        const currentCount = stopsList.querySelectorAll('.dynamic-stop').length + 1;
        const displayName = prefillName || 'New Stop';

        const stopDiv = document.createElement('div');
        stopDiv.className = 'stop-card dynamic-stop';
        stopDiv.setAttribute('data-is-start', '0');
        stopDiv.setAttribute('data-expanded', 'true');
        stopDiv.innerHTML = `
            <input type="hidden" name="facility_is_start[]" value="0">
            <div class="stop-header">
                <div class="stop-header-left">
                    <button type="button" class="btn-toggle-collapse" title="Collapse / Expand">&#9652;</button>
                    <span class="stop-number">Stop ${currentCount}</span>
                    <span class="stop-header-preview">${displayName}</span>
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
                        <input type="text" name="facility_names[]" class="facility-name-input" value="${prefillName}" placeholder="e.g., C-300 Central Control Facility" required>
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
                        <input type="text" name="facility_smes[]" placeholder="e.g., Jane Smith, Senior Operations Engineer">
                    </div>
                    <div class="form-group span-2">
                        <label>Stop Agenda / Briefing Focus / Safety Requirements</label>
                        <textarea name="facility_notes[]" rows="2" placeholder="Key topics covered, PPE required, or specific route instructions..."></textarea>
                    </div>
                </div>
            </div>
        `;

        stopsList.appendChild(stopDiv);
        renumberStops();
        recalculateTimeBudget();

        stopDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        const nameInput = stopDiv.querySelector('.facility-name-input');
        if (nameInput) {
            setTimeout(() => nameInput.focus(), 150);
        }

        return stopDiv;
    }

    // Single-instance Add Stop button binding with de-duplication
    if (addStopBtn) {
        const cleanAddStopBtn = addStopBtn.cloneNode(true);
        addStopBtn.replaceWith(cleanAddStopBtn);
        cleanAddStopBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            addFacilityStop('');
        });
    }

    if (collapseAllBtn) {
        collapseAllBtn.addEventListener('click', () => {
            document.querySelectorAll('.dynamic-stop').forEach(card => card.setAttribute('data-expanded', 'false'));
        });
    }

    if (expandAllBtn) {
        expandAllBtn.addEventListener('click', () => {
            document.querySelectorAll('.dynamic-stop').forEach(card => card.setAttribute('data-expanded', 'true'));
        });
    }

    // Delegation on Stops List: Collapse, Reorder Up/Down, Remove
    if (stopsList) {
        stopsList.addEventListener('click', (e) => {
            const stopCard = e.target.closest('.dynamic-stop');
            if (!stopCard) return;

            // Toggle Collapse on Header Click
            if (e.target.closest('.stop-header') && !e.target.closest('.btn-reorder') && !e.target.closest('.btn-remove-stop')) {
                const isExpanded = stopCard.getAttribute('data-expanded') === 'true';
                stopCard.setAttribute('data-expanded', isExpanded ? 'false' : 'true');
                const body = stopCard.querySelector('.stop-body');
                if (body) body.style.display = isExpanded ? 'none' : 'block';
                const toggle = stopCard.querySelector('.btn-toggle-collapse');
                if (toggle) toggle.innerHTML = isExpanded ? '&#9662;' : '&#9652;';
                return;
            }

            // Reorder Up
            if (e.target.closest('.btn-move-up')) {
                e.stopPropagation();
                const prev = stopCard.previousElementSibling;
                if (prev && prev.classList.contains('dynamic-stop')) {
                    stopsList.insertBefore(stopCard, prev);
                    renumberStops();
                    recalculateTimeBudget();
                }
                return;
            }

            // Reorder Down
            if (e.target.closest('.btn-move-down')) {
                e.stopPropagation();
                const next = stopCard.nextElementSibling;
                if (next && next.classList.contains('dynamic-stop')) {
                    stopsList.insertBefore(next, stopCard);
                    renumberStops();
                    recalculateTimeBudget();
                }
                return;
            }

            // Remove Stop (Protected for non-start stops)
            if (e.target.closest('.btn-remove-stop')) {
                e.stopPropagation();
                if (stopCard.getAttribute('data-is-start') === '1') {
                    return; // Start location cannot be deleted
                }

                const allCards = Array.from(stopsList.querySelectorAll('.dynamic-stop'));
                const cardIndex = allCards.indexOf(stopCard);
                if (cardIndex > -1 && cardIndex < stopsMarkers.length) {
                    stopsMarkers.splice(cardIndex, 1);
                }

                stopCard.remove();
                renumberStops();
                recalculateTimeBudget();
                render(false);
            }
        });

        // Sync Live Header Summary as user types & couple Arrival Time
        stopsList.addEventListener('input', (e) => {
            const stopCard = e.target.closest('.dynamic-stop');
            if (!stopCard) return;

            if (e.target.classList.contains('facility-name-input')) {
                const preview = stopCard.querySelector('.stop-header-preview');
                const isStart = stopCard.getAttribute('data-is-start') === '1';
                if (preview) {
                    preview.textContent = e.target.value.trim() || (isStart ? 'Start Location (Required)' : 'New Stop');
                }
            }

            if (e.target.classList.contains('facility-minutes-input')) {
                const timePill = stopCard.querySelector('.stop-header-time-pill');
                if (timePill) {
                    const rawVal = e.target.value.trim();
                    const val = parseInt(rawVal, 10);
                    timePill.textContent = (rawVal !== '' && !isNaN(val) && val >= 0) ? `${val} min` : '-- min';
                }
                updateCardArrivalRequirement(stopCard);
                recalculateTimeBudget();
            }
        });
    }

    // Renumber Stops Chronologically & Enforce Start Stop State
    function renumberStops() {
        if (!stopsList) return;
        const stopCards = stopsList.querySelectorAll('.dynamic-stop');
        stopCards.forEach((card, index) => {
            const isStart = (index === 0);
            card.setAttribute('data-is-start', isStart ? '1' : '0');

            const isStartHidden = card.querySelector('input[name="facility_is_start[]"]');
            if (isStartHidden) {
                isStartHidden.value = isStart ? '1' : '0';
            }

            const headerLeft = card.querySelector('.stop-header-left');
            const label = card.querySelector('.stop-number');
            const preview = card.querySelector('.stop-header-preview');
            const nameInput = card.querySelector('.facility-name-input');
            const removeBtn = card.querySelector('.btn-remove-stop');
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
                if (label) label.textContent = 'Start Location';
                if (preview) preview.textContent = (nameInput && nameInput.value.trim()) ? nameInput.value.trim() : 'Start Location (Required)';
                if (removeBtn) removeBtn.style.display = 'none';
            } else {
                card.classList.remove('is-start-stop');
                if (startBadge) startBadge.remove();
                if (label) label.textContent = `Stop ${index + 1}`;
                if (preview) preview.textContent = (nameInput && nameInput.value.trim()) ? nameInput.value.trim() : 'New Stop';
                if (removeBtn) removeBtn.style.display = 'inline-flex';
            }

            updateCardArrivalRequirement(card);
        });
    }

    // -------------------------------------------------------------
    // Live Tour Time Budget Tracker
    // -------------------------------------------------------------
    function calculateTotalTourMinutes() {
        if (!timeStartInput || !timeEndInput) return null;
        const start = timeStartInput.value;
        const end = timeEndInput.value;
        if (!start || !end) return null;

        const [startH, startM] = start.split(':').map(Number);
        const [endH, endM] = end.split(':').map(Number);

        let startMinutes = (startH * 60) + startM;
        let endMinutes = (endH * 60) + endM;

        if (endMinutes < startMinutes) {
            endMinutes += 24 * 60;
        }

        return endMinutes - startMinutes;
    }

    function recalculateTimeBudget() {
        if (!statAllocated || !statTotalTour) return;
        const totalTourMins = calculateTotalTourMinutes();
        const minuteInputs = Array.from(document.querySelectorAll('.facility-minutes-input'));

        let totalAllocated = 0;
        minuteInputs.forEach((input) => {
            const val = parseInt(input.value, 10);
            if (!isNaN(val) && val > 0) {
                totalAllocated += val;
            }
        });

        statAllocated.textContent = `${totalAllocated} mins`;

        if (totalTourMins === null) {
            statTotalTour.textContent = '-- mins (set times)';
            if (statRemaining) {
                statRemaining.textContent = '-- mins';
                statRemaining.className = 'budget-val';
            }
            if (timeAlertBox) timeAlertBox.style.display = 'none';
            minuteInputs.forEach(inp => inp.classList.remove('input-danger'));
            return;
        }

        statTotalTour.textContent = `${totalTourMins} mins`;
        const remaining = totalTourMins - totalAllocated;

        if (remaining < 0) {
            const overMins = Math.abs(remaining);
            if (statRemaining) {
                statRemaining.textContent = `Exceeded by ${overMins} mins`;
                statRemaining.className = 'budget-val exceeded';
            }

            if (alertAllocatedMins) alertAllocatedMins.textContent = String(totalAllocated);
            if (alertTotalMins) alertTotalMins.textContent = String(totalTourMins);
            if (alertOverMins) alertOverMins.textContent = String(overMins);
            if (timeAlertBox) timeAlertBox.style.display = 'block';
            minuteInputs.forEach(inp => inp.classList.add('input-danger'));
        } else {
            if (statRemaining) {
                statRemaining.textContent = `${remaining} mins`;
                statRemaining.className = 'budget-val ok';
            }
            if (timeAlertBox) timeAlertBox.style.display = 'none';
            minuteInputs.forEach(inp => inp.classList.remove('input-danger'));
        }
    }

    if (timeStartInput) timeStartInput.addEventListener('change', recalculateTimeBudget);
    if (timeEndInput) timeEndInput.addEventListener('change', recalculateTimeBudget);

    // -------------------------------------------------------------
    // GIS Layers, Pan/Zoom & Vector Canvas Engine
    // -------------------------------------------------------------
    const canvas = document.getElementById('routeCanvas');
    const ctx = canvas ? canvas.getContext('2d') : null;
    const mapTooltip = document.getElementById('mapTooltip');
    const gisStatusMessage = document.getElementById('gisStatusMessage');
    const canvasModeLabel = document.getElementById('canvasModeLabel');
    const canvasModeHelp = document.getElementById('canvasModeHelp');

    // Floating Viewport & Mode Controls
    const panToolBtn = document.getElementById('panToolBtn');
    const stopToolBtn = document.getElementById('stopToolBtn');
    const routeToolBtn = document.getElementById('routeToolBtn');
    const zoomInBtn = document.getElementById('zoomInBtn');
    const zoomOutBtn = document.getElementById('zoomOutBtn');
    const zoomResetBtn = document.getElementById('zoomResetBtn');
    const zoomLevelBadge = document.getElementById('zoomLevelBadge');

    // Toolbar Controls
    const strokeColor = document.getElementById('strokeColor');
    const lineWidth = document.getElementById('lineWidth');
    const showRoads = document.getElementById('showRoads');
    const showFootprints = document.getElementById('showFootprints');
    const showLabels = document.getElementById('showLabels');
    const showArrows = document.getElementById('showArrows');
    const undoPointBtn = document.getElementById('undoPointBtn');
    const newLegBtn = document.getElementById('newLegBtn');
    const clearCanvasBtn = document.getElementById('clearCanvasBtn');
    const clearStopsBtn = document.getElementById('clearStopsBtn');
    const resetBgBtn = document.getElementById('resetBgBtn');
    const bgImageUpload = document.getElementById('bgImageUpload');
    const routeImageHidden = document.getElementById('route_image_data');
    const tourForm = document.getElementById('tourForm');

    // Viewport Matrix State
    let viewport = {
        zoom: 1.0,
        panX: 0,
        panY: 0,
        minZoom: 0.6,
        maxZoom: 10.0
    };

    // Tool Modes: 'pan' (DEFAULT), 'stop', 'route'
    let currentMode = 'pan';
    let isDragging = false;
    let dragStartX = 0;
    let dragStartY = 0;

    // GIS Data & Transform State
    let gisData = null;
    let roadsData = null;
    let hoveredFeature = null;

    // Stops Marker Array: [{ x, y, name }]
    let stopsMarkers = [];

    // Vector Route State: array of legs, each containing an array of {x, y}
    let legs = [[]]; 
    let activeLegIndex = 0;
    let hoverPos = null;
    let bgImage = null;

    // Mode Selector Handler
    function setMode(mode) {
        currentMode = mode;
        if (panToolBtn) panToolBtn.classList.toggle('active', mode === 'pan');
        if (stopToolBtn) stopToolBtn.classList.toggle('active', mode === 'stop');
        if (routeToolBtn) routeToolBtn.classList.toggle('active', mode === 'route');

        if (mode === 'pan') {
            if (canvasModeLabel) canvasModeLabel.textContent = 'Mode: Pan Map';
            if (canvasModeHelp) canvasModeHelp.innerHTML = '<strong>Pan Tool:</strong> Drag to move map. Scroll wheel to zoom in/out.';
            if (canvas) canvas.style.cursor = 'grab';
        } else if (mode === 'stop') {
            if (canvasModeLabel) canvasModeLabel.textContent = 'Mode: Place Stop';
            if (canvasModeHelp) canvasModeHelp.innerHTML = '<strong>Stop Tool:</strong> Click any building or location to place a stop and add to itinerary.';
            if (canvas) canvas.style.cursor = 'pointer';
        } else if (mode === 'route') {
            if (canvasModeLabel) canvasModeLabel.textContent = 'Mode: Draw Route';
            if (canvasModeHelp) canvasModeHelp.innerHTML = '<strong>Route Tool:</strong> Click to draw route waypoints with arrows. Double-click to complete leg.';
            if (canvas) canvas.style.cursor = 'crosshair';
        }

        if (mapTooltip) mapTooltip.style.display = 'none';
        render(false);
    }

    if (panToolBtn) panToolBtn.addEventListener('click', () => setMode('pan'));
    if (stopToolBtn) stopToolBtn.addEventListener('click', () => setMode('stop'));
    if (routeToolBtn) routeToolBtn.addEventListener('click', () => setMode('route'));

    // Fetch Facilities and Roads in parallel
    Promise.all([
        fetch('data/gis/facilities.json').then(r => r.ok ? r.json() : null),
        fetch('data/gis/roads.json').then(r => r.ok ? r.json() : null)
    ])
    .then(([facData, roadData]) => {
        gisData = facData;
        roadsData = roadData;
        if (gisStatusMessage) gisStatusMessage.style.display = 'none';

        if (gisData && gisData.bounds) {
            computeTransforms();
        }

        // Re-apply saved state if loaded during tour editing
        const mapStateInput = document.getElementById('map_state');
        if (mapStateInput && mapStateInput.value && typeof window.restoreMapEngineState === 'function') {
            window.restoreMapEngineState(mapStateInput.value);
        } else {
            render(false);
        }
    })
    .catch(err => {
        console.error('GIS layer loading error:', err);
        render(false);
    });

    // Compute Base Transform (Local Survey Feet -> Base Canvas 900x580 Space)
    function computeTransforms() {
        if (!gisData || !gisData.bounds || !canvas) return;

        const bounds = gisData.bounds;
        const padding = 28;
        const availWidth = canvas.width - (padding * 2);
        const availHeight = canvas.height - (padding * 2);

        const dataWidth = bounds.xmax - bounds.xmin;
        const dataHeight = bounds.ymax - bounds.ymin;

        const scale = Math.min(availWidth / dataWidth, availHeight / dataHeight);
        const offsetX = padding + (availWidth - (dataWidth * scale)) / 2;
        const offsetY = padding + (availHeight - (dataHeight * scale)) / 2;

        gisData.features.forEach(feat => {
            feat.canvasRings = feat.rings.map(ring => {
                return ring.map(pt => [
                    offsetX + (pt[0] - bounds.xmin) * scale,
                    offsetY + (bounds.ymax - pt[1]) * scale
                ]);
            });

            feat.canvasCenter = [
                offsetX + (feat.center[0] - bounds.xmin) * scale,
                offsetY + (bounds.ymax - feat.center[1]) * scale
            ];
        });

        if (roadsData && roadsData.roads) {
            roadsData.roads.forEach(road => {
                road.canvasPoints = road.points.map(pt => [
                    offsetX + (pt[0] - bounds.xmin) * scale,
                    offsetY + (bounds.ymax - pt[1]) * scale
                ]);
            });
        }
    }

    // Raycasting Point-in-Polygon
    function isPointInPolygon(px, py, ring) {
        let inside = false;
        for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
            const xi = ring[i][0], yi = ring[i][1];
            const xj = ring[j][0], yj = ring[j][1];
            const intersect = ((yi > py) !== (yj > py)) &&
                (px < (xj - xi) * (py - yi) / (yj - yi) + xi);
            if (intersect) inside = !inside;
        }
        return inside;
    }

    function getFeatureAtPosition(worldX, worldY) {
        if (!gisData || !showFootprints || !showFootprints.checked) return null;
        for (let i = gisData.features.length - 1; i >= 0; i--) {
            const feat = gisData.features[i];
            if (!feat.canvasRings) continue;
            for (let r = 0; r < feat.canvasRings.length; r++) {
                if (isPointInPolygon(worldX, worldY, feat.canvasRings[r])) {
                    return feat;
                }
            }
        }
        return null;
    }

    // Draw background grid in base coordinates
    function drawGrid() {
        if (!ctx || !canvas) return;
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        ctx.strokeStyle = '#f0f0f0';
        ctx.lineWidth = 1;
        const gridSize = 25;

        for (let x = 0; x < canvas.width; x += gridSize) {
            ctx.beginPath();
            ctx.moveTo(x, 0);
            ctx.lineTo(x, canvas.height);
            ctx.stroke();
        }
        for (let y = 0; y < canvas.height; y += gridSize) {
            ctx.beginPath();
            ctx.moveTo(0, y);
            ctx.lineTo(canvas.width, y);
            ctx.stroke();
        }
    }

    // Draw Public Roadway Network
    function drawRoads() {
        if (!ctx || !roadsData || !showRoads || !showRoads.checked || !roadsData.roads) return;

        ctx.save();
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';

        const uniformRoadWidth = Math.max(1.1, 1.8 / viewport.zoom);
        ctx.strokeStyle = '#88929b';
        ctx.lineWidth = uniformRoadWidth;

        roadsData.roads.forEach(road => {
            if (!road.canvasPoints || road.canvasPoints.length < 2) return;

            ctx.beginPath();
            ctx.moveTo(road.canvasPoints[0][0], road.canvasPoints[0][1]);
            for (let i = 1; i < road.canvasPoints.length; i++) {
                ctx.lineTo(road.canvasPoints[i][0], road.canvasPoints[i][1]);
            }
            ctx.stroke();
        });

        ctx.restore();
    }

    // Draw GIS Footprints & Labels
    function drawFootprints() {
        if (!ctx || !gisData || !showFootprints || !showFootprints.checked) return;

        const renderLabels = showLabels && showLabels.checked;

        gisData.features.forEach(feat => {
            if (!feat.canvasRings) return;
            const isHovered = (feat === hoveredFeature);

            ctx.beginPath();
            feat.canvasRings.forEach(ring => {
                ctx.moveTo(ring[0][0], ring[0][1]);
                for (let i = 1; i < ring.length; i++) {
                    ctx.lineTo(ring[i][0], ring[i][1]);
                }
                ctx.closePath();
            });

            if (isHovered && currentMode !== 'pan') {
                ctx.fillStyle = 'rgba(26, 115, 232, 0.45)';
                ctx.fill();
                ctx.strokeStyle = '#1a73e8';
                ctx.lineWidth = Math.max(1, 2 / viewport.zoom);
                ctx.stroke();
            } else {
                ctx.fillStyle = 'rgba(215, 220, 226, 0.40)';
                ctx.fill();
                ctx.strokeStyle = '#5a6268';
                ctx.lineWidth = Math.max(0.7, 1 / viewport.zoom);
                ctx.stroke();
            }

            if (renderLabels && feat.canvasCenter) {
                const [cx, cy] = feat.canvasCenter;
                const fontSize = Math.max(7, Math.min(13, 9 / viewport.zoom));
                ctx.font = isHovered ? `bold ${fontSize}px sans-serif` : `${fontSize}px sans-serif`;
                ctx.fillStyle = isHovered ? '#1a73e8' : '#2d3748';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(feat.id, cx, cy);
            }
        });
    }

    // Draw Green Circle Stops
    function drawStops() {
        if (!ctx || stopsMarkers.length === 0) return;

        ctx.save();
        stopsMarkers.forEach((stop) => {
            const radius = Math.max(5.5, 9 / viewport.zoom);

            ctx.beginPath();
            ctx.arc(stop.x, stop.y, radius, 0, Math.PI * 2);
            ctx.fillStyle = '#188038';
            ctx.fill();

            ctx.strokeStyle = '#ffffff';
            ctx.lineWidth = Math.max(1.5, 2.2 / viewport.zoom);
            ctx.stroke();

            ctx.strokeStyle = 'rgba(0, 0, 0, 0.35)';
            ctx.lineWidth = Math.max(0.6, 1 / viewport.zoom);
            ctx.stroke();
        });
        ctx.restore();
    }

    // Draw single directional arrowhead
    function drawArrowHead(x, y, angle, color, size) {
        if (!ctx) return;
        ctx.save();
        ctx.translate(x, y);
        ctx.rotate(angle);
        ctx.beginPath();
        ctx.moveTo(size * 0.8, 0);
        ctx.lineTo(-size * 0.8, -size * 0.55);
        ctx.lineTo(-size * 0.4, 0);
        ctx.lineTo(-size * 0.8, size * 0.55);
        ctx.closePath();
        ctx.fillStyle = color;
        ctx.fill();
        ctx.restore();
    }

    // Draw directional arrows along a line segment
    function drawSegmentArrows(p1, p2, color, strokeWidth) {
        const dx = p2.x - p1.x;
        const dy = p2.y - p1.y;
        const distance = Math.hypot(dx, dy);

        if (distance < (28 / viewport.zoom)) return;

        const angle = Math.atan2(dy, dx);
        const arrowSize = Math.max(6, (strokeWidth * 2.5) / viewport.zoom);

        if (distance < (90 / viewport.zoom)) {
            const midX = (p1.x + p2.x) / 2;
            const midY = (p1.y + p2.y) / 2;
            drawArrowHead(midX, midY, angle, color, arrowSize);
        } else {
            const step = 60 / viewport.zoom;
            const margin = 26 / viewport.zoom;
            for (let d = margin + (step / 2); d < distance - margin; d += step) {
                const fraction = d / distance;
                const ax = p1.x + dx * fraction;
                const ay = p1.y + dy * fraction;
                drawArrowHead(ax, ay, angle, color, arrowSize);
            }
        }
    }

    // Main Redraw Loop with Viewport Transformation
    function render(includeHoverGuide = true) {
        if (!ctx || !canvas) return;
        ctx.save();
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        ctx.translate(viewport.panX, viewport.panY);
        ctx.scale(viewport.zoom, viewport.zoom);

        // 1. Base Layer
        if (bgImage) {
            ctx.drawImage(bgImage, 0, 0, canvas.width, canvas.height);
        } else {
            drawGrid();
        }

        // 2. Road Layer
        drawRoads();

        // 3. GIS Footprints
        drawFootprints();

        // 4. Green Stop Markers
        drawStops();

        const selectedColor = strokeColor ? strokeColor.value : '#d9381e';
        const selectedWidth = (lineWidth ? parseInt(lineWidth.value, 10) : 4) / viewport.zoom;
        const renderArrows = showArrows ? showArrows.checked : true;

        // 5. Committed Route Legs
        legs.forEach((leg) => {
            if (leg.length > 1) {
                ctx.beginPath();
                ctx.strokeStyle = selectedColor;
                ctx.lineWidth = selectedWidth;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
                ctx.setLineDash([]);

                ctx.moveTo(leg[0].x, leg[0].y);
                for (let i = 1; i < leg.length; i++) {
                    ctx.lineTo(leg[i].x, leg[i].y);
                }
                ctx.stroke();

                if (renderArrows) {
                    for (let i = 0; i < leg.length - 1; i++) {
                        drawSegmentArrows(leg[i], leg[i + 1], selectedColor, selectedWidth);
                    }
                }
            }
        });

        // 6. Rubber-Band Guide Line
        if (includeHoverGuide && currentMode === 'route' && hoverPos && legs[activeLegIndex] && legs[activeLegIndex].length > 0) {
            const currentLeg = legs[activeLegIndex];
            const lastPoint = currentLeg[currentLeg.length - 1];

            ctx.beginPath();
            ctx.strokeStyle = selectedColor;
            ctx.lineWidth = Math.max(1, selectedWidth - (1 / viewport.zoom));
            ctx.setLineDash([6 / viewport.zoom, 4 / viewport.zoom]);
            ctx.moveTo(lastPoint.x, lastPoint.y);
            ctx.lineTo(hoverPos.x, hoverPos.y);
            ctx.stroke();
            ctx.setLineDash([]);

            if (renderArrows) {
                drawSegmentArrows(lastPoint, hoverPos, selectedColor, selectedWidth);
            }
        }

        // 7. Route Vertex Nodes (Clean solid dots, no numbers)
        legs.forEach((leg) => {
            leg.forEach((pt) => {
                const nodeRadius = Math.max(3.5 / viewport.zoom, (selectedWidth * 1.2));
                ctx.beginPath();
                ctx.arc(pt.x, pt.y, nodeRadius, 0, Math.PI * 2);
                ctx.fillStyle = selectedColor;
                ctx.fill();
                ctx.strokeStyle = '#ffffff';
                ctx.lineWidth = 1.2 / viewport.zoom;
                ctx.stroke();
            });
        });

        ctx.restore();
    }

    // Coordinate Conversion
    function getCanvasCoordinates(e) {
        if (!canvas) return { canvasX: 0, canvasY: 0, worldX: 0, worldY: 0, domX: 0, domY: 0 };
        const rect = canvas.getBoundingClientRect();
        const scaleX = canvas.width / rect.width;
        const scaleY = canvas.height / rect.height;

        let clientX = e.clientX;
        let clientY = e.clientY;

        if (e.touches && e.touches.length > 0) {
            clientX = e.touches[0].clientX;
            clientY = e.touches[0].clientY;
        }

        const canvasX = (clientX - rect.left) * scaleX;
        const canvasY = (clientY - rect.top) * scaleY;

        const worldX = (canvasX - viewport.panX) / viewport.zoom;
        const worldY = (canvasY - viewport.panY) / viewport.zoom;

        return {
            canvasX: canvasX,
            canvasY: canvasY,
            worldX: Math.round(worldX * 10) / 10,
            worldY: Math.round(worldY * 10) / 10,
            domX: clientX - rect.left,
            domY: clientY - rect.top
        };
    }

    // Zoom Math Centered on Point
    function zoomAtPoint(cx, cy, factor) {
        const newZoom = Math.min(Math.max(viewport.zoom * factor, viewport.minZoom), viewport.maxZoom);
        const actualFactor = newZoom / viewport.zoom;

        viewport.panX = cx - (cx - viewport.panX) * actualFactor;
        viewport.panY = cy - (cy - viewport.panY) * actualFactor;
        viewport.zoom = newZoom;

        updateZoomUI();
        render(true);
    }

    function updateZoomUI() {
        if (zoomLevelBadge) {
            zoomLevelBadge.textContent = `${Math.round(viewport.zoom * 100)}%`;
        }
    }

    function resetView() {
        viewport.zoom = 1.0;
        viewport.panX = 0;
        viewport.panY = 0;
        updateZoomUI();
        render(true);
    }

    if (zoomInBtn && canvas) zoomInBtn.addEventListener('click', () => zoomAtPoint(canvas.width / 2, canvas.height / 2, 1.25));
    if (zoomOutBtn && canvas) zoomOutBtn.addEventListener('click', () => zoomAtPoint(canvas.width / 2, canvas.height / 2, 1 / 1.25));
    if (zoomResetBtn) zoomResetBtn.addEventListener('click', resetView);

    // Mouse Wheel Zooming
    if (canvas) {
        canvas.addEventListener('wheel', (e) => {
            e.preventDefault();
            const coords = getCanvasCoordinates(e);
            const factor = e.deltaY < 0 ? 1.15 : (1 / 1.15);
            zoomAtPoint(coords.canvasX, coords.canvasY, factor);
        }, { passive: false });

        // Canvas MouseDown
        canvas.addEventListener('mousedown', (e) => {
            if (currentMode === 'pan' || e.spaceKey || e.button === 1 || e.button === 2) {
                isDragging = true;
                dragStartX = e.clientX - viewport.panX;
                dragStartY = e.clientY - viewport.panY;
                canvas.style.cursor = 'grabbing';
                return;
            }

            if (e.button === 0) {
                const coords = getCanvasCoordinates(e);
                const feat = getFeatureAtPosition(coords.worldX, coords.worldY);

                // MODE 1: PLACE GREEN CIRCLE STOP
                if (currentMode === 'stop') {
                    let stopPos = { x: coords.worldX, y: coords.worldY };
                    let formattedLabel = `Stop ${stopsMarkers.length + 1}`;

                    if (feat && feat.canvasCenter) {
                        stopPos = {
                            x: Math.round(feat.canvasCenter[0] * 10) / 10,
                            y: Math.round(feat.canvasCenter[1] * 10) / 10
                        };
                        formattedLabel = formatFacilityName(feat.id, feat.desc);
                    }

                    stopsMarkers.push({
                        x: stopPos.x,
                        y: stopPos.y,
                        name: formattedLabel
                    });

                    // ISOLATE START LOCATION: Never populate the default Start Location from map clicks.
                    // Filter to non-start cards only:
                    const nonStartCards = Array.from(document.querySelectorAll('.dynamic-stop')).filter(card => card.getAttribute('data-is-start') !== '1');
                    const emptyNonStart = nonStartCards.map(c => c.querySelector('.facility-name-input')).find(inp => inp && inp.value.trim() === '');

                    if (emptyNonStart) {
                        emptyNonStart.value = formattedLabel;
                        const card = emptyNonStart.closest('.dynamic-stop');
                        if (card) {
                            const preview = card.querySelector('.stop-header-preview');
                            if (preview) preview.textContent = formattedLabel;
                        }
                    } else {
                        addFacilityStop(formattedLabel);
                    }

                    render(true);
                    return;
                }

                // MODE 2: DRAW ROUTE WAYPOINTS
                if (currentMode === 'route') {
                    let targetPoint = { x: coords.worldX, y: coords.worldY };

                    if (feat && feat.canvasCenter) {
                        targetPoint = {
                            x: Math.round(feat.canvasCenter[0] * 10) / 10,
                            y: Math.round(feat.canvasCenter[1] * 10) / 10
                        };
                    }

                    if (!legs[activeLegIndex]) {
                        legs[activeLegIndex] = [];
                    }
                    legs[activeLegIndex].push(targetPoint);
                    render(true);
                }
            }
        });
    }

    // Window MouseMove
    window.addEventListener('mousemove', (e) => {
        if (!canvas) return;
        if (isDragging) {
            viewport.panX = e.clientX - dragStartX;
            viewport.panY = e.clientY - dragStartY;
            render(false);
            return;
        }

        const rect = canvas.getBoundingClientRect();
        if (e.clientX < rect.left || e.clientX > rect.right || e.clientY < rect.top || e.clientY > rect.bottom) {
            if (hoverPos) {
                hoverPos = null;
                hoveredFeature = null;
                if (mapTooltip) mapTooltip.style.display = 'none';
                render(false);
            }
            return;
        }

        const coords = getCanvasCoordinates(e);
        hoverPos = { x: coords.worldX, y: coords.worldY };

        if (currentMode !== 'pan') {
            const feat = getFeatureAtPosition(coords.worldX, coords.worldY);
            if (feat !== hoveredFeature) {
                hoveredFeature = feat;
                render(true);
            } else if (currentMode === 'route' && hoverPos && legs[activeLegIndex] && legs[activeLegIndex].length > 0) {
                render(true);
            }

            if (hoveredFeature) {
                canvas.style.cursor = 'pointer';
                if (mapTooltip) {
                    mapTooltip.style.display = 'block';
                    mapTooltip.style.left = `${coords.domX + 12}px`;
                    mapTooltip.style.top = `${coords.domY + 12}px`;

                    const displayTooltip = formatFacilityName(hoveredFeature.id, hoveredFeature.desc);
                    mapTooltip.innerHTML = `<strong>${displayTooltip}</strong>`;
                }
            } else {
                canvas.style.cursor = currentMode === 'route' ? 'crosshair' : 'pointer';
                if (mapTooltip) mapTooltip.style.display = 'none';
            }
        }
    });

    window.addEventListener('mouseup', () => {
        if (isDragging) {
            isDragging = false;
            if (canvas) canvas.style.cursor = currentMode === 'pan' ? 'grab' : (currentMode === 'route' ? 'crosshair' : 'pointer');
            render(true);
        }
    });

    if (canvas) {
        canvas.addEventListener('contextmenu', (e) => e.preventDefault());
    }

    // Spacebar temporary pan
    window.addEventListener('keydown', (e) => {
        if (e.code === 'Space' && !['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) {
            e.preventDefault();
            if (canvas) canvas.style.cursor = 'grab';
        }
        if ((e.ctrlKey || e.metaKey) && e.key === 'z') {
            if (!['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) {
                e.preventDefault();
                undoAction();
            }
        }
    });

    window.addEventListener('keyup', (e) => {
        if (e.code === 'Space') {
            if (canvas) canvas.style.cursor = currentMode === 'pan' ? 'grab' : (currentMode === 'route' ? 'crosshair' : 'pointer');
        }
    });

    // -------------------------------------------------------------
    // Global State Sync: Connect Engine to Form & Database Serializers
    // -------------------------------------------------------------
    window.getMapEngineState = function() {
        return {
            zoom: viewport.zoom,
            scale: viewport.zoom,
            panX: viewport.panX,
            panY: viewport.panY,
            offsetX: viewport.panX,
            offsetY: viewport.panY,
            zoomPercent: Math.round(viewport.zoom * 100),
            routeLegs: legs,
            tourStops: stopsMarkers
        };
    };

    window.restoreMapEngineState = function(savedState) {
        if (!savedState) return;
        try {
            const state = typeof savedState === 'string' ? JSON.parse(savedState) : savedState;

            // 1. Restore zoom level
            const targetZoom = state.zoom || state.scale;
            if (targetZoom && !isNaN(targetZoom)) {
                viewport.zoom = parseFloat(targetZoom);
            }

            // 2. Restore pan offsets
            if (state.panX !== undefined) {
                viewport.panX = parseFloat(state.panX);
            } else if (state.offsetX !== undefined) {
                viewport.panX = parseFloat(state.offsetX);
            }

            if (state.panY !== undefined) {
                viewport.panY = parseFloat(state.panY);
            } else if (state.offsetY !== undefined) {
                viewport.panY = parseFloat(state.offsetY);
            }

            // 3. Restore route legs
            const restoredLegs = state.routeLegs || state.legs;
            if (Array.isArray(restoredLegs) && restoredLegs.length > 0) {
                legs = JSON.parse(JSON.stringify(restoredLegs));
                activeLegIndex = legs.length - 1;
            }

            // 4. Restore stop markers
            const restoredStops = state.tourStops || state.stopsMarkers;
            if (Array.isArray(restoredStops)) {
                stopsMarkers = JSON.parse(JSON.stringify(restoredStops));
            }

            // 5. Update UI badge and repaint
            updateZoomUI();
            render(false);
        } catch (e) {
            console.error('Failed to restore map engine state:', e);
        }
    };

    // Start New Route Leg
    function startNewLeg() {
        if (legs[activeLegIndex] && legs[activeLegIndex].length > 0) {
            legs.push([]);
            activeLegIndex = legs.length - 1;
            render(true);
        }
    }

    if (canvas) {
        canvas.addEventListener('dblclick', (e) => {
            e.preventDefault();
            if (currentMode === 'route') {
                startNewLeg();
            }
        });
    }

    // Context-Aware Undo Action
    function undoAction() {
        if (currentMode === 'stop' && stopsMarkers.length > 0) {
            stopsMarkers.pop();
            const stopCards = stopsList ? stopsList.querySelectorAll('.dynamic-stop') : [];
            // Never remove or alter the initial Start stop card on map undo
            if (stopCards.length > 1) {
                stopCards[stopCards.length - 1].remove();
                renumberStops();
                recalculateTimeBudget();
            }
            render(true);
            return;
        }

        if (legs.length === 0) return;
        let currentLeg = legs[activeLegIndex];
        if (currentLeg && currentLeg.length > 0) {
            currentLeg.pop();
        } else if (activeLegIndex > 0) {
            legs.pop();
            activeLegIndex = legs.length - 1;
            if (legs[activeLegIndex] && legs[activeLegIndex].length > 0) {
                legs[activeLegIndex].pop();
            }
        }
        render(true);
    }

    // Toolbar Event Listeners
    if (undoPointBtn) undoPointBtn.addEventListener('click', undoAction);
    if (newLegBtn) newLegBtn.addEventListener('click', startNewLeg);
    if (strokeColor) strokeColor.addEventListener('input', () => render(true));
    if (lineWidth) lineWidth.addEventListener('change', () => render(true));
    if (showRoads) showRoads.addEventListener('change', () => render(true));
    if (showFootprints) showFootprints.addEventListener('change', () => render(true));
    if (showLabels) showLabels.addEventListener('change', () => render(true));
    if (showArrows) showArrows.addEventListener('change', () => render(true));

    if (clearCanvasBtn) {
        clearCanvasBtn.addEventListener('click', () => {
            legs = [[]];
            activeLegIndex = 0;
            hoverPos = null;
            render(false);
        });
    }

    // Clear Stops: Reset stop markers and reset DOM stops back to empty Start stop
    if (clearStopsBtn) {
        clearStopsBtn.addEventListener('click', () => {
            stopsMarkers = [];
            if (stopsList) {
                stopsList.innerHTML = `
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
            renumberStops();
            recalculateTimeBudget();
            render(false);
        });
    }

    if (resetBgBtn && bgImageUpload) {
        resetBgBtn.addEventListener('click', () => {
            bgImage = null;
            bgImageUpload.value = '';
            render(true);
        });
    }

    // Base Map Image Upload
    if (bgImageUpload) {
        bgImageUpload.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = (event) => {
                const img = new Image();
                img.onload = () => {
                    bgImage = img;
                    render(true);
                };
                img.src = event.target.result;
            };
            reader.readAsDataURL(file);
        });
    }

    // -------------------------------------------------------------
    // Form Submit Handler
    // -------------------------------------------------------------
    if (tourForm) {
        tourForm.addEventListener('submit', (e) => {
            const totalTourMins = calculateTotalTourMinutes();
            let totalAllocated = 0;
            document.querySelectorAll('.facility-minutes-input').forEach((inp) => {
                const val = parseInt(inp.value, 10);
                if (!isNaN(val) && val > 0) totalAllocated += val;
            });

            if (totalTourMins !== null && totalAllocated > totalTourMins) {
                const overMins = totalAllocated - totalTourMins;
                const proceed = confirm(
                    `Warning: Scheduled facility stops total ${totalAllocated} minutes, exceeding the tour window of ${totalTourMins} minutes by ${overMins} minutes.\n\nDo you want to proceed and generate the report anyway?`
                );
                if (!proceed) {
                    e.preventDefault();
                    return;
                }
            }

            if (sameOrgToggle && sameOrgToggle.checked) {
                document.querySelectorAll('.participant-org-input').forEach(inp => {
                    inp.disabled = false;
                    inp.value = commonOrgInput.value;
                });
            }

            // Render the exact viewport view without hover crosshairs or popups
            hoveredFeature = null;
            hoverPos = null;
            render(false);

            // Capture exact current view as PNG at its current zoom and pan coordinates
            if (routeImageHidden && canvas) {
                routeImageHidden.value = canvas.toDataURL('image/png');
            }

            // Serialize current zoom, pan, stops, and legs into map_state
            const mapStateHidden = document.getElementById('map_state');
            if (mapStateHidden && typeof window.getMapEngineState === 'function') {
                mapStateHidden.value = JSON.stringify(window.getMapEngineState());
            }
        });
    }

    // Check if editing an existing record and restore map state immediately
    const initialMapStateInput = document.getElementById('map_state');
    if (initialMapStateInput && initialMapStateInput.value && typeof window.restoreMapEngineState === 'function') {
        window.restoreMapEngineState(initialMapStateInput.value);
    }

    // Initial setup
    setMode('pan');
    renumberStops();
    recalculateTimeBudget();
});
