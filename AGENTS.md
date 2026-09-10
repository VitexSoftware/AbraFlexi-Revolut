# AGENTS.md - Working AI Reference for AbraFlexi-Revolut

## Project Overview

**Type**: PHP project + standalone Python tool, packaged as three Debian binary packages from one source
**Purpose**: Import Revolut bank statement CSVs into the AbraFlexi accounting system
**Status**: Active
**Repository**: git@github.com:VitexSoftware/AbraFlexi-Revolut.git
**Branches**: develop on `main`; releases are cut from `production` (see `debian/Jenkinsfile.release` — this repo requires the `production`-branch release flow, not a plain `main` release)

## Architecture & Structure

```txt
AbraFlexi-Revolut/
├── src/                      # PHP: RevolutCsvHelper.php (pure logic, unit-tested)
│                              #      + abraflexi-revolut-csv-import.php (CLI entrypoint)
│                              #      + abraflexi-revolut-setup.php
├── bin/                       # Thin shell wrappers installed to /usr/bin
├── tests/                     # PHPUnit tests for RevolutCsvHelper
├── revolut_automation/        # Standalone Python tool: browser-automated statement
│                              # download (Selenium, QR-code login), own README.md
├── multiflexi/                # MultiFlexi app manifest (abraflexi_revolut.multiflexi.app.json)
├── debian/                    # Packaging for 3 binary packages (see below)
└── .env / .env.example        # Runtime config (AbraFlexi creds, ACCOUNT_IBAN, etc.)
```

### The three Debian packages (one source, `debian/control`)

- `abraflexi-revolut` — the PHP CSV importer (`abraflexi-revolut-csv-import`,
  `abraflexi-revolut-setup`). Uses a hand-written Debian-native autoloader
  (`debian/autoload.php` → installed at `/usr/share/php/abraflexi-revolut/autoload.php`),
  **not** Composer's `vendor/autoload.php` and **not** phpab-generated. See
  `debian/README.Debian` for the real installed paths.
- `multiflexi-abraflexi-revolut` — registers the app in a MultiFlexi instance
  (depends on `abraflexi-revolut`).
- `abraflexi-revolut-statement-downloader` — the Python downloader from
  `revolut_automation/`, packaged separately. Depends on `python3-selenium` and
  `gecko-driver | chromium-driver` (either satisfies it; the script auto-detects
  whichever is installed and prefers `geckodriver`/Firefox).

## Key Technologies

- PHP 8+, Composer (`spojenet/flexibee`, `vitexsoftware/ease-core`)
- Python 3, Selenium (no `webdriver-manager` — deliberately dropped so the tool
  can depend on the system `chromium-driver`/`gecko-driver` package instead of pip)
- Debian packaging (`debhelper-compat = 11`, `pkg-php-tools`)
- MultiFlexi app manifest

## Development Workflow

### Setup

```bash
git clone git@github.com:VitexSoftware/AbraFlexi-Revolut.git
cd AbraFlexi-Revolut
composer install
cp .env.example .env   # fill in ABRAFLEXI_* and ACCOUNT_IBAN
```

### Testing

```bash
vendor/bin/phpunit tests/          # PHP - the only automated test suite in this repo
python3 -m py_compile revolut_automation/revolut-statement-downloader  # syntax check only, no Python tests
```

There is no automated test coverage for `revolut_automation/` — it drives a real
browser against Revolut's live site, so changes there need a manual live run to
verify (see "Revolut UI changes" below).

### Build packages

```bash
dpkg-buildpackage -us -uc -b
```

Builds all three binary packages listed above in one pass.

### Release

Use the `/release` skill/slash-command, not a manual `git tag` + `gh release`.
This is a MultiFlexi-style project (`debian/Jenkinsfile.release` present): releases
must be made from the `production` branch, which the release flow fast-forwards
from `main`. Releasing from `main` directly targets the wrong Jenkins pipeline.

## Key Concepts

- **Currency handling**: a Revolut account's IBAN is shared across all its
  currency pockets (one IBAN, multiple currencies) — the AbraFlexi bank account
  record is matched by `ACCOUNT_IBAN`, and each imported transaction carries its
  own currency from the CSV's `Currency`/`Měna` column. Import one CSV per
  currency against the same `.env`/`ACCOUNT_IBAN`; they don't conflict.
- **Deduplication**: `abraflexi-revolut-csv-import.php` derives a stable
  `ext:rev:<hash>` id per transaction (`RevolutCsvHelper::buildExternalId`) and
  skips rows whose id already exists in AbraFlexi — safe to re-run the importer
  on the same or overlapping CSV files.
- **Transaction type mapping**: `RevolutCsvHelper::resolveMovementType()` is the
  single source of truth for which Revolut transaction types map to income vs.
  expense vs. skip. An unrecognized type is logged as a warning and the
  transaction is **silently skipped** — this has caused real missed income
  before (`Deposit` rows went unimported until the mapping was extended). If an
  import run logs "Unknown transaction type X", that's a real gap to fix here,
  not routine noise.
- **CLI argument parsing gotcha**: `bin/abraflexi-revolut-csv-import` invokes
  `php -f script.php $@` with no `--` separator, so PHP's own CLI parses flags
  like `-i` before the script gets a chance to. Known workaround:
  `php -f abraflexi-revolut-csv-import.php -- -e/path/.env -i/path/in.csv -o/path/out.json`
  (short options, value attached with no space, real `--` separator). The
  script's `getopt()` long-option spec is also malformed (`['input::environment::output::']`
  is one string, not three array entries), so `--input=`/`--environment=`/`--output=`
  never work at all — this is a real unfixed bug, not a documentation gap.

## Revolut UI changes

Revolut's web app is a moving target with no stable automation hooks; expect
`revolut_automation/revolut-statement-downloader` to break periodically. Known
failure modes already hit and fixed once (may recur in a different form):

- Login flow moved from `app.revolut.com` to a phone-number-first SSO page
  (`sso.revolut.com/signin`) with a QR code shown immediately, no PIN step.
- The QR `<svg>`'s CSS class is a non-deterministic build hash, not a stable
  name — match by `aria-label` (the QR challenge URL) instead of class.
- Revolut can silently re-authenticate an already-trusted browser straight to
  `/home`, skipping the QR screen entirely — treat that as success, not a timeout.
- A cookie-consent banner intercepts clicks until dismissed.
- The account switcher lists currency pockets by **name** ("Euro", "Czech
  koruna"), not by ISO code.
- The statement date picker is a month/year grid (`aria-label="September 2025"`
  per cell), not a text input — filling it like a text field silently does nothing.
- Some accounts show a "Statement is ready" sheet with its own Download button
  after clicking Generate; others download immediately. Handle both.

When a step breaks, `REVOLUT_DEBUG_DIR=/path python3 revolut-statement-downloader ...`
saves a screenshot + page source at each step — the fastest way to see the
actual current markup and fix the selector.

## Support

- **Issues**: https://github.com/VitexSoftware/AbraFlexi-Revolut/issues
- **Author**: Vítězslav Dvořák <info@vitexsoftware.cz>
