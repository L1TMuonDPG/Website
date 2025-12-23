<?php
// --------------------------------------------------------------------
// CONFIGURATION & SETTINGS
// --------------------------------------------------------------------

// 1. EVENTS CONFIGURATION (ICS/iCal)
// Paste your Indico Category ics export link here.
// Example: https://indico.cern.ch/export/categ/2091.ics?from=-31d
$events_ical_url = "https://indico.cern.ch/category/2091/events.ics?user_token=165296_Fi1YuX-ZTsdUsz5Of3SegqsM0NP9PVZqs7skqxV677k"; // Leave empty to hide the events box, or paste URL to enable.

// 2. FILE SETTINGS
$plot_extensions = ["png", "pdf", "jpg", "jpeg", "gif", "svg", "PNG", "PDF", "JPG", "JPEG", "GIF", "SVG"];
$ignore_files = ['.', '..', 'index.php', '.htaccess', '.git', 'bin', 'tests', 'events.json', 'requirements_dev.txt'];
$base_path = __DIR__;
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$base_url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];

// --------------------------------------------------------------------
// HELPER: PARSE ICS FILES (Simple Implementation)
// --------------------------------------------------------------------
function get_upcoming_events($url, $limit = 3) {
    if (empty($url)) return [];
    
    // Set a timeout context
    $ctx = stream_context_create(['http' => ['timeout' => 3]]);
    $ics_data = @file_get_contents($url, false, $ctx);
    
    if (!$ics_data) return [];

    // Regex to find events
    preg_match_all('/BEGIN:VEVENT[\s\S]*?END:VEVENT/i', $ics_data, $events_raw);
    
    $events = [];
    $now = time();

    foreach ($events_raw[0] as $event_raw) {
        // Extract fields
        preg_match('/SUMMARY:(.*?)\r?\n/', $event_raw, $summary);
        preg_match('/DTSTART(?:\;.*?)?:(.*?)\r?\n/', $event_raw, $dtstart);
        preg_match('/DTEND(?:\;.*?)?:(.*?)\r?\n/', $event_raw, $dtend);
        preg_match('/DESCRIPTION:(.*?)\r?\n/', $event_raw, $desc);

        if (isset($dtstart[1])) {
            $time_str = $dtstart[1];
            // Handle UTC 'Z' or simple datetime
            $timestamp = strtotime($time_str);
            
            // Only show future events (or events from today)
            if ($timestamp >= $now - 86400) { 
                $events[] = [
                    'title' => isset($summary[1]) ? trim($summary[1]) : 'No Title',
                    'time' => $timestamp,
                    'desc' => isset($desc[1]) ? trim(str_replace('\n', "\n", $desc[1])) : ''
                ];
            }
        }
    }

    // Sort by date
    usort($events, function($a, $b) { return $a['time'] - $b['time']; });
    
    return array_slice($events, 0, $limit);
}

// --------------------------------------------------------------------
// ROUTER & LOGIC
// --------------------------------------------------------------------
$requested_path = isset($_GET['path']) ? trim($_GET['path'], '/') : '';
if (strpos($requested_path, '..') !== false) die("Invalid path.");
$full_path = realpath($base_path . ($requested_path ? '/' . $requested_path : ''));
$search_query = isset($_GET["search"]) ? $_GET["search"] : "";

// File Download Handler
if ($full_path && is_file($full_path)) {
    if (strpos($full_path, $base_path) !== 0) die("Access denied.");
    $ext = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));
    $mime = mime_content_type($full_path);
    $inline_extensions = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'pdf', 'txt', 'log', 'cxx', 'c', 'py', 'php'];

    if (in_array($ext, $inline_extensions)) {
        if ($ext == 'pdf') $mime = 'application/pdf';
        if ($ext == 'svg') $mime = 'image/svg+xml';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="'.basename($full_path).'"');
    } else {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($full_path).'"');
    }
    readfile($full_path);
    exit;
}

if (!$full_path || !is_dir($full_path)) die("Directory not found.");

// Directory Description Logic
function get_directory_description($subdir) {
    if ($subdir == "2025") {
        return '<h4>Plots for 2025</h4><p>Total certified: 115.65 <span>fb<sup>-1</sup></span>. <br>Includes Eras <code>2025C-G</code>, Combined <code>2025All</code>, and comparisons.</p>';
    } elseif ($subdir == "2024") {
        return '<h4>Plots for 2024</h4><p>Total certified: 109 <span>fb<sup>-1</sup></span>.</p>';
    } elseif ($subdir == "eff") {
        return '<h4>Efficiency plots</h4><p>L1T muon efficiency vs offline muon pT, &eta;, &phi;.</p>';
    }
    return "";
}

// Scan Content
$directories = [];
$nested_plots = []; 
$other_files = [];
$is_home_page = ($requested_path == '' || $requested_path == 'index.php');
$dir_desc_html = get_directory_description(basename($full_path));

$items = scandir($full_path);
foreach ($items as $item) {
    if ($item[0] === '.' || $item[0] === '_') continue;
    if (in_array($item, $ignore_files)) continue;
    
    $item_path = $full_path . '/' . $item;
    
    if (is_dir($item_path)) {
        $directories[] = $item;
    } else {
        $ext = pathinfo($item, PATHINFO_EXTENSION);
        $base_name = pathinfo($item, PATHINFO_FILENAME);
        if (!empty($search_query) && stripos($item, $search_query) === false) continue;

        if (in_array($ext, $plot_extensions)) {
            $nested_plots[$base_name][] = $ext;
        } else {
            $other_files[] = $item;
        }
    }
}
ksort($nested_plots); sort($directories); sort($other_files);

function format_size($bytes) {
    $u = ['B', 'KB', 'MB', 'GB'];
    for ($i=0; $bytes >= 1024 && $i < 3; $i++) $bytes /= 1024;
    return round($bytes, 2) . ' ' . $u[$i];
}

// Fetch Events only on homepage
$upcoming_events = [];
if ($is_home_page && !empty($events_ical_url)) {
    $upcoming_events = get_upcoming_events($events_ical_url);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Muon DPG PlotBrowser</title>
    
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        /* COLOR PALETTE CONFIGURATION */
        :root {
            --folder-bg: #e9ecef;
            --folder-color: #495057;
            /* CMS Light Mode Blue: Deep, Professional */
            --cms-blue: #005eb8; 
            --cms-border: #ced4da;
        }
        [data-bs-theme="dark"] {
            --folder-bg: #2b3035;
            --folder-color: #adb5bd;
            /* CMS Dark Mode Blue: Bright, Cyan-tinted for contrast */
            --cms-blue: #3ea6ff; 
            --cms-border: #495057;
        }
        
        body { padding-bottom: 60px; transition: background-color 0.3s, color 0.3s; }
        
        /* Navbar Tweaks */
        .navbar-brand img { height: 40px; width: auto; margin-right: 10px; }
        .breadcrumb-item a { text-decoration: none; color: var(--cms-blue); font-weight: 600; }
        
        /* Card Styling */
        .card { transition: border-color 0.2s, box-shadow 0.2s, transform 0.2s; border: 1px solid var(--cms-border); }
        .card-hover:hover { transform: translateY(-3px); border-color: var(--cms-blue); }
        [data-bs-theme="light"] .card-hover:hover { box-shadow: 0 8px 16px rgba(0,94,184,0.15); }
        [data-bs-theme="dark"] .card-hover:hover { box-shadow: 0 8px 16px rgba(62,166,255,0.15); }
        
        /* Specific Elements using the Theme Color */
        .text-primary-custom { color: var(--cms-blue) !important; }
        .border-primary-custom { border-color: var(--cms-blue) !important; }
        .btn-primary-custom { background-color: var(--cms-blue); border-color: var(--cms-blue); color: #fff; }
        .btn-primary-custom:hover { opacity: 0.9; color: #fff; }
        .folder-icon { font-size: 2.5rem; color: #ffc107; /* Keeping folders yellow */ }

        /* Compare Mode Styles */
        body.compare-active .card-hover { cursor: crosshair; }
        .card.selected { border: 3px solid var(--cms-blue) !important; box-shadow: 0 0 15px rgba(0, 94, 184, 0.4) !important; transform: scale(1.02); }
        .selection-indicator { display: none; position: absolute; top: 10px; right: 10px; font-size: 1.5rem; color: var(--cms-blue); z-index: 20; background: rgba(255,255,255,0.9); border-radius: 50%; width: 30px; height: 30px; align-items: center; justify-content: center; }
        .card.selected .selection-indicator { display: flex; }

        .img-thumb { height: 300px; object-fit: contain; background: var(--bs-card-bg); border-bottom: 1px solid var(--cms-border); padding: 5px; }
        .folder-card { background: var(--folder-bg); color: var(--folder-color); border: none !important; }
        
        /* Floating Actions & Compare Bar */
        #floating-actions { position: fixed; bottom: 20px; right: 20px; z-index: 1000; background: var(--bs-body-bg); border: 1px solid var(--cms-border); padding: 8px; border-radius: 50px; box-shadow: 0 4px 10px rgba(0,0,0,0.2); display: flex; gap: 10px; }
        #compare-bar { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%) translateY(100px); z-index: 1050; background: var(--bs-body-bg); padding: 10px 20px; border-radius: 50px; box-shadow: 0 5px 20px rgba(0,0,0,0.3); border: 1px solid var(--cms-border); transition: transform 0.3s; display: flex; align-items: center; gap: 15px; }
        #compare-bar.visible { transform: translateX(-50%) translateY(0); }

        /* Lightbox & Compare Modals */
        #lightboxModal .modal-dialog { max-width: 95vw; height: 95vh; margin: auto; display: flex; align-items: center; }
        #lightboxModal img { max-height: 90vh; max-width: 100%; object-fit: contain; margin: 0 auto; display: block; }
        .lightbox-nav-btn { position: absolute; top: 50%; transform: translateY(-50%); font-size: 3rem; color: white; background: rgba(0,0,0,0.3); border: none; padding: 10px; cursor: pointer; border-radius: 5px; z-index: 1060; }
        .lightbox-nav-btn:hover { background: rgba(0,0,0,0.6); }
        .lightbox-prev { left: 20px; } .lightbox-next { right: 20px; }
        .lightbox-close { position: absolute; top: 10px; right: 20px; font-size: 2rem; color: white; cursor: pointer; z-index: 1060; }
        
        #compareModal .modal-dialog { max-width: 98vw; height: 95vh; }
        .compare-pane { height: 100%; overflow: auto; padding: 10px; text-align: center; border-right: 1px solid var(--cms-border); }
        .compare-pane img { max-width: 100%; height: auto; }

        .copy-btn { cursor: pointer; opacity: 0.5; transition: opacity 0.2s; border: none; background: transparent; padding: 0 5px; }
        .copy-btn:hover { opacity: 1; }
        .badge-new { position: absolute; top: 10px; left: 10px; z-index: 10; box-shadow: 0 2px 5px rgba(0,0,0,0.2); font-size: 0.7rem; }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand bg-body-tertiary shadow-sm sticky-top mb-4 py-2">
        <div class="container-fluid px-4">
            
            <nav aria-label="breadcrumb" class="me-auto">
                <ol class="breadcrumb mb-0 align-items-center">
                    <li class="breadcrumb-item">
                        <a href="?path="><i class="bi bi-house-door-fill"></i> Home</a>
                    </li>
                    <?php
                    $accumulated = '';
                    if($requested_path):
                        foreach(explode('/', $requested_path) as $part):
                            $accumulated .= $part . '/';
                            ?>
                            <li class="breadcrumb-item">
                                <a href="?path=<?= urlencode(trim($accumulated, '/')) ?>"><?= htmlspecialchars($part) ?></a>
                            </li>
                    <?php endforeach; endif; ?>
                </ol>
            </nav>

            <div class="d-flex align-items-center gap-2">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-body"><i class="bi bi-search"></i></span>
                    <input id="liveSearchInput" class="form-control" type="search" placeholder="Filter..." value="<?= htmlspecialchars($search_query) ?>">
                </div>
                
                <div class="vr mx-1"></div>

                <button class="btn btn-sm btn-outline-secondary rounded-circle" id="compareToggle" title="Compare Mode">
                    <i class="bi bi-arrows-angle-contract" id="compareIcon"></i>
                </button>
                <button class="btn btn-sm btn-outline-secondary rounded-circle" id="themeToggle" title="Toggle Dark/Light Mode">
                    <i class="bi bi-circle-half" id="themeIcon"></i>
                </button>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-4" id="mainContainer">

        <?php if ($is_home_page): ?>
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="card h-100 shadow-sm border-primary-custom border-start border-4">
                    <div class="card-body">
                        <h2 class="card-title text-primary-custom mb-3">L1T Muon DPG Run 3 Plots Repository</h2>
                        
                        <h5 class="fw-bold"><i class="bi bi-people-fill me-2"></i>Team Members</h5>
                        <ul class="mb-4">
                            <li>Nikolaos Plastiras, Muon DPG Run 3 convener, National and Kapodistrian University of Athens</li>
                            <li>Panagiotis Katris, PhD student, National and Kapodistrian University of Athens</li>
                        </ul>

                        <h5 class="fw-bold"><i class="bi bi-journal-text me-2"></i>Publications</h5>
                        <ul class="mb-4">
                            <li><a href="https://cds.cern.ch/record/2917885" target="_blank">Level-1 Trigger Performance in 2024 (13.6 TeV)</a></li>
                            <li><a href="https://cds.cern.ch/record/2868794?ln=en" target="_blank">Level-1 Muon Trigger Performance (2023)</a></li>
                            <li><a href="https://cds.cern.ch/record/2868797" target="_blank">Displaced BMTF Efficiency (2023)</a></li>
                        </ul>

                         <h5 class="fw-bold"><i class="bi bi-easel me-2"></i>Workshops</h5>
                        <ul>
                            <li><a href="https://indico.cern.ch/event/1497887/contributions/6402480/attachments/3043685/5377433/Trigger_Workshop_Muons.pdf" target="_blank">Level-1 Trigger Workshop @ Oviedo, 2025 (Nikolaos)</a></li>
                            <li><a href="https://indico.cern.ch/event/1288569/contributions/5490551/attachments/2711183/4709072/Muons%20L1T%20workshop%20athens.pdf" target="_blank">Level-1 Trigger Workshop @ Athens, 2023 (Ioannis)</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 mt-3 mt-lg-0">
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-body fw-bold d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-calendar-event me-2"></i>Upcoming Events</span>
                        <?php if(!empty($events_ical_url)): ?><span class="badge bg-secondary" style="font-size:0.6rem">Live</span><?php endif; ?>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php if (empty($events_ical_url)): ?>
                             <div class="list-group-item text-muted small text-center py-3">
                                 No calendar linked.
                             </div>
                        <?php elseif (empty($upcoming_events)): ?>
                            <div class="list-group-item text-muted small text-center py-3">
                                No upcoming events found.
                            </div>
                        <?php else: ?>
                            <?php foreach($upcoming_events as $event): ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1 text-primary-custom text-truncate"><?= htmlspecialchars($event['title']) ?></h6>
                                    <small class="text-muted text-nowrap"><?= date('M j', $event['time']) ?></small>
                                </div>
                                <p class="mb-1 small"><?= date('H:i', $event['time']) ?> (Local)</p>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer text-center small">
                         <a href="https://indico.cern.ch/category/2091/overview?period=day" target="_blank">View Indico Calendar</a>
                    </div>
                </div>

                <div class="card shadow-sm">
                     <div class="card-header bg-body fw-bold"><i class="bi bi-link-45deg me-2"></i>External Resources</div>
                     <div class="list-group list-group-flush">
                        <a href="https://twiki.cern.ch/twiki/bin/view/CMS/L1TriggerDPG" target="_blank" class="list-group-item list-group-item-action"><i class="bi bi-box-arrow-up-right me-2"></i>DPG TWiki</a>
                        <a href="https://twiki.cern.ch/twiki/bin/view/CMSPublic/L1TriggerDPGResults" target="_blank" class="list-group-item list-group-item-action"><i class="bi bi-bar-chart-fill me-2"></i>Level-1 Trigger Public Performance Results</a>
                        <a href="https://twiki.cern.ch/twiki/bin/viewauth/CMS/L1KnownIssues#Muons" target="_blank" class="list-group-item list-group-item-action"><i class="bi bi-exclamation-triangle-fill me-2"></i>L1 Known Issues</a>
                        <a href="https://htmlpreview.github.io/?https://github.com/cms-l1-dpg/L1MenuRun3/blob/master/development/L1Menu_Collisions2025_v1_3_0/L1Menu_Collisions2025_v1_3_0.html" target="_blank" class="list-group-item list-group-item-action"><i class="bi bi-menu-up me-2"></i>L1 Menu</a>
                        <a href="https://github.com/L1TMuonDPG" target="_blank" class="list-group-item list-group-item-action"><i class="bi bi-github me-2"></i>L1T Muon DPG Repository</a>
                     </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($dir_desc_html)): ?>
        <div class="alert alert-secondary shadow-sm mb-4 border-start border-4 border-secondary">
            <?= $dir_desc_html ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($directories)): ?>
        <div id="section-dirs">
            <h6 class="text-muted text-uppercase small fw-bold mb-3">Directories</h6>
            <div class="row row-cols-2 row-cols-md-4 row-cols-lg-6 g-3 mb-5" id="container-dirs">
                <?php foreach($directories as $dir): 
                    $dir_link = "?path=" . urlencode(trim($requested_path . '/' . $dir, '/'));
                ?>
                <div class="col filterable-item" data-name="<?= strtolower($dir) ?>">
                    <a href="<?= $dir_link ?>" class="text-decoration-none">
                        <div class="card h-100 folder-card card-hover text-center py-3">
                            <i class="bi bi-folder-fill folder-icon mb-2"></i>
                            <div class="small fw-bold text-truncate px-2"><?= $dir ?></div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($nested_plots)): ?>
        <div id="section-plots">
            <h6 class="text-muted text-uppercase small fw-bold mb-3">Plots <span class="badge bg-secondary opacity-50 rounded-pill ms-1"><?= count($nested_plots) ?></span></h6>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-4 g-4 mb-5" id="container-plots">
                <?php foreach($nested_plots as $base_name => $exts): 
                    $unique_exts = array_unique($exts);
                    
                    $main_ext = 'png';
                    foreach(['png', 'jpg', 'jpeg', 'gif', 'svg'] as $e) {
                        if (in_array($e, $unique_exts)) { $main_ext = $e; break; }
                    }
                    if (!in_array($main_ext, $unique_exts) && in_array('pdf', $unique_exts)) $main_ext = 'pdf';

                    $img_display_path = trim($full_path . '/' . $base_name . '.' . $main_ext);
                    $img_url_param = trim($requested_path . '/' . $base_name . '.' . $main_ext, '/');
                    $full_img_url = $base_url . "?path=" . urlencode($img_url_param);
                    
                    $mtime = file_exists($img_display_path) ? filemtime($img_display_path) : 0;
                    $is_new = (time() - $mtime < 86400 * 3); 
                    $display_title = $base_name;
                ?>
                <div class="col filterable-item" data-name="<?= strtolower($display_title) ?>">
                    <div class="card h-100 shadow-sm card-hover position-relative plot-card" data-img="<?= $full_img_url ?>" data-title="<?= $display_title ?>">
                        <div class="selection-indicator"><i class="bi bi-check-lg"></i></div>
                        
                        <?php if($is_new): ?>
                            <span class="badge bg-warning text-dark badge-new">NEW</span>
                        <?php endif; ?>
                        
                        <a href="#" class="lightbox-trigger d-flex align-items-center justify-content-center" data-src="<?= $full_img_url ?>" data-title="<?= $display_title ?>">
                            <?php if($main_ext == 'pdf'): ?>
                                <div class="img-thumb d-flex align-items-center justify-content-center w-100 text-danger display-1"><i class="bi bi-file-pdf"></i></div>
                            <?php else: ?>
                                <img src="<?= $full_img_url ?>" class="card-img-top img-thumb" loading="lazy">
                            <?php endif; ?>
                        </a>
                        
                        <div class="card-body p-2 text-center">
                            <div class="d-flex align-items-center mb-2 justify-content-center" style="width: 100%;">
                                <div class="fw-bold text-truncate small flex-grow-1" title="<?= $display_title ?>">
                                    <?= $display_title ?>
                                </div>
                                <button class="copy-btn text-muted" onclick="copyToClipboard(this, '<?= $full_img_url ?>')" title="Copy Link">
                                    <i class="bi bi-link-45deg"></i>
                                </button>
                            </div>
                            
                            <div class="d-flex flex-wrap gap-1 justify-content-center">
                                <?php foreach($unique_exts as $ext): 
                                    $file_link = "?path=" . urlencode(trim($requested_path . '/' . $base_name . '.' . $ext, '/'));
                                    // Use custom color class for png/pdf to match theme
                                    $bg_class = ($ext == 'png') ? 'bg-primary' : (($ext == 'pdf') ? 'bg-info' : 'bg-secondary');
                                ?>
                                <a href="<?= $file_link ?>" target="_blank" class="badge <?= $bg_class ?> text-decoration-none"><?= $ext ?></a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($other_files)): ?>
        <div id="section-files">
            <h6 class="text-muted text-uppercase small fw-bold mb-3">Other Files</h6>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-4 row-cols-lg-5 g-3 mb-5" id="container-files">
                <?php foreach($other_files as $file): 
                    $file_link = "?path=" . urlencode(trim($requested_path . '/' . $file, '/'));
                    $size = format_size(filesize($full_path . '/' . $file));
                ?>
                <div class="col filterable-item" data-name="<?= strtolower($file) ?>">
                    <div class="card h-100 shadow-sm card-hover border-0">
                        <div class="card-body d-flex align-items-center p-2">
                            <i class="bi bi-file-earmark-text fs-4 text-secondary me-2"></i>
                            <div class="overflow-hidden">
                                <a href="<?= $file_link ?>" target="_blank" class="text-reset text-decoration-none fw-bold small d-block text-truncate"><?= $file ?></a>
                                <span class="text-muted" style="font-size: 0.75rem;"><?= $size ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if(empty($directories) && empty($nested_plots) && empty($other_files)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-folder2-open display-1"></i>
                <p class="mt-3">This directory is empty.</p>
            </div>
        <?php endif; ?>

    </div>

    <div id="floating-actions">
        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill border-0" data-bs-toggle="modal" data-bs-target="#contactModal">
            <i class="bi bi-envelope-fill me-1"></i> Contact
        </button>
        <button type="button" class="btn btn-primary-custom btn-sm rounded-circle" onclick="window.scrollTo({top: 0, behavior: 'smooth'});" title="Back to top">
            <i class="bi bi-arrow-up"></i>
        </button>
    </div>

    <div id="compare-bar">
        <span class="text-muted small">2 items selected</span>
        <button class="btn btn-primary-custom btn-sm rounded-pill" id="btn-do-compare">Compare</button>
        <button class="btn btn-outline-secondary btn-sm rounded-circle" id="btn-clear-compare"><i class="bi bi-x"></i></button>
    </div>

    <div class="modal fade" id="contactModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title">Contact Us</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <p class="mb-1">Nikolaos Plastiras - <a href="mailto:nikolaos.plastiras@cern.ch">nikolaos.plastiras@cern.ch</a></p>
                    <p>Panagiotis Katris - <a href="mailto:panagiotis.katris@cern.ch">panagiotis.katris@cern.ch</a></p>
                    <hr>
                    <p class="small text-muted mb-0"><a href="https://gitlab.cern.ch/cms-analysis/general/php-plots" target="_blank">Plot Browser based on PHP Plots</a></p>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade p-0" id="lightboxModal" tabindex="-1" aria-hidden="true">
        <span class="lightbox-close" data-bs-dismiss="modal">&times;</span>
        <button class="lightbox-nav-btn lightbox-prev"><i class="bi bi-chevron-left"></i></button>
        <button class="lightbox-nav-btn lightbox-next"><i class="bi bi-chevron-right"></i></button>
        <div class="modal-dialog">
            <div class="modal-content bg-transparent border-0">
                <img id="lightboxImage" src="" alt="">
                <div class="text-center text-white mt-2 lightbox-caption" id="lightboxCaption" style="text-shadow: 1px 1px 4px black;"></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="compareModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content h-75">
                <div class="modal-header py-2">
                    <h5 class="modal-title fs-6">Side-by-Side Comparison</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="row h-100 g-0">
                        <div class="col-6 compare-pane" id="compare-left">
                            <h6 class="text-muted small border-bottom pb-2 mb-2 title text-truncate"></h6>
                            <img src="" alt="">
                        </div>
                        <div class="col-6 compare-pane" id="compare-right">
                            <h6 class="text-muted small border-bottom pb-2 mb-2 title text-truncate"></h6>
                            <img src="" alt="">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 1. DARK MODE LOGIC
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const htmlElement = document.documentElement;
        
        const currentTheme = htmlElement.getAttribute('data-bs-theme');
        themeIcon.className = currentTheme === 'light' ? 'bi bi-moon-fill' : 'bi bi-sun-fill';

        themeToggle.addEventListener('click', () => {
            const currentTheme = htmlElement.getAttribute('data-bs-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            htmlElement.setAttribute('data-bs-theme', newTheme);
            themeIcon.className = newTheme === 'light' ? 'bi bi-moon-fill' : 'bi bi-sun-fill';
            localStorage.setItem('theme', newTheme);
        });

        // 2. LIVE SEARCH LOGIC
        const searchInput = document.getElementById('liveSearchInput');
        const filterItems = document.querySelectorAll('.filterable-item');

        searchInput.addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase().trim();
            filterItems.forEach(item => {
                const name = item.getAttribute('data-name');
                if (term === "" || name.includes(term)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
            checkSectionVisibility('container-dirs', 'section-dirs');
            checkSectionVisibility('container-plots', 'section-plots');
            checkSectionVisibility('container-files', 'section-files');
        });

        function checkSectionVisibility(containerId, sectionId) {
            const container = document.getElementById(containerId);
            const section = document.getElementById(sectionId);
            if (!container || !section) return;
            const hasVisible = Array.from(container.children).some(child => child.style.display !== 'none');
            section.style.display = hasVisible ? '' : 'none';
        }

        // 3. COPY LINK LOGIC
        function copyToClipboard(btn, text) {
            navigator.clipboard.writeText(text).then(() => {
                const icon = btn.querySelector('i');
                const originalClass = icon.className;
                icon.className = 'bi bi-check-lg text-success';
                setTimeout(() => { icon.className = originalClass; }, 1500);
            });
        }

        // 4. COMPARE LOGIC
        let compareMode = false;
        let selectedPlots = [];
        const compareToggle = document.getElementById('compareToggle');
        const compareBar = document.getElementById('compare-bar');
        const compareModal = new bootstrap.Modal(document.getElementById('compareModal'));

        compareToggle.addEventListener('click', () => {
            compareMode = !compareMode;
            document.body.classList.toggle('compare-active', compareMode);
            compareToggle.classList.toggle('btn-primary-custom', compareMode);
            compareToggle.classList.toggle('btn-outline-secondary', !compareMode);
            if(!compareMode) clearComparison();
        });

        function clearComparison() {
            selectedPlots = [];
            document.querySelectorAll('.plot-card.selected').forEach(card => card.classList.remove('selected'));
            compareBar.classList.remove('visible');
        }

        document.querySelectorAll('.plot-card').forEach(card => {
            card.addEventListener('click', (e) => {
                if (compareMode) {
                    e.preventDefault(); e.stopPropagation();
                    const img = card.dataset.img;
                    const title = card.dataset.title;
                    const index = selectedPlots.findIndex(p => p.img === img);

                    if (index > -1) {
                        selectedPlots.splice(index, 1);
                        card.classList.remove('selected');
                    } else {
                        if (selectedPlots.length < 2) {
                            selectedPlots.push({img, title});
                            card.classList.add('selected');
                        } else {
                            const removed = selectedPlots.shift();
                            const oldCard = document.querySelector(`.plot-card[data-img="${CSS.escape(removed.img)}"]`);
                            if(oldCard) oldCard.classList.remove('selected');
                            selectedPlots.push({img, title});
                            card.classList.add('selected');
                        }
                    }
                    compareBar.classList.toggle('visible', selectedPlots.length === 2);
                }
            });
        });

        document.getElementById('btn-clear-compare').addEventListener('click', clearComparison);
        
        document.getElementById('btn-do-compare').addEventListener('click', () => {
            if (selectedPlots.length !== 2) return;
            document.getElementById('compare-left').querySelector('img').src = selectedPlots[0].img;
            document.getElementById('compare-left').querySelector('.title').textContent = selectedPlots[0].title;
            document.getElementById('compare-right').querySelector('img').src = selectedPlots[1].img;
            document.getElementById('compare-right').querySelector('.title').textContent = selectedPlots[1].title;
            compareModal.show();
        });

        // 5. LIGHTBOX LOGIC
        const lightboxModal = new bootstrap.Modal(document.getElementById('lightboxModal'));
        const lightboxImage = document.getElementById('lightboxImage');
        const lightboxCaption = document.getElementById('lightboxCaption');
        let galleryImages = []; let currentIndex = 0;

        document.querySelectorAll('.lightbox-trigger').forEach((trigger, index) => {
            galleryImages.push({
                src: trigger.getAttribute('data-src'),
                title: trigger.getAttribute('data-title')
            });
            trigger.addEventListener('click', (e) => {
                if (compareMode) return;
                e.preventDefault();
                currentIndex = index;
                updateLightbox();
                lightboxModal.show();
            });
        });

        function updateLightbox() {
            if (galleryImages.length === 0) return;
            const item = galleryImages[currentIndex];
            lightboxImage.src = item.src;
            lightboxCaption.textContent = item.title + ` (${currentIndex + 1}/${galleryImages.length})`;
        }
        function showNext() { currentIndex = (currentIndex + 1) % galleryImages.length; updateLightbox(); }
        function showPrev() { currentIndex = (currentIndex - 1 + galleryImages.length) % galleryImages.length; updateLightbox(); }

        document.querySelector('.lightbox-next').addEventListener('click', (e) => {e.stopPropagation(); showNext();});
        document.querySelector('.lightbox-prev').addEventListener('click', (e) => {e.stopPropagation(); showPrev();});
        
        document.getElementById('lightboxModal').addEventListener('click', (e) => {
            if (e.target.tagName !== 'IMG' && !e.target.closest('.lightbox-nav-btn')) {
                lightboxModal.hide();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (document.getElementById('lightboxModal').classList.contains('show')) {
                if (e.key === 'ArrowRight') showNext();
                if (e.key === 'ArrowLeft') showPrev();
            }
        });
    </script>
    
    <script>
      var _paq = window._paq = window._paq || [];
      _paq.push(['trackPageView']);
      _paq.push(['enableLinkTracking']);
      (function() {
        var u="https://webanalytics.web.cern.ch/";
        _paq.push(['setTrackerUrl', u+'matomo.php']);
        _paq.push(['setSiteId', '793']);
        var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
        g.async=true; g.src=u+'matomo.js'; s.parentNode.insertBefore(g,s);
      })();
    </script>
</body>
</html>