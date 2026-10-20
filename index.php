<?php
declare(strict_types=1);

$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0755, true);
}

// Handle deletion request
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $deleteId = basename((string)($_POST['id'] ?? ''));
    $targetFile = $dataDir . '/' . $deleteId . '.json';
    if (!empty($deleteId) && file_exists($targetFile)) {
        unlink($targetFile);
        header('Location: index.php?msg=deleted');
        exit;
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $message = 'Tour record deleted successfully.';
} elseif (isset($_GET['msg']) && $_GET['msg'] === 'not_found') {
    $message = 'The requested tour record could not be found.';
}

// Load and index all JSON files
$files = glob($dataDir . '/*.json') ?: [];
$tours = [];
$totalStopsAllTours = 0;
$totalMinutesAllTours = 0;

foreach ($files as $file) {
    $raw = file_get_contents($file);
    if ($raw === false) continue;
    $record = json_decode($raw, true);
    if (!is_array($record)) continue;

    $stopCount = count($record['stops'] ?? []);
    $totalStopsAllTours += $stopCount;

    $tourMins = 0;
    foreach (($record['stops'] ?? []) as $st) {
        $tourMins += (int)($st['minutes'] ?? 0);
    }
    $totalMinutesAllTours += $tourMins;

    $record['file_mtime'] = filemtime($file);
    $record['stop_count'] = $stopCount;
    $record['total_stop_mins'] = $tourMins;
    $tours[] = $record;
}

// Sort order handling (created, modified, or tour_date)
$sortBy = (string)($_GET['sort'] ?? 'created');

switch ($sortBy) {
    case 'modified':
        usort($tours, function ($a, $b) {
            $modA = !empty($a['updated_at']) ? (string)$a['updated_at'] : (!empty($a['file_mtime']) ? date('c', (int)$a['file_mtime']) : '');
            $modB = !empty($b['updated_at']) ? (string)$b['updated_at'] : (!empty($b['file_mtime']) ? date('c', (int)$b['file_mtime']) : '');
            return $modB <=> $modA;
        });
        break;

    case 'tour_date':
        usort($tours, function ($a, $b) {
            $dateA = (string)($a['tour_date'] ?? '');
            $dateB = (string)($b['tour_date'] ?? '');
            if ($dateA === '' && $dateB !== '') return 1;
            if ($dateB === '' && $dateA !== '') return -1;
            return $dateB <=> $dateA;
        });
        break;

    case 'created':
    default:
        $sortBy = 'created';
        usort($tours, fn($a, $b) => ($b['created_at'] ?? '') <=> ($a['created_at'] ?? ''));
        break;
}

$totalTours = count($tours);
$avgMinutes = $totalTours > 0 ? (int)round($totalMinutesAllTours / $totalTours) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facility Tour Dashboard – Saved Records</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .record-card-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .badge-draft {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: #64748b;
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            padding: 5px 12px;
            border-radius: 4px;
            user-select: none;
            line-height: 1.4;
        }
        .mini-badge-draft-card {
            background-color: #e2e8f0;
            color: #475569;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 4px;
            text-transform: uppercase;
            border: 1px solid #cbd5e1;
        }
        .dashboard-controls {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .sort-select {
            padding: 7px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background-color: #ffffff;
            font-size: 13px;
            color: #374151;
            outline: none;
            cursor: pointer;
            height: 38px;
            box-sizing: border-box;
        }
        .sort-select:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Dashboard Header -->
        <header class="app-header flex-between">
            <div class="header-content">
                <h1>Facility Tour Dashboard</h1>
                <p class="subtitle">Review, search, reprint, edit, and manage previously generated tour agendas</p>
            </div>
            <div class="header-nav">
                <a href="create.php" class="btn btn-primary">+ Create New Tour</a>
            </div>
        </header>

        <?php if (!empty($message)): ?>
            <div class="alert-box alert-success">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <!-- Summary Stats Panel -->
        <div class="stats-panel-grid">
            <div class="stat-card">
                <span class="stat-title">Total Saved Tours</span>
                <span class="stat-number"><?= $totalTours ?></span>
            </div>
            <div class="stat-card">
                <span class="stat-title">Total Stops Logged</span>
                <span class="stat-number"><?= $totalStopsAllTours ?></span>
            </div>
            <div class="stat-card">
                <span class="stat-title">Avg Stop Time / Tour</span>
                <span class="stat-number"><?= $avgMinutes ?> <small style="font-size: 14px; font-weight: normal;">mins</small></span>
            </div>
        </div>

        <!-- Filter & Search Card -->
        <section class="card">
            <div class="card-header flex-between">
                <h2>Saved Tour Agendas</h2>
                <div class="dashboard-controls">
                    <div class="search-box">
                        <input type="text" id="dashboardSearch" placeholder="Search by title, coordinator, or facility...">
                    </div>
                    <div class="sort-box">
                        <select id="sortSelect" class="sort-select">
                            <option value="created" <?= $sortBy === 'created' ? 'selected' : '' ?>>Sort: Created Date</option>
                            <option value="modified" <?= $sortBy === 'modified' ? 'selected' : '' ?>>Sort: Modified Date</option>
                            <option value="tour_date" <?= $sortBy === 'tour_date' ? 'selected' : '' ?>>Sort: Tour Date</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($tours)): ?>
                    <div class="empty-state">
                        <p>No saved tour reports found yet.</p>
                        <a href="create.php" class="btn btn-primary btn-sm" style="margin-top: 10px;">Build Your First Tour</a>
                    </div>
                <?php else: ?>
                    <div class="tour-records-grid" id="recordsGrid">
                        <?php foreach ($tours as $t): 
                            $id = htmlspecialchars($t['id'] ?? '', ENT_QUOTES, 'UTF-8');
                            $title = htmlspecialchars($t['tour_title'] ?? 'Untitled Tour', ENT_QUOTES, 'UTF-8');
                            $date = htmlspecialchars($t['tour_date'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
                            $timeWindow = htmlspecialchars(($t['time_start'] ?? '') . ' – ' . ($t['time_end'] ?? ''), ENT_QUOTES, 'UTF-8');
                            $coord = htmlspecialchars($t['coordinator_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
                            $stopCount = (int)$t['stop_count'];
                            $stopMins = (int)$t['total_stop_mins'];
                            $hasMap = !empty($t['route_image_data']);
                            $isDraft = (isset($t['status']) && strtolower((string)$t['status']) === 'draft');

                            $modifiedTimestamp = !empty($t['updated_at']) 
                                ? (string)$t['updated_at'] 
                                : (!empty($t['file_mtime']) ? date('c', (int)$t['file_mtime']) : '');

                            $facilityNames = [];
                            foreach (($t['stops'] ?? []) as $st) {
                                if (!empty($st['name'])) $facilityNames[] = $st['name'];
                            }
                            $searchableText = strtolower($title . ' ' . $coord . ' ' . ($isDraft ? 'draft' : '') . ' ' . implode(' ', $facilityNames));
                        ?>
                            <div class="tour-record-card" 
                                 data-search="<?= htmlspecialchars($searchableText, ENT_QUOTES, 'UTF-8') ?>"
                                 data-created="<?= htmlspecialchars($t['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                 data-modified="<?= htmlspecialchars($modifiedTimestamp, ENT_QUOTES, 'UTF-8') ?>"
                                 data-tour-date="<?= htmlspecialchars($t['tour_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <div class="record-card-header">
                                    <div>
                                        <h3 class="record-title"><?= $title ?></h3>
                                        <div class="record-date-badge"><?= $date ?> &bull; <?= $timeWindow ?></div>
                                    </div>
                                    <div style="display: flex; gap: 6px; align-items: center;">
                                        <?php if ($isDraft): ?>
                                            <span class="mini-badge-draft-card">Draft</span>
                                        <?php endif; ?>
                                        <?php if ($hasMap): ?>
                                            <span class="mini-badge-map" title="Includes Vector Route Map">&#128506; Route Map</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="record-meta-list">
                                    <div><strong>Coordinator:</strong> <?= $coord ?></div>
                                    <div><strong>Stops:</strong> <?= $stopCount ?> facilities (<?= $stopMins ?> mins total)</div>
                                    <?php if (!empty($facilityNames)): ?>
                                        <div class="record-facilities">
                                            <strong>Key Stops:</strong> <?= htmlspecialchars(implode(', ', array_slice($facilityNames, 0, 3)), ENT_QUOTES, 'UTF-8') ?><?= count($facilityNames) > 3 ? '...' : '' ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="record-card-actions">
                                    <a href="create.php?id=<?= $id ?>" class="btn btn-primary btn-sm">Edit Tour</a>
                                    
                                    <?php if ($isDraft): ?>
                                        <span class="badge-draft">Draft</span>
                                    <?php else: ?>
                                        <a href="generate.php?id=<?= $id ?>" class="btn btn-secondary btn-sm">View &amp; Print Report</a>
                                    <?php endif; ?>

                                    <form method="POST" action="index.php" onsubmit="return confirm('Permanently delete this tour record?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $id ?>">
                                        <button type="submit" class="btn btn-icon btn-remove" title="Delete Tour">&times;</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <script>
        const searchInput = document.getElementById('dashboardSearch');
        const sortSelect = document.getElementById('sortSelect');
        const recordsGrid = document.getElementById('recordsGrid');

        // Dynamic client-side search
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                const query = e.target.value.toLowerCase().trim();
                const cards = document.querySelectorAll('.tour-record-card');
                cards.forEach(card => {
                    const text = card.getAttribute('data-search') || '';
                    if (query === '' || text.includes(query)) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        }

        // Dynamic instant reordering without page reload
        function sortCards(criteria) {
            if (!recordsGrid) return;
            const cardsArray = Array.from(recordsGrid.querySelectorAll('.tour-record-card'));

            cardsArray.sort((a, b) => {
                let valA = '';
                let valB = '';

                if (criteria === 'modified') {
                    valA = a.getAttribute('data-modified') || '';
                    valB = b.getAttribute('data-modified') || '';
                } else if (criteria === 'tour_date') {
                    valA = a.getAttribute('data-tour-date') || '';
                    valB = b.getAttribute('data-tour-date') || '';
                    if (!valA && valB) return 1;
                    if (!valB && valA) return -1;
                } else {
                    valA = a.getAttribute('data-created') || '';
                    valB = b.getAttribute('data-created') || '';
                }

                return valB.localeCompare(valA);
            });

            cardsArray.forEach(card => recordsGrid.appendChild(card));

            // Silently persist sort state in the browser URL
            const url = new URL(window.location.href);
            url.searchParams.set('sort', criteria);
            window.history.replaceState({}, '', url);
        }

        if (sortSelect) {
            sortSelect.addEventListener('change', (e) => {
                sortCards(e.target.value);
            });
        }
    </script>
</body>
</html>
