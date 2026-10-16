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

// Sort newest first
usort($tours, fn($a, $b) => ($b['created_at'] ?? '') <=> ($a['created_at'] ?? ''));

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
                <div class="search-box">
                    <input type="text" id="dashboardSearch" placeholder="Search by title, coordinator, or facility...">
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

                            $facilityNames = [];
                            foreach (($t['stops'] ?? []) as $st) {
                                if (!empty($st['name'])) $facilityNames[] = $st['name'];
                            }
                            $searchableText = strtolower($title . ' ' . $coord . ' ' . implode(' ', $facilityNames));
                        ?>
                            <div class="tour-record-card" data-search="<?= htmlspecialchars($searchableText, ENT_QUOTES, 'UTF-8') ?>">
                                <div class="record-card-header">
                                    <div>
                                        <h3 class="record-title"><?= $title ?></h3>
                                        <div class="record-date-badge"><?= $date ?> &bull; <?= $timeWindow ?></div>
                                    </div>
                                    <?php if ($hasMap): ?>
                                        <span class="mini-badge-map" title="Includes Vector Route Map">&#128506; Route Map</span>
                                    <?php endif; ?>
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
                                    <a href="generate.php?id=<?= $id ?>" class="btn btn-secondary btn-sm">View & Print Report</a>
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
        const cards = document.querySelectorAll('.tour-record-card');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                const query = e.target.value.toLowerCase().trim();
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
    </script>
</body>
</html>
