# Revolut Statement Downloader

This tool uses Selenium to automate the download of bank statements from Revolut. Login uses Revolut's QR-code flow: the script opens the login page, shows the QR code, and waits for you to scan it with your phone and confirm — no PIN or password is entered by the script.

## Usage

### Debian/Ubuntu package

```bash
sudo apt install abraflexi-revolut-statement-downloader
```

This installs the `revolut-statement-downloader` command plus its dependencies (`python3-selenium`, `chromium-driver`).

### Manual install

1. Install dependencies: Python 3, [Selenium](https://pypi.org/project/selenium/), and a `chromedriver` binary on PATH (on Debian/Ubuntu, `sudo apt install python3-selenium chromium-driver`; otherwise a virtualenv with `pip install selenium` plus a matching chromedriver works too — point `REVOLUT_CHROMEDRIVER` at it if it's not on PATH).
2. Run the script (`./revolut-statement-downloader` if installed via the .deb, or `python3 revolut-statement-downloader` from a checkout), either interactively:
   ```bash
   revolut-statement-downloader
   ```
   or non-interactively via arguments/environment variables (useful for scripted/scheduled runs, though the QR scan-and-confirm step still requires a human):
   ```bash
   revolut-statement-downloader --month-from 2025-10 --month-to 2025-10 --download-dir /path/to/downloads
   # or
   REVOLUT_MONTH_FROM=2025-10 REVOLUT_MONTH_TO=2025-10 REVOLUT_DOWNLOAD_DIR=/path/to/downloads \
     revolut-statement-downloader
   ```
3. When the QR code appears, scan it with the Revolut mobile app and confirm the login on your phone. The script auto-refreshes the QR code if it expires before you scan it (waits up to 180s by default).

The tool will log in, navigate to the statements section, generate the statement for the given range, and wait for the download to finish (verified by watching the download directory, not a fixed sleep). It exits with status `0` on success and `1` if any step failed or timed out — check the log output for the exact reason.

### Multi-currency accounts

If your Revolut account holds balances in more than one currency (e.g. CZK and EUR), each currency pocket has its own statement export. Pass `--currencies` (or `REVOLUT_CURRENCIES`) as a comma-separated list to log in once and download one CSV per currency:

```bash
revolut-statement-downloader --month-from 2025-10 --month-to 2025-10 \
  --download-dir /path/to/downloads --currencies CZK,EUR
```

This produces `revolut-CZK.csv` and `revolut-EUR.csv` in the download directory (feed each one separately into `abraflexi-revolut-csv-import` — the AbraFlexi importer expects one CSV per bank account/IBAN, matching each currency pocket to its own `ACCOUNT_IBAN`). The account switcher is located by matching the currency code in the account list; if Revolut's UI doesn't match, the script logs a warning and continues against whatever account was already active, so check the log when using an unfamiliar layout for the first time.

### A note on Revolut UI changes

Revolut's web app is a moving target — the login flow, statement export dialog, and account switcher are all matched by best-effort selectors (text content, partial class names) rather than stable IDs, because Revolut doesn't expose stable IDs for automation. If a step stops working after a Revolut UI update, the log message will name which step failed (e.g. "Timed out ... waiting for Statement button"); that's the selector to fix first.
