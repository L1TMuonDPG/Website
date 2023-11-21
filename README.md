# php-plots

PHP based plot browser for EOS (sub)directories via web.cern.ch.

For detailed setup and usage instructions, see the [CAT documentation](https://cms-analysis.docs.cern.ch/guidelines/other/plot_browser).

The current project supersedes a previous version of the plot browser `index.php` script.
It can still be accessed through the [`old_version` branch](https://gitlab.cern.ch/cms-analysis/general/php-plots/-/tree/old_version), however, please mind the potential outdated instructions.

## Settings of the main `index.php` file

The main `index.php` file contains a few settings at the top of the file that can be configured according to your needs.

- `$main_extension`: Extension of plot files to show in cards. Defaults to `"png"``.
- `$additional_extensions`: Additional extensions to link in card footer if existing. Defaults to `("png", "pdf", "cxx", "eps", "root", "txt")`.
- `$search_mode`: The search mode in case one or multiple search patterns are provided. Defaults to `"any"`.
  - `"any"`: Any search pattern must match.
  - `"all"`: All search patterns must match.
  - `"exact"`: The search pattern must match as is.

## Additional scripts

The `index.php` file is meant to be copied (or symlinked) into every subdirectory that should have a plot browser.
In addition, plots for your analysis location might need to be copied into a `www` directory in your EOS user space from where website content can be served.
A handful of scripts (prefixed with `pb` for plot browser) are provided to help you with the deployment of files.

### `bin/pb_copy_index.py`

```shell
> pb_copy_index.py --help

usage: pb_copy_index.py [-h] [--recursive] directories [directories ...]

Copies the index.php file of the plot browser to various directories.

positional arguments:
  directories      the directories to copy the index.php file to

optional arguments:
  -h, --help       show this help message and exit
  --recursive, -r  copy the index.php file recursively into all subdirectories
```

### `bin/pb_pdf_to_png.py`

```shell
> pb_pdf_to_png.py --help

usage: pb_pdf_to_png.py [-h] [--recursive] [--cores CORES] paths [paths ...]

Converts one or multiple pdf files to png using "pdftocairo".

positional arguments:
  paths                 files to convert or directories to check for pdf files

optional arguments:
  -h, --help            show this help message and exit
  --recursive, -r       convert pdf files recursively in all subdirectories
  --cores CORES, -j CORES
                        number of cores to use for parallel conversion
```

### `bin/pb_deploy_plots.py`

```shell
> pb_deploy_plots.py --help

usage: pb_deploy_plots.py [-h] [--extensions EXTENSIONS] [--pdf-to-png] [--recursive] [--cores CORES]
                          sources [sources ...] destination

Copies images recursively to a target directory, adds plot browser index files to all newly created directories, and optionally
converts pdf files to png.

positional arguments:
  sources               source files or directories to check for plots
  destination           target directory to copy files to

optional arguments:
  -h, --help            show this help message and exit
  --extensions EXTENSIONS, -e EXTENSIONS
                        comma-separated extensions of files to copy; default: ('png', 'pdf', 'jpg', 'jpeg', 'gif', 'eps',
                        'svg', 'root', 'cxx', 'txt', 'rtf', 'log')
  --pdf-to-png, -c      convert pdf files to png
  --recursive, -r       convert pdf files recursively in all subdirectories
  --cores CORES, -j CORES
                        number of cores to use for parallel conversion of pdf files
```
