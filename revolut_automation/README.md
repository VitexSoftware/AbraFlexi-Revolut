# Revolut Statement Downloader

This tool uses Selenium to automate the download of bank statements from Revolut. The only manual step required is to confirm login on your mobile device when prompted by Revolut.

## Usage

1. Install Python dependencies:
   ```bash
   pip install selenium webdriver-manager
   ```
2. Run the script, either interactively:
   ```bash
   python revolut_statement_downloader.py
   ```
   or non-interactively via arguments/environment variables (useful for scripted/scheduled runs, though the mobile confirmation step still requires a human):
   ```bash
   python revolut_statement_downloader.py --pin 1234 --month-from 2025-10 --month-to 2025-10 --download-dir /path/to/downloads
   # or
   REVOLUT_PIN=1234 REVOLUT_MONTH_FROM=2025-10 REVOLUT_MONTH_TO=2025-10 REVOLUT_DOWNLOAD_DIR=/path/to/downloads \
     python revolut_statement_downloader.py
   ```
3. When prompted, confirm the login on your mobile device.

The tool will log in, navigate to the statements section, generate the statement for the given range, and wait for the download to finish (verified by watching the download directory, not a fixed sleep). It exits with status `0` on success and `1` if any step failed or timed out — check the log output for the exact reason.
