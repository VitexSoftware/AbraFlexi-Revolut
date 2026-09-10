# Revolut Statement Downloader

This tool uses Selenium to automate the download of bank statements from Revolut. Login uses Revolut's QR-code flow: the script opens the login page, shows the QR code, and waits for you to scan it with your phone and confirm — no PIN or password is entered by the script.

## Usage

### Debian/Ubuntu package

```bash
sudo apt install abraflexi-revolut-statement-downloader
```

This installs the `revolut-statement-downloader` command plus its dependencies (`python3-selenium`, and either `gecko-driver` or `chromium-driver` — whichever is already installed, or `gecko-driver` if neither is).

### Manual install

1. Install dependencies: Python 3, [Selenium](https://pypi.org/project/selenium/), and a browser driver on PATH — either `geckodriver` (Firefox, Debian package `gecko-driver`) or `chromedriver` (Chrome/Chromium, Debian package `chromium-driver`); e.g. `sudo apt install python3-selenium gecko-driver` (or `chromium-driver`). The tool auto-detects whichever is installed, preferring `geckodriver` when both are present. Force a specific one with `REVOLUT_BROWSER=firefox` or `REVOLUT_BROWSER=chrome`, or point `REVOLUT_GECKODRIVER`/`REVOLUT_CHROMEDRIVER` at a binary that isn't on PATH.
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

This produces one file per currency in the download directory, named e.g.
`revolut-CZK-2025-10_2025-10.csv` and `revolut-EUR-2025-10_2025-10.csv`
(currency + date range). Feed each one separately into
`abraflexi-revolut-csv-import` — a Revolut account shares a single IBAN across
all its currency pockets, so **both files go against the same `ACCOUNT_IBAN` /
`.env`**; each transaction carries its own currency from the CSV, and
re-running the importer on overlapping files is safe (already-imported
transactions are deduplicated and skipped).

The account switcher is located via Revolut's `Select account` button, then
matched by currency **name** (e.g. "Euro", "Czech koruna" — Revolut lists
pockets by name, not ISO code) using a small built-in mapping for common
currencies. If Revolut's UI doesn't match — e.g. a currency outside that
mapping, or the switcher itself changed — the script logs a warning and
continues against whatever account was already active; check the log.

### A note on Revolut UI changes

Revolut's web app is a moving target with no stable automation hooks (no
stable IDs, and even CSS classes can be non-deterministic build hashes rather
than fixed names). Things already broken and fixed once, which may break again
in a different form:

- **Login**: moved to a phone-number-first SSO page with a QR code shown
  immediately (no PIN step). Revolut can also silently re-authenticate an
  already-trusted browser straight past the QR screen — the script detects
  both cases rather than only waiting for a QR code.
- **Cookie banner**: intercepts every click until dismissed; handled
  automatically.
- **Date range**: the "Starting on"/"Ending on" fields are a month/year picker
  grid, not text inputs — typing into them silently does nothing.
- **Download**: some accounts auto-download right after "Generate"; others
  show a "Statement is ready" sheet with its own Download button. Both are handled.

If a step still stops working after a Revolut UI update, the log message
names which step failed (e.g. "Timed out ... waiting for Statement button") —
that's the selector to fix first. Setting `REVOLUT_DEBUG_DIR=/path/to/dir`
saves a screenshot and the page HTML at each step, which is the fastest way to
see the actual current markup.
