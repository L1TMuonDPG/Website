<?php
// --------------------------------------------------------------------
// CONFIGURATION & SETTINGS
// --------------------------------------------------------------------

// 1. EVENTS CONFIGURATION (ICS/iCal)
// Paste your Indico Category ics export link here.
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
    // Style settings
    $title_class = "text-primary-custom mb-2 desc-title"; 
    $text_class = "mb-3 desc-text"; 
    
    // 1. STANDARD WRAPPER (For eff_ directories)
    $wrap = function($title, $text) use ($title_class, $text_class) {
        return "<div>
                    <h2 class='$title_class'>$title</h2>
                    <div class='$text_class'>$text</div>
                </div><hr class='my-4 opacity-10'>";
    };

   // 2. ERA WRAPPER (Clean Layout: Adaptive text colors)
    $wrap_era = function($title, $text, $runs, $fills, $dates) use ($title_class, $text_class) {
        return "
        <div>
            <h2 class='$title_class'>$title</h2>
            <div class='$text_class'>$text</div>
            
            <div class='d-flex flex-wrap gap-5 mt-2 pt-1'>
                <div>
                    <span class='d-block small text-muted mb-1' style='font-size: 0.75rem; letter-spacing: 0.5px;'>Runs</span>
                    <span class='fw-bold text-body-emphasis' style='font-family: monospace; font-size: 1.1rem;'>
                        <i class='bi bi-hash me-1 text-primary-custom opacity-50'></i>$runs
                    </span>
                </div>
                <div>
                    <span class='d-block small text-muted mb-1' style='font-size: 0.75rem; letter-spacing: 0.5px;'>Fills</span>
                    <span class='fw-bold text-body-emphasis' style='font-family: monospace; font-size: 1.1rem;'>
                        <i class='bi bi-fuel-pump me-1 text-primary-custom opacity-50'></i>$fills
                    </span>
                </div>
                <div>
                    <span class='d-block small text-muted mb-1' style='font-size: 0.75rem; letter-spacing: 0.5px;'>Recorded</span>
                    <span class='fw-bold text-body-emphasis' style='font-size: 1rem;'>
                        <i class='bi bi-calendar-event me-1 text-primary-custom opacity-50'></i>$dates
                    </span>
                </div>
            </div>
        </div><hr class='my-4 opacity-10'>";
    };

return match($subdir) {
        // --- 2025 ---
        "2025" => $wrap("Plots for 2025", 
            "For 2025, a total of 115.65 fb<sup>-1</sup> of pp luminosity was certified. <br>Plots are organized by Individual Eras (C-G), Combined data (All), and Comparisons (vs).",
            "115.65 fb<sup>-1</sup> Certified"),
        "2025All" => $wrap_era("Plots for 2025",
            "A total of 115.65 <span>fb<sup>-1</sup></span> of pp luminosity was recorded and certified for the full 2025 data-taking period.",
            "392174 - 398903", "10638 - 11245", "May 16 - Nov 05"),
        "2025C" => $wrap_era("Plots for 2025C", 
            "A total of 20.78 <span>fb<sup>-1</sup></span> of pp luminosity was recorded and certified for this era of data taking.",
            "392174 - 393087", "10638 - 10697", "May 16 - Jun 07"),
        "2025D" => $wrap_era("Plots for 2025D", 
            "A total of 25.29 <span>fb<sup>-1</sup></span> of pp luminosity was recorded and certified for this era of data taking.",
            "394393 - 395948", "10821 - 10956", "Jun 18 - Aug 18"),
        "2025E" => $wrap_era("Plots for 2025E", 
            "A total of 14.00 <span>fb<sup>-1</sup></span> of pp luminosity was recorded and certified for this era of data taking.",
            "395982 - 396422", "10959 - 10997", "Aug 18 - Aug 31"),
        "2025F" => $wrap_era("Plots for 2025F", 
            "A total of 30.35 <span>fb<sup>-1</sup></span> of pp luminosity was recorded and certified for this era of data taking.",
            "396629 - 397853", "11044 - 11135", "Sep 07 - Oct 05"),
        "2025G" => $wrap_era("Plots for 2025G", 
            "A total of 25.23 <span>fb<sup>-1</sup></span> of pp luminosity was recorded and certified for this era of data taking.",
            "397954 - 398903", "11164 - 11245", "Oct 08 - Nov 05"),

        // --- 2024 ---
        "2024" => $wrap("Plots for 2024", 
            "For Level-1 Trigger, 2024 was the smoothest year of Run-3 pp data-taking so far.<br>A total of 123/113/109 fb<sup>-1</sup> of pp luminosity was delivered/recorded/certified.", 
            "109 fb<sup>-1</sup> Certified"),
        "2024All" => $wrap_era("Plots for 2024", 
            "A total of 109 <span>fb<sup>-1</sup></span> of pp luminosity was recorded and certified for the full 2024 data-taking period.",
            "378981 - 386974", "9473 - 10230", "Apr 05 - Oct 15"),
        "2024B" => $wrap_era("Plots for 2024B", 
            "A total of 0.13 <span>fb<sup>-1</sup></span> of pp luminosity was recorded and certified for this era of data taking.",
            "378981 - 379391", "9473 - 9514", "Apr 05 - Apr 13"),
        "2024C" => $wrap_era("Plots for 2024C", 
            "A total of 7.24 <span>fb<sup>-1</sup></span> of pp luminosity was recorded and certified for this era of data taking.",
            "379415 - 380238", "9517 - 9579", "Apr 14 - May 01"),
        "2024D" => $wrap_era("Plots for 2024D", 
            "A total of 7.96 <span>fb<sup>-1</sup></span> of pp luminosity was recorded and certified for this era of data taking.",
            "380255 - 380947", "9585 - 9653", "May 02 - May 20"),
        "2024E" => $wrap_era("Plots for 2024E", 
            "A total of 11.32 <span>fb<sup>-1</sup></span> of pp luminosity was recorded and certified for this era of data taking.",
            "380956 - 381594", "9654 - 9717", "May 20 - Jun 05"),
        "2024F" => $wrap_era("Plots for 2024F", 
            "A total of 27.76 <span>fb<sup>-1</sup></span> of pp luminosity was recorded and certified for this era of data taking.",
            "381984 - 383779", "9801 - 9943", "Jun 19 - Jul 28"),
        "2024G" => $wrap_era("Plots for 2024G", 
            "A total of 37.77 <span>fb<sup>-1</sup></span> of pp luminosity was recorded and certified for this era of data taking.",
            "383811 - 385801", "9945 - 10119", "Jul 29 - Sep 16"),
        "2024H" => $wrap_era("Plots for 2024H", 
            "A total of 5.44 <span>fb<sup>-1</sup></span> of pp luminosity was recorded and certified for this era of data taking.",
            "385836 - 386319", "10122 - 10144", "Sep 16 - Sep 26"),
        "2024I" => $wrap_era("Plots for 2024I", 
            "A total of 11.49 <span>fb<sup>-1</sup></span> of pp luminosity was recorded and certified for this era of data taking.",
            "386446 - 386974", "10189 - 10230", "Oct 02 - Oct 15"),

        // --- 2023 ---
        "2023" => $wrap("Plots for 2023", "For 2023, a total of 29 fb<sup>-1</sup> was delivered of which 28.41 fb<sup>-1</sup> was certified. <br>Plots are organized by individual eras (B,C,D) as well as combined 2023 data (All)."),
        "2023All" => $wrap_era("Plots for 2023", 
            "A total of 28.41 <span>fb<sup>-1</sup></span> of pp luminosity was recorded and certified for the full 2023 data-taking period.",
            "366403 - 371225", "8637 - 9073", "Apr 21 - Jul 16"),
        "2023B" => $wrap_era("Plots for 2023B", 
            "A total of  <span>fb<sup>-1</sup></span> of pp luminosity was recorded and certified for this era of data taking.",
            "366403 - 367079", "8637 - 8725", "Apr 21 - May 06"),
        "2023C" => $wrap_era("Plots for 2023C", 
            "A total of  <span>fb<sup>-1</sup></span> of pp luminosity was recorded and certified for this era of data taking.",
            "367094 - 369694", "8728 - 8997", "May 06 - Jun 28"),
        "2023D" => $wrap_era("Plots for 2023D", 
            "A total of  <span>fb<sup>-1</sup></span> of pp luminosity was recorded and certified for this era of data taking.",
            "369844 - 371225", "8999 - 9073", "Jun 28 - Jul 16"),

        // --- General Categories ---
        "eff" => $wrap("Efficiency Plots", 
            "Plots that show the L1T muon efficiency as a function of offline-reconstructed muon pT, &eta;, &phi; or &eta;-&phi; for:<br>
            <span style='color: #5790fc;'>&#9679;</span> <strong>uGMT:</strong> ($|\\eta|$ $\\le$ 2.4)<br>
            <span style='color: #f89c20;'>&#9679;</span> <strong>BMTF:</strong> ($|\\eta|$ $\\le$ 0.83)<br>
            <span style='color: #e42536;'>&#9679;</span> <strong>OMTF:</strong> (0.83 $\\le$ $|\\eta|$ $\\le$ 1.24)<br>
            <span style='color: #964a8b;'>&#9679;</span> <strong>EMTF:</strong> (1.24 $\\le$ $|\\eta|$ $\\le$ 2.4)<br>
            The plots are divided into two working points: one for an L1T muon pT threshold of 22 GeV and an L1T quality cut of 12, and the second for an L1T muon pT threshold of 5 GeV with an L1T quality cut of 8 GeV.<br>"),
        
        "misid" => $wrap("Charge Misidentification", 
            "Plots showing the L1T muon charge misidentification probability as a function of offline-reconstructed muon pT and &eta;-&phi;.<br>
            <strong>WP:</strong> 22 GeV / Qual 12. Matched if &Delta;R(L1, offline) < 0.1."),
        
        "eff_qual" => $wrap("Quality Cut Efficiency Scan", 
            "Comparison of BMTF efficiency for different L1T Quality cuts at a fixed L1 p<sub>T</sub> $\\ge$ 22 GeV.<br>
            <span style='color: #5790fc;'>&#9679;</span> <strong>Quality &ge; 12</strong><br>
            <span style='color: #f89c20;'>&#9679;</span> <strong>Quality &ge; 13</strong><br>
            <span style='color: #e42536;'>&#9679;</span> <strong>Quality &ge; 14</strong><br>
            <span style='color: #964a8b;'>&#9679;</span> <strong>Quality &ge; 15</strong><br>
            The new high quality working points are chosen to have very high L1T muon purity and recover muons in low pT region while staying within the available L1T rate budget.<br>
            Plots include efficiency vs offline p<sub>T</sub>, &eta;, and &phi;."),

        "eff_run" => $wrap("Efficiency vs Run Number", "Average L1T muon efficiency as a function of the run number."),
        "eff_vs_run" => $wrap("Efficiency vs Run Number", "Average L1T muon efficiency as a function of the run number."),
        
        "misid_run" => $wrap("Charge misidentification vs Run Number", "Average L1T charge misidentification probability as a function of the run number."),
        "misid_vs_run" => $wrap("Charge misidentification vs Run Number", "Average L1T charge misidentification probability as a function of the run number."),

        // --- Specific Working Points ---
        "eff_22_11" => $wrap("BMTF Efficiency Comparison", 
            "Comparison of L1T efficiency in the Barrel Muon Track Finder ($|\\eta|$ $\\le$ 0.83) between the standard SingleMu22 and the high quality, low p<sub>T</sub> working point.<br>
            <span class='text-primary'>&#9679;</span> <strong>Standard:</strong> L1 p<sub>T</sub> $\\ge$ 22 GeV, Qual $\\ge$ 12 (Offline p<sub>T</sub> $\\ge$ 26 GeV)<br>
            <span class='text-danger'>&#9679;</span> <strong>High Quality:</strong> L1 p<sub>T</sub> $\\ge$ 11 GeV, Qual $\\ge$ 14 (Offline p<sub>T</sub> $\\ge$ 15 GeV)<br>
            Plots include efficiency vs offline p<sub>T</sub>, &eta;, and &phi;."),
            
        "eff_22_15" => $wrap("Global Muon Trigger Efficiency Comparison", 
            "Comparison of L1T efficiency in the Global Muon Trigger ($|\\eta|$ $\\le$ 2.4) between the standard SingleMu22 and the DoubleMu15 working point.<br>
            <span class='text-primary'>&#9679;</span> <strong>SingleMu22:</strong> L1 p<sub>T</sub> $\\ge$ 22 GeV, Quality $\\ge$ 12 (Offline p<sub>T</sub> $\\ge$ 26 GeV)<br>
            <span class='text-danger'>&#9679;</span> <strong>DoubleMu15:</strong> L1 p<sub>T</sub> $\\ge$ 15 GeV, Quality $\\ge$ 8 (Offline p<sub>T</sub> $\\ge$ 19 GeV)<br>
            Plots include efficiency vs offline p<sub>T</sub>, &eta;, and &phi;."),
        
        "eff_22_15_7_3" => $wrap("Multi-Threshold Efficiency", 
            "Comparison of L1T efficiency across all track finder regions (uGMT, BMTF, OMTF, EMTF) for four distinct working points ranging from high to open quality.<br>
            <span style='color: #5790fc;'>&#9679;</span> <strong>SingleMu22:</strong> L1 p<sub>T</sub> $\\ge$ 22 GeV, Quality $\\ge$ 12 (Offline p<sub>T</sub> $\\ge$ 26 GeV)<br>
            <span style='color: #f89c20;'>&#9679;</span> <strong>SingleMu15:</strong> L1 p<sub>T</sub> $\\ge$ 15 GeV, Quality $\\ge$ 8 (Offline p<sub>T</sub> $\\ge$ 19 GeV)<br>
            <span style='color: #e42536;'>&#9679;</span> <strong>SingleMu7:</strong> L1 p<sub>T</sub> $\\ge$ 7 GeV, Quality $\\ge$ 4 (Offline p<sub>T</sub> $\\ge$ 11 GeV)<br>
            <span style='color: #964a8b;'>&#9679;</span> <strong>SingleMu3:</strong> L1 p<sub>T</sub> $\\ge$ 3 GeV, Quality $\\ge$ 0 (Offline p<sub>T</sub> $\\ge$ 7 GeV)<br>
            Plots include efficiency vs offline p<sub>T</sub>, &eta;, and &phi; for each track finder."),
        
        // --- All Working Points ---
        "eff_all" => $wrap("Efficiency Plots", 
            "Efficiency plots for different working points, depending on the L1T quality and the L1T muon p<sub>T</sub> cuts.<br>
            <ul class='mt-2 mb-3 ps-3'>
                <li class='mb-1'>For the L1T quality, four working points are used: 'Single Quality' (<strong>12</strong>), 'Double Quality' (<strong>8</strong>), 'Open Quality' (<strong>4</strong>), and a working point without any quality cut (<strong>0</strong>).</li>
                <li>For each quality working point, the following set of L1T muon p<sub>T</sub> cuts are used: <strong>26, 22, 20, 15, 10, 7, 5, 3 GeV</strong>.</li>
            </ul>
            Plots show efficiency as a function of offline p<sub>T</sub>, &eta;, &phi;, and 2D &eta;-&phi; maps for:<br>
            <span style='color: #5790fc;'>&#9679;</span> <strong>uGMT:</strong> ($|\\eta|$ $\\le$ 2.4)<br>
            <span style='color: #f89c20;'>&#9679;</span> <strong>BMTF:</strong> ($|\\eta|$ $\\le$ 0.83)<br>
            <span style='color: #e42536;'>&#9679;</span> <strong>OMTF:</strong> (0.83 $\\le$ $|\\eta|$ $\\le$ 1.24)<br>
            <span style='color: #964a8b;'>&#9679;</span> <strong>EMTF:</strong> (1.24 $\\le$ $|\\eta|$ $\\le$ 2.4)<br>
            <div class='mt-3 small text-muted border-top pt-2'>
                <div><i class='bi bi-tag-fill me-1'></i> Naming scheme: <strong>L1MuX_Y</strong> (where X = p<sub>T</sub> cut, Y = Quality cut)</div>
                <div><i class='bi bi-info-circle me-1'></i> Offline cut applied is <strong>p<sub>T</sub><sup>offline</sup> $\\ge$ p<sub>T</sub><sup>L1</sup> + 4 GeV</strong>.</div>
            </div>"),
        
        // --- Comparison Directories ---
        "eff_comparison_Qual0" => $wrap("Efficiency Comparisons (Quality 0)", 
            "Comparative studies with no quality cut ($\ge$ 0).<br>
            Two different comparison categories are provided::<br>
            <ul class='mt-2 mb-3 ps-3'>
                <li class='mb-1'> Comparison between seven different <strong>&eta; regions</strong> (for fixed p<sub>T</sub><sup>L1</sup> threshold):
                    <div class='col-md-6 mt-3 mt-md-0'>
                    <ul class='list-unstyled mb-0 small'>
                        <li><span style='color: #e76300;'>&#9679;</span> <strong>uGMT</strong> ($|\\eta| \\le 2.4$)</li>
                        <li><span style='color: #3f90da;'>&#9679;</span> <strong>BMTF</strong> ($|\\eta| \\le 0.83$)</li>
                        <li><span style='color: #ffa90e;'>&#9679;</span> <strong>OMTF</strong> ($0.83 \\le |\\eta| \\le 1.24$)</li>
                        <li><span style='color: #bd1f01;'>&#9679;</span> <strong>EMTF</strong> ($1.24 \\le |\\eta| \\le 2.4$)</li>
                        <li><span style='color: #94a4a2;'>&#9679;</span> <strong>EMTF1</strong> ($1.24 \\le |\\eta| \\le 1.6$)</li>
                        <li><span style='color: #832db6;'>&#9679;</span> <strong>EMTF2</strong> ($1.6 \\le |\\eta| \\le 2.1$)</li>
                        <li><span style='color: #a96b59;'>&#9679;</span> <strong>EMTF3</strong> ($2.1 \\le |\\eta| \\le 2.4$)</li>
                    </ul>
                    </div>
                </li>
                <li> Comparison between seven different <strong>p<sub>T</sub> thresholds</strong> (for fixed &eta; region):
                    <div class='col-md-6'>
                    <ul class='list-unstyled mb-0 small'>
                        <li><span style='color: #1845fb;'>&#9679;</span> <strong>L1Mu26_0</strong> (p<sub>T</sub><sup>L1</sup> $\\ge$ 26)</li>
                        <li><span style='color: #ff5e02;'>&#9679;</span> <strong>L1Mu22_0</strong> (p<sub>T</sub><sup>L1</sup> $\\ge$ 22)</li>
                        <li><span style='color: #c91f16;'>&#9679;</span> <strong>L1Mu20_0</strong> (p<sub>T</sub><sup>L1</sup> $\\ge$ 20)</li>
                        <li><span style='color: #c849a9;'>&#9679;</span> <strong>L1Mu15_0</strong> (p<sub>T</sub><sup>L1</sup> $\\ge$ 15)</li>
                        <li><span style='color: #adad7d;'>&#9679;</span> <strong>L1Mu10_0</strong> (p<sub>T</sub><sup>L1</sup> $\\ge$ 10)</li>
                        <li><span style='color: #86c8dd;'>&#9679;</span> <strong>L1Mu5_0</strong> (p<sub>T</sub><sup>L1</sup> $\\ge$ 5)</li>
                        <li><span style='color: #578dff;'>&#9679;</span> <strong>L1Mu3_0</strong> (p<sub>T</sub><sup>L1</sup> $\\ge$ 3)</li>
                    </ul>
                </li>
                </div>
            </ul>
            <div class='mt-3 small text-muted border-top pt-2'>
                <i class='bi bi-info-circle me-1'></i> Offline cut applied is <strong>p<sub>T</sub><sup>offline</sup> $\\ge$ p<sub>T</sub><sup>L1</sup> + 4 GeV</strong>.
            </div>"),

        "eff_comparison_Qual4" => $wrap("Efficiency Comparisons (Quality 4)", 
            "Comparative studies for the Open Quality ($\ge$ 4) working point.<br>
            Two different comparison categories are provided::<br>
            <ul class='mt-2 mb-3 ps-3'>
                <li class='mb-1'> Comparison between seven different <strong>&eta; regions</strong> (for fixed p<sub>T</sub><sup>L1</sup> threshold):
                    <div class='col-md-6 mt-3 mt-md-0'>
                    <ul class='list-unstyled mb-0 small'>
                        <li><span style='color: #e76300;'>&#9679;</span> <strong>uGMT</strong> ($|\\eta| \\le 2.4$)</li>
                        <li><span style='color: #3f90da;'>&#9679;</span> <strong>BMTF</strong> ($|\\eta| \\le 0.83$)</li>
                        <li><span style='color: #ffa90e;'>&#9679;</span> <strong>OMTF</strong> ($0.83 \\le |\\eta| \\le 1.24$)</li>
                        <li><span style='color: #bd1f01;'>&#9679;</span> <strong>EMTF</strong> ($1.24 \\le |\\eta| \\le 2.4$)</li>
                        <li><span style='color: #94a4a2;'>&#9679;</span> <strong>EMTF1</strong> ($1.24 \\le |\\eta| \\le 1.6$)</li>
                        <li><span style='color: #832db6;'>&#9679;</span> <strong>EMTF2</strong> ($1.6 \\le |\\eta| \\le 2.1$)</li>
                        <li><span style='color: #a96b59;'>&#9679;</span> <strong>EMTF3</strong> ($2.1 \\le |\\eta| \\le 2.4$)</li>
                    </ul>
                    </div>
                </li>
                <li> Comparison between seven different <strong>p<sub>T</sub> thresholds</strong> (for fixed &eta; region):
                    <div class='col-md-6'>
                    <ul class='list-unstyled mb-0 small'>
                        <li><span style='color: #1845fb;'>&#9679;</span> <strong>L1Mu26_4</strong> (p<sub>T</sub><sup>L1</sup> $\\ge$ 26)</li>
                        <li><span style='color: #ff5e02;'>&#9679;</span> <strong>L1Mu22_4</strong> (p<sub>T</sub><sup>L1</sup> $\\ge$ 22)</li>
                        <li><span style='color: #c91f16;'>&#9679;</span> <strong>L1Mu20_4</strong> (p<sub>T</sub><sup>L1</sup> $\\ge$ 20)</li>
                        <li><span style='color: #c849a9;'>&#9679;</span> <strong>L1Mu15_4</strong> (p<sub>T</sub><sup>L1</sup> $\\ge$ 15)</li>
                        <li><span style='color: #adad7d;'>&#9679;</span> <strong>L1Mu10_4</strong> (p<sub>T</sub><sup>L1</sup> $\\ge$ 10)</li>
                        <li><span style='color: #86c8dd;'>&#9679;</span> <strong>L1Mu5_4</strong> (p<sub>T</sub><sup>L1</sup> $\\ge$ 5)</li>
                        <li><span style='color: #578dff;'>&#9679;</span> <strong>L1Mu3_4</strong> (p<sub>T</sub><sup>L1</sup> $\\ge$ 3)</li>
                    </ul>
                </li>
                </div>
            </ul>
            <div class='mt-3 small text-muted border-top pt-2'>
                <i class='bi bi-info-circle me-1'></i> Offline cut applied is <strong>p<sub>T</sub><sup>offline</sup> $\\ge$ p<sub>T</sub><sup>L1</sup> + 4 GeV</strong>.
            </div>"),          

        "eff_comparison_Qual8" => $wrap("Efficiency Comparisons (Quality 8)", 
            "Comparative studies for the Double Quality ($\ge$ 8) working point.<br>
            Two different comparison categories are provided::<br>
            <ul class='mt-2 mb-3 ps-3'>
                <li class='mb-1'> Comparison between seven different <strong>&eta; regions</strong> (for fixed p<sub>T</sub><sup>L1</sup> threshold):
                    <div class='col-md-6 mt-3 mt-md-0'>
                    <ul class='list-unstyled mb-0 small'>
                        <li><span style='color: #e76300;'>&#9679;</span> <strong>uGMT</strong> ($|\\eta| \\le 2.4$)</li>
                        <li><span style='color: #3f90da;'>&#9679;</span> <strong>BMTF</strong> ($|\\eta| \\le 0.83$)</li>
                        <li><span style='color: #ffa90e;'>&#9679;</span> <strong>OMTF</strong> ($0.83 \\le |\\eta| \\le 1.24$)</li>
                        <li><span style='color: #bd1f01;'>&#9679;</span> <strong>EMTF</strong> ($1.24 \\le |\\eta| \\le 2.4$)</li>
                        <li><span style='color: #94a4a2;'>&#9679;</span> <strong>EMTF1</strong> ($1.24 \\le |\\eta| \\le 1.6$)</li>
                        <li><span style='color: #832db6;'>&#9679;</span> <strong>EMTF2</strong> ($1.6 \\le |\\eta| \\le 2.1$)</li>
                        <li><span style='color: #a96b59;'>&#9679;</span> <strong>EMTF3</strong> ($2.1 \\le |\\eta| \\le 2.4$)</li>
                    </ul>
                    </div>
                </li>
                <li> Comparison between seven different <strong>p<sub>T</sub> thresholds</strong> (for fixed &eta; region):
                    <div class='col-md-6'>
                    <ul class='list-unstyled mb-0 small'>
                        <li><span style='color: #1845fb;'>&#9679;</span> <strong>L1Mu26_8</strong> (p<sub>T</sub><sup>L1</sup> $\\ge$ 26)</li>
                        <li><span style='color: #ff5e02;'>&#9679;</span> <strong>L1Mu22_8</strong> (p<sub>T</sub><sup>L1</sup> $\\ge$ 22)</li>
                        <li><span style='color: #c91f16;'>&#9679;</span> <strong>L1Mu20_8</strong> (p<sub>T</sub><sup>L1</sup> $\\ge$ 20)</li>
                        <li><span style='color: #c849a9;'>&#9679;</span> <strong>L1Mu15_8</strong> (p<sub>T</sub><sup>L1</sup> $\\ge$ 15)</li>
                        <li><span style='color: #adad7d;'>&#9679;</span> <strong>L1Mu10_8</strong> (p<sub>T</sub><sup>L1</sup> $\\ge$ 10)</li>
                        <li><span style='color: #86c8dd;'>&#9679;</span> <strong>L1Mu5_8</strong> (p<sub>T</sub><sup>L1</sup> $\\ge$ 5)</li>
                        <li><span style='color: #578dff;'>&#9679;</span> <strong>L1Mu3_8</strong> (p<sub>T</sub><sup>L1</sup> $\\ge$ 3)</li>
                    </ul>
                </li>
                </div>
            </ul>
            <div class='mt-3 small text-muted border-top pt-2'>
                <i class='bi bi-info-circle me-1'></i> Offline cut applied is <strong>p<sub>T</sub><sup>offline</sup> $\\ge$ p<sub>T</sub><sup>L1</sup> + 4 GeV</strong>.
            </div>"),           

        "eff_comparison_Qual12" => $wrap("Efficiency Comparisons (Quality 12)", 
            "Comparative studies for the Single Quality ($\ge$ 12) working point.<br>
            Two different comparison categories are provided::<br>
            <ul class='mt-2 mb-3 ps-3'>
                <li class='mb-1'> Comparison between seven different <strong>&eta; regions</strong> (for fixed p<sub>T</sub><sup>L1</sup> threshold):
                    <div class='col-md-6 mt-3 mt-md-0'>
                    <ul class='list-unstyled mb-0 small'>
                        <li><span style='color: #e76300;'>&#9679;</span> <strong>uGMT</strong> ($|\\eta| \\le 2.4$)</li>
                        <li><span style='color: #3f90da;'>&#9679;</span> <strong>BMTF</strong> ($|\\eta| \\le 0.83$)</li>
                        <li><span style='color: #ffa90e;'>&#9679;</span> <strong>OMTF</strong> ($0.83 \\le |\\eta| \\le 1.24$)</li>
                        <li><span style='color: #bd1f01;'>&#9679;</span> <strong>EMTF</strong> ($1.24 \\le |\\eta| \\le 2.4$)</li>
                        <li><span style='color: #94a4a2;'>&#9679;</span> <strong>EMTF1</strong> ($1.24 \\le |\\eta| \\le 1.6$)</li>
                        <li><span style='color: #832db6;'>&#9679;</span> <strong>EMTF2</strong> ($1.6 \\le |\\eta| \\le 2.1$)</li>
                        <li><span style='color: #a96b59;'>&#9679;</span> <strong>EMTF3</strong> ($2.1 \\le |\\eta| \\le 2.4$)</li>
                    </ul>
                    </div>
                </li>
                <li> Comparison between seven different <strong>p<sub>T</sub> thresholds</strong> (for fixed &eta; region):
                    <div class='col-md-6'>
                    <ul class='list-unstyled mb-0 small'>
                        <li><span style='color: #1845fb;'>&#9679;</span> <strong>L1Mu26_12</strong> (p<sub>T</sub><sup>L1</sup> $\\ge$ 26)</li>
                        <li><span style='color: #ff5e02;'>&#9679;</span> <strong>L1Mu22_12</strong> (p<sub>T</sub><sup>L1</sup> $\\ge$ 22)</li>
                        <li><span style='color: #c91f16;'>&#9679;</span> <strong>L1Mu20_12</strong> (p<sub>T</sub><sup>L1</sup> $\\ge$ 20)</li>
                        <li><span style='color: #c849a9;'>&#9679;</span> <strong>L1Mu15_12</strong> (p<sub>T</sub><sup>L1</sup> $\\ge$ 15)</li>
                        <li><span style='color: #adad7d;'>&#9679;</span> <strong>L1Mu10_12</strong> (p<sub>T</sub><sup>L1</sup> $\\ge$ 10)</li>
                        <li><span style='color: #86c8dd;'>&#9679;</span> <strong>L1Mu5_12</strong> (p<sub>T</sub><sup>L1</sup> $\\ge$ 5)</li>
                        <li><span style='color: #578dff;'>&#9679;</span> <strong>L1Mu3_12</strong> (p<sub>T</sub><sup>L1</sup> $\\ge$ 3)</li>
                    </ul>
                </li>
                </div>
            </ul>
            <div class='mt-3 small text-muted border-top pt-2'>
                <i class='bi bi-info-circle me-1'></i> Offline cut applied is <strong>p<sub>T</sub><sup>offline</sup> $\\ge$ p<sub>T</sub><sup>L1</sup> + 4 GeV</strong>.
            </div>"),
            
        default => ""
    };
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
            <div class="mb-5 ms-1">
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

    <script>
    window.MathJax = {
      tex: {
        inlineMath: [['$', '$'], ['\\(', '\\)']]
      },
      svg: {
        fontCache: 'global'
      }
    };
    </script>
    <script type="text/javascript" id="MathJax-script" async
      src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js">
    </script>

</body>
</html>