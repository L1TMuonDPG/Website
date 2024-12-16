<?php
// authors: Marcel Rieger, Clemens Lange, based on the original work by P. Musella and improvements by G. Petrucciani
// see https://gitlab.cern.ch/cms-analysis/general/php-plots for more info
//
// settings
//
// Configuration
$plot_extensions = array(
    "png", "pdf", "jpg", "jpeg", "gif", "PNG", "PDF", "JPG", "JPEG", "GIF",
);

$additional_extensions = array(
    "eps", "svg", "root", "cxx", "txt", "rtf", "log", "csv", "EPS", "SVG", "ROOT", "CXX", "TXT", "RTF", "LOG", "CSV",
);

$max_plot_depth = isset($_GET["depth"]) ? intval($_GET["depth"]) : 0;
  //
  // helpers  //

  // function that decides whether an entry given by its name is shown,
  // considering the name itself and optional search strings
function show_entry($name) {
  if ($name[0] === "." || $name[0] === "_") {
      return false;
  }
    
  $search = $_GET["search"] ?? "";
  if (empty($search)) {
      return true;
  }
    
  $patterns = explode(" ", preg_replace("/\s+/", " ", $search));
  $mode = $_GET["search_pattern_mode"] ?? "any";
    
  return match($mode) {
      "all" => array_reduce($patterns, fn($carry, $pattern) => 
          $carry && fnmatch("*$pattern*", $name), true),
      "exact" => fnmatch("*$search*", $name),
      default => array_reduce($patterns, fn($carry, $pattern) => 
          $carry || fnmatch("*$pattern*", $name), false)
  };
}
// My settings 
// Check if the current path is the root (home page)
$is_home_page = ($_SERVER['REQUEST_URI'] == '/' || $_SERVER['REQUEST_URI'] == 'index.php');

$current_path = trim($_SERVER['REQUEST_URI'], '/'); // Remove leading/trailing slashes
$path_parts = explode('/', $current_path); // Split the path into parts
$subdirectory = end($path_parts); // Get the last part of the path
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- include third-party style sheets via cdns -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">

    <!-- minimal custom styles -->
    <style type="text/css">
      a {
        text-decoration: none;
      }

      nav ol.breadcrumb {
        margin: 0;
        padding: 0;
        background-color: transparent;
      }

      body > div.container-fluid {
        margin: 15px 0;
        padding: 0 15px;
      }

      .empty-text {
        font-style: italic;
        font-size: 0.9rem;
      }

      #plot-listing .card {
        margin: 5px;
      }

      #plot-listing .card-header {
        font-size: 0.8rem;
        padding: 0.4rem;
      }

      #plot-listing .card-footer {
        height: 100%;
        font-size: 0.8rem;
        padding: 0.4rem;
      }

      #footer {
        position: fixed;
        bottom: 0px;
        right: 0px;
        padding: 8px;
        font-size: 0.75rem;
        background-color: rgba(255,255,255,0.8);
        border-radius: 4px;
      }

      @media screen and (min-width: 1200px) {
        #plot-listing .card {
          max-width: 350px;
        }
      }

      @media screen and (min-width: 800px) and (max-width: 1199px) {
        #plot-listing .card {
          max-width: 300px;
        }
      }

      @media screen and (min-width: 668px) and (max-width: 799px) {
        #plot-listing .card {
          max-width: 250px;
        }
      }

      /* portrait mode, without pixel ratio setting */
      @media only screen and (max-height: 667px) and (orientation: portrait) {
        #plot-listing .card {
          max-width: 162px;
        }
      }

      /* landscape mode, without pixel ratio setting */
      @media only screen and (max-height: 667px) and (orientation: landscape) {
        #plot-listing .card {
          max-width: 202px;
        }
      }

      .sortable-card {
        cursor: grab;
        margin-bottom: 1rem;
      }

      .ui-state-highlight {
        height: 350px;
        background-color: #f0f0f0;
        border: 2px dashed #007bff;
        margin: 5px;
      }

      .modal {
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.4);
        display: flex;
        align-items: center;
        justify-content: center;
      }

      .modal-content {
        background-color: #fff;
        padding: 20px;
        border-radius: 5px;
        width: 50%;
        text-align: center;
      }

      .close {
        position: absolute;
        right: 15px;
        top: 10px;
        font-size: 24px;
        cursor: pointer;
      }
      
      #contact-modal .modal-content p {
        margin-bottom: 4px;
      }

    </style>

    <title>Muon DPG PlotBrowser</title>
  </head>

  <body>
    <!-- navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
      <div class="container-fluid">

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
          <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item">
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                  <?
                    // home
                    echo "<li class=\"breadcrumb-item\"><a href=\"/\"><i class=\"bi bi-house-door-fill\"></i></a></li>";

                    // path fragments
                    $rel_dir = trim(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH), "/");
                    $base_path = getcwd();
                    $current_path = $base_path . "/" . $rel_dir;                    if ($rel_dir != "") {
                      $fragments = explode("/", $rel_dir);
                      foreach($fragments as $i=>$fragment) {
                        $href = implode("/", array_fill(0, count($fragments) - 1 - $i, ".."));
                        echo "<li class=\"breadcrumb-item\"><a href=\"" . htmlspecialchars($href) . "\">" . htmlspecialchars($fragment) . "</a></li>";
                      }
                    }
                  ?>
                </ol>
              </nav>
            </li>
          </ul>
          <form class="d-flex">
            <input type="hidden" name="depth" value="<?php echo $max_plot_depth; ?>">
            <div class="input-group me-2">
              <span class="input-group-text">Depth</span>
              <a class="btn btn-outline-secondary" href="?depth=<?php echo max(0, ($max_plot_depth - 1)); ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['search_pattern_mode']) ? '&search_pattern_mode=' . urlencode($_GET['search_pattern_mode']) : ''; ?>">
                <i class="bi bi-dash"></i>
              </a>
              <span class="input-group-text"><?php echo $max_plot_depth; ?></span>
              <a class="btn btn-outline-secondary" href="?depth=<?php echo $max_plot_depth + 1; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?><?php echo isset($_GET['search_pattern_mode']) ? '&search_pattern_mode=' . urlencode($_GET['search_pattern_mode']) : ''; ?>">
                <i class="bi bi-plus"></i>
              </a>
            </div>
            <div class="input-group">
              <div style="position:relative">
                <input class="form-control" type="search" name="search" placeholder="Pattern(s)" aria-label="Search" value="<?php if (isset($_GET["search"])) echo htmlspecialchars($_GET["search"]); ?>">
                <?php if (isset($_GET["search"]) && !empty($_GET["search"])): ?>
                  <button type="button" class="btn btn-sm position-absolute" style="right:8px; top:50%; transform:translateY(-50%)" onclick="this.previousElementSibling.value='';this.closest('form').submit()">
                    <i class="bi bi-x"></i>
                  </button>
                <?php endif; ?>
              </div>
            </div>
              <select class="btn btn-secondary bootstrap-select" name="search_pattern_mode" type="button" aria-expanded="false">
                <option value="any" <?php echo (!isset($_GET["search_pattern_mode"]) || $_GET["search_pattern_mode"] == "any") ? "selected" : ""; ?>>any</option>
                <option value="all" <?php echo (isset($_GET["search_pattern_mode"]) && $_GET["search_pattern_mode"] == "all") ? "selected" : ""; ?>>all</option>
                <option value="exact" <?php echo (isset($_GET["search_pattern_mode"]) && $_GET["search_pattern_mode"] == "exact") ? "selected" : ""; ?>>exact</option>
              </select>
              <button class="btn btn-outline-success" type="submit">Search</button>
            </div>
          </form>
        </div>

      </div>
    </nav>
      
    <?php
      // Display different content based on whether we are on the homepage
      if ($is_home_page) {
        echo '<div id="home-title" class="container-fluid">
        <h2>L1T Muon DPG Plots Repository</h2>
        <p style="text-align: left; margin-left: 0; font-size: 20px;">Team Members:</p>
        <ul style="text-align: left; margin-left: 5px; font-size: 18px; list-style-type: disc;">
            <li>Ioannis Paraskevas, Muon DPG convener, National and Kapodistrian University of Athens</li>
            <li>Nikolaos Plastiras, PhD student, National and Kapodistrian University of Athens</li>
            <li>Panagiotis Katris, PhD student, National and Kapodistrian University of Athens</li>
        </ul>
        <h4>Publications</h4>
        <ul style="text-align: left; margin-left: 5px; font-size: 18px; list-style-type: disc;">
          <li><a href="https://cds.cern.ch/record/2917885" target="_blank">Level-1 Trigger Performance in 2024 Proton-Proton Collisions at &radic;s 13.6 TeV </a></li>
          <li><a href="https://cds.cern.ch/record/2868794?ln=en" target="_blank">Level-1 Muon Trigger Performance with part of 2023 dataset</a></li>
        </ul>
        <p style="text-align: left; margin-left: 0; font-size: 20px;"> Navigate to the following directories to see the plots </p>
        </div>';
      } 
      if ($subdirectory == "2024") {
        echo '<div id="2024" class="container-fluid">
        <p style="text-align: left; margin-left: 0; font-size: 24px;">Plots for 2024 </p>
        </div>';
      } elseif ($subdirectory == "2023"){

      } elseif ($subdirectory == "eff") {
        echo '<div id="2024" class="container-fluid">
        <h4>Efficiency plots</h4>
        <p style="text-align: left; margin-left: 0; font-size: 18px;">Plots that show the L1T muon efficiency as a function of offline-reconstructed muon pT, &eta;, &phi; or &eta;-&phi;, separately for each track finder and combined.<br> 
        The plots are divided into two working points: one for an L1T muon pT threshold of 22 GeV and an L1T quality cut of 12, and the second for an L1T muon pT threshold of 5 GeV with an L1T quality cut of 8 GeV.</p>
        </div>';
      } elseif ($subdirectory == "misid"){
        echo '<div id="2024" class="container-fluid">
        <h4>Charge misidentification probability plots</h4>
        <p style="text-align: left; margin-left: 0; font-size: 18px;">Plots that show the L1T muon charge misidentification probability as a function of offline-reconstructed muon pT, separately for each track finder and combined, and as a function of &eta;-&phi;.<br> 
        For these plots, an L1T muon pT threshold of 22 GeV and an L1T quality cut of 12 are used. L1T muons are matched to offline muons if &Delta;R(L1, offline) < 0.1.</p>
        </div>';
      } elseif ($subdirectory == "eff_qual") {
        echo '<div id="2024" class="container-fluid">
        <h4>Efficiency plots for different L1T quality cuts</h4>
        <p style="text-align: left; margin-left: 0; font-size: 18px;">Plots that show the L1T muon efficiency as a function of offline-reconstructed muon pT, &eta;, &phi; or &eta;-&phi;, for the Barrel Muon Track Finder (BMTF), for four different L1T quality cuts.<br> 
        The new high quality working points are chosen to have very high L1T muon purity and recover muons in low pT region while staying within the available L1T rate budget.</p>
        </div>';
      } elseif ($subdirectory == "eff_run") {
        echo '<div id="2024" class="container-fluid">
        <h4>Efficiency plots versus run number </h4>
        <p style="text-align: left; margin-left: 0; font-size: 18px;">Plots that show the average L1T muon efficiency as a function of the run number.</p>
        </div>';
      } elseif ($subdirectory == "misid_run") {
        echo '<div id="2024" class="container-fluid">
        <h4>Charge misidentification probability plots versus run number </h4>
        <p style="text-align: left; margin-left: 0; font-size: 18px;">Plots that show the average L1T charge misidentification probability as a function of the run number.</p>
        </div>';
      }

    //   else {
    //       echo '<div id="subdirectory-title" class="container-fluid">
    //               <p style="text-align: left; margin-left: 0; font-size: 24px;">Subdirectory Content</p>
    //             </div>';
    //  }
    ?>

    <!-- show local serving directory -->
    <!-- <div id="local-directory" class="container-fluid">
      <p>Served from <i><? echo getcwd() . "/" . $rel_dir; ?></i></p>
    </div> -->

    <!-- show search info -->
    <?
      if (isset($_GET["search"]) && !empty($_GET["search"])) {
        echo "<div id=\"search-description\" class=\"container-fluid\">";
        echo "<i>Searching for '<b>" . htmlspecialchars($_GET["search"]) . "</b>'";
        $search_pattern_mode = isset($_GET["search_pattern_mode"]) ? htmlspecialchars($_GET["search_pattern_mode"]) : "";
        if ($search_pattern_mode != "") {
          echo " (mode '" . htmlspecialchars($search_pattern_mode) . "')";
        }
        echo "</i></div>";
      }
    ?>

    <!-- list other directories -->
    <div id="directory-listing" class="container-fluid">
      <h4><a id="directories">Directories</a></h4>
      <?
        $dir_names = array();
        foreach (glob("./$rel_dir/*") as $dir_name) {
          if (is_dir($dir_name)) {
            $dir_name_split = explode("/", $dir_name);
            $dir_basename = end($dir_name_split);
            // Skip bin and tests directories in base dir
            if ($rel_dir == "" && ($dir_basename == "bin" || $dir_basename == "tests")) {
              continue;
            }
            array_push($dir_names, $dir_basename);
          }
        }
        if (count($dir_names) == 0) {
          echo "<span class=\"empty-text\">No directories to display</span>";
        } else {
          echo "<ul>";
          sort($dir_names);
          foreach($dir_names as $dir_name) {
            echo "<li><a href=\"". htmlspecialchars($dir_name) . "\">" . htmlspecialchars($dir_name) . "</a></li>";
          }
          echo "</ul>";
        }
      ?>
    </div>

    <!-- list plots -->
    <div id="plot-listing" class="container-fluid">
      <h4><a id="plots">Plots</a></h4>
    <div id="sortable" class="d-flex align-content-start flex-wrap">
      <?
    function glob_recursive(string $pattern, int $depth, string $rel_dir): array {
        if ($depth < 0) return [];
        $base_path = getcwd();
        $search_pattern = $rel_dir === '' ? "$base_path/*." . pathinfo($pattern, PATHINFO_EXTENSION) : $pattern;
    
        $files = glob($search_pattern);
        if ($depth == 0) return $files;
    
        $dir_pattern = $rel_dir === '' ? "$base_path/*" : dirname($pattern) . '/*';
    
        return array_reduce(glob($dir_pattern, GLOB_ONLYDIR), function($acc, $dir) use ($pattern, $depth, $rel_dir, $base_path) {
            $dir_name = basename($dir);
            if ($dir_name[0] !== '.' && $dir_name[0] !== '_' && strpos(realpath($dir), $base_path) === 0) {
                return array_merge($acc, glob_recursive(
                    "$dir/*." . pathinfo($pattern, PATHINFO_EXTENSION),
                    $depth - 1,
                    $rel_dir
                ));
            }
            return $acc;
        }, $files);
    }    $nested_files = array();
    $covered_files = array();
    $plot_names = array();
    foreach ($plot_extensions as $ext) {
        foreach (glob_recursive("$rel_dir/*.$ext", $max_plot_depth, $rel_dir) as $file_path) {
            if (!is_file($file_path) || !show_entry($file_path)) {
                continue;
            }
            // Clean the path to remove ./ and ensure correct relative paths
            $file_path_clean = preg_replace('#^\./|^' . $rel_dir . '/#', '', $file_path);
            $file_name = pathinfo($file_path_clean, PATHINFO_FILENAME);
            $file_dir = dirname($file_path_clean);
            $display_path = $file_dir == '.' ? $file_name : "$file_dir/$file_name";
            
            if (!array_key_exists($display_path, $nested_files)) {
                $nested_files[$display_path] = [];
            }
            $nested_files[$display_path][] = $ext;
            $covered_files[] = $file_path_clean;
        }
    }
          // show plots
          if (count($nested_files) == 0) {
            echo "<span class=\"empty-text\">No plots to display</span>";
          } else {
            // sort by name
            $file_names = array_keys($nested_files);
            sort($file_names);
            foreach ($file_names as $file_name) {
              foreach ($nested_files[$file_name] as $i => $ext) {
                $file_path = "$file_name.$ext";

                // beginning of the card container, knowing that the first file is always a plot
                if ($i == 0) {
                  echo "<div class=\"card text-center sortable-card\">";
                  echo "  <div class=\"card-header\">";
                  echo "    <a href=\"$file_path\">$file_name</a>";
                  echo "  </div>";
                  echo "  <a href=\"$file_path\">";
                  echo "    <img class=\"card-img-top\" src=\"$file_path\">";
                  echo "  </a>";
                  echo "  <div class=\"card-footer\">";
                  echo "    <p class=\"card-text\">";
                }
                // list all available extensions in footer
                $badge_style = "secondary";
                if ($ext == "png") {
                  $badge_style = "primary";
                } else if ($ext == "pdf") {
                  $badge_style = "info";
                } else if ($ext == "root") {
                  $badge_style = "dark";
                }
                echo "      <a href=\"$file_path\" class=\"badge rounded-pill bg-$badge_style\">$ext</a>";
              }
              // finish the card container
              echo "    </p>";
              echo "  </div>";
              echo "</div>";
            }
          }
        ?>
      </div>
    </div>

    <!-- list additional files -->
    <div id="file-listing" class="container-fluid">
      <h4><a id="files">Other files</a></h4>
      <?
        $file_names = array();
        foreach (glob("./$rel_dir/*") as $file_name) {
          $file_name_cleaned = preg_replace('#/+#','/',$file_name);
          $file_name_split_tmp = explode("/", $file_name_cleaned);
          $file_name_split = end($file_name_split_tmp);
          
          // Get file extension
          $ext = pathinfo($file_name_split, PATHINFO_EXTENSION);
          
          // Skip if extension is in either array
          if (in_array($ext, $plot_extensions) || in_array($ext, $additional_extensions)) {
            continue;
          }
          
          // Skip directories, index files, and manually skipped ones
          if (!is_file($file_name_cleaned) || $file_name_split == "index.php" || !show_entry($file_name_split)) {
            continue;
          }
          
          $file_names[] = $file_name_split;
        }

        if (count($file_names) == 0) {
          echo "<span class=\"empty-text\">No files to display</span>";
        } else {
          echo "<ul>";
          sort($file_names);
          foreach($file_names as $file_name) {
            echo "  <li><a href=\"" . htmlspecialchars($file_name) . "\">" . htmlspecialchars($file_name) . "</a></li>";
          }
          echo "</ul>";
        }
      ?>
    </div>

    <!-- scroll-to-top button -->
    <div class="container-fluid">
      <button type="button" class="btn btn-outline-primary btn-sm" id="scroll-top">To top</button>
    </div>
    
    <!-- Contact button -->
    <div id="contact-modal" class="modal" style="display:none;">
      <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Contact Us</h2>
          <p> Ioannis Paraskevas - <a href="mailto:ioannis.paraskevas@cern.ch">ioannis.paraskevas@cern.ch</a></p>
          <p> Nikolaos Plastiras - <a href="mailto:nikolaos.plastiras@cern.ch">nikolaos.plastiras@cern.ch</a></p>
          <p>Panagiotis Katris - <a href="mailto:panagiotis.katris@cern.ch">panagiotis.katris@cern.ch</a></p>
      </div>
    </div>


    <!-- footer -->
    <div id="footer">
      <a href="#" id="contact-button"><i class="bi bi-envelope"></i> Contact Us</a>
        |  
      <a href="https://gitlab.cern.ch/cms-analysis/general/php-plots"><i class="bi bi-code-slash"></i> Plot browser</a>
      <!--   |  
      <?php
        $version = "unknown version";
        $git_dir = dirname(__FILE__) . "/.git";
        if (is_dir($git_dir)) {
          $head_file = $git_dir . "/HEAD";
          if (file_exists($head_file)) {
            $head_content = trim(file_get_contents($head_file));
            if (preg_match('/^ref: (.+)$/', $head_content, $matches)) {
              $ref_file = $git_dir . "/" . $matches[1];
              if (file_exists($ref_file)) {
                $hash = trim(file_get_contents($ref_file));
                $version = "version " . substr($hash, 0, 7);
              }
            }
          }
        }
        echo $version;
      ?> -->
        |  
      <a href="https://cms-analysis.docs.cern.ch/guidelines/other/plot_browser"><i class="bi bi-info-circle"></i> Documentation</a>
    </div>
    <!-- include third-party scripts via cdns -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>


    <!-- inline scripts -->
    <script>
      $(document).ready(function() {
        // add event to scroll-to-top button
        $("#scroll-top").click(function() {
          window.scrollTo({top: 0, behavior: "smooth"});
        });
      });

      $(function () {
          $("#sortable").sortable({
              placeholder: "ui-state-highlight",
              items: ".sortable-card",
              cursor: "move"
          });
          $("#sortable").disableSelection();
      });
    </script>

    <script>
      document.getElementById('contact-button').addEventListener('click', function() {
        document.getElementById('contact-modal').style.display = 'flex';
      });

      document.querySelector('.close').addEventListener('click', function() {
        document.getElementById('contact-modal').style.display = 'none';
      });

      window.addEventListener('click', function(event) {
        if (event.target == document.getElementById('contact-modal')) {
          document.getElementById('contact-modal').style.display = 'none';
        }
      });
    </script>

  </body>
</html>
