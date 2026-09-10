# AbraFlexi Revolut Statements Import

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-blue.svg)](https://php.net)
[![MultiFlexi Ready](https://img.shields.io/badge/MultiFlexi-Ready-green.svg)](https://multiflexi.eu)
![Packaging: deb](https://img.shields.io/badge/packaging-.deb-red?logo=debian&logoColor=white)

Import Revolut bank statements (CSV format) into AbraFlexi accounting system.

![Logo](abraflexi-revolut-social-preview.svg?raw=true)

## Features

- **CSV Import**: Import Revolut bank statements from CSV files
- **AbraFlexi Integration**: Direct integration with AbraFlexi accounting system
- **Automated Statement Download**: Optional browser-automated downloader logs into Revolut via QR code and fetches CSV statements for you — see [Automated Statement Download](#automated-statement-download)
- **Multi-currency**: Handles accounts with several currency pockets (e.g. CZK + EUR), one CSV per currency
- **MultiFlexi Compatible**: Ready to run as a MultiFlexi application
- **Docker Support**: Available as Docker container
- **Automated Setup**: Includes setup script for easy configuration
- **Logging**: Configurable logging (syslog/console)
- **Debug Mode**: Optional debug mode for troubleshooting

## Requirements

- PHP 8.0 or higher
- AbraFlexi server
- Revolut CSV export file

## Configuration

### Environment Variables

#### Required Configuration

```ini
# AbraFlexi Server Configuration
ABRAFLEXI_URL="https://demo.flexibee.eu:5434"
ABRAFLEXI_LOGIN="winstrom"
ABRAFLEXI_PASSWORD="winstrom"
ABRAFLEXI_COMPANY="demo_de"

# Revolut Account Configuration
ACCOUNT_IBAN="EUXX XXXX XXXX XXXX XXXX"

# Input File
REVOLUT_CSV="/path/to/revolut-statement.csv"
```

#### Optional Configuration

```ini
# Debug and Logging
APP_DEBUG=false
EASE_LOGGER="syslog|console"

# Output Configuration
RESULT_FILE="revolut-import-{ACCOUNT_IBAN}.csv"
```

### Legacy Configuration (INI format)

For backward compatibility, you can also use INI file configuration:

```ini
# Additional legacy options
DOCUMENT_TYPE=STAND
DOCUMENT_NUMROW=REVO+
EMAIL_FROM=fbchanges@localhost
SEND_INFO_TO=admin@localhost
```


## Installation

### Debian/Ubuntu Package Installation

There is a repository available for Debian/Ubuntu Linux distributions:

```shell
# Add VitexSoftware repository
sudo apt install lsb-release wget apt-transport-https bzip2

wget -qO- https://repo.vitexsoftware.com/KEY.gpg | sudo tee /etc/apt/trusted.gpg.d/vitexsoftware.gpg
echo "deb [signed-by=/etc/apt/trusted.gpg.d/vitexsoftware.gpg]  https://repo.vitexsoftware.com  $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/vitexsoftware.list
sudo apt update

# Install the package
sudo apt install abraflexi-revolut

# Optional: browser-automated statement downloader (see "Automated Statement
# Download" below) - a separate package built from this same source
sudo apt install abraflexi-revolut-statement-downloader
```

### Composer Installation

```shell
composer require vitexsoftware/abraflexi-revolut
```

### Docker Installation

```shell
# Pull the Docker image
docker pull docker.io/vitexsoftware/abraflexi-revolut

# Run with environment variables
docker run -e ABRAFLEXI_URL="https://your-server.com:5434" \
           -e ABRAFLEXI_LOGIN="your-login" \
           -e ABRAFLEXI_PASSWORD="your-password" \
           -e ABRAFLEXI_COMPANY="your-company" \
           -e ACCOUNT_IBAN="your-iban" \
           -v /path/to/revolut.csv:/data/revolut.csv \
           vitexsoftware/abraflexi-revolut /data/revolut.csv
```

## Usage

### Command Line Usage

After installation, you can use the following commands:

#### Import CSV File
```shell
# Basic usage
abraflexi-revolut-csv-import /path/to/revolut-statement.csv

# With environment variables
ABRAFLEXI_URL="https://your-server.com:5434" \
ABRAFLEXI_LOGIN="your-login" \
ABRAFLEXI_PASSWORD="your-password" \
ABRAFLEXI_COMPANY="your-company" \
ACCOUNT_IBAN="your-iban" \
abraflexi-revolut-csv-import /path/to/revolut-statement.csv
```

#### Setup Configuration
```shell
# Run setup wizard
abraflexi-revolut-setup
```

### CSV File Format

Both English and Czech Revolut CSV exports are supported.

#### English Format
| Column | Example |
|--------|--------|
| Type | TOPUP, CARD_PAYMENT, FEE, TRANSFER, CARD_REFUND |
| Product | Current |
| Started Date | 2025-09-04 08:04:10 |
| Completed Date | 2025-09-04 08:04:11 |
| Description | Payment from John |
| Amount | 163.68 (positive=income, negative=expense) |
| Fee | 0.00 |
| Currency | EUR, CZK |
| State | COMPLETED |
| Balance | 170.09 |

#### Czech Format (cs / cs-cz)
| Sloupec | Příklad |
|---------|--------|
| Typ | Dobíjení, Platba kartou, Poplatek, Převod, Vrácení peněz na kartu |
| Produkt | Aktuální |
| Datum zahájení | 2025-09-04 08:04:10 |
| Datum dokončení | 2025-09-04 08:04:11 |
| Popis | Platba od HANA DVORAKOVA |
| Částka | 163.68 (kladná=příjem, záporná=výdaj) |
| Poplatek | 0.00 |
| Měna | EUR, CZK |
| State | DOKONČENO |
| Zůstatek | 170.09 |

#### Supported Transaction Types

Both `SCREAMING_SNAKE_CASE` (older CSV exports) and `Title Case` (current Revolut
exports) forms of the English type are recognized.

| English | Czech | Direction |
|---------|-------|-----------|
| TOPUP / Topup | Dobíjení | Income |
| DEPOSIT / Deposit | Vklad | Income |
| REVERTED | — | Income |
| CARD_PAYMENT / Card Payment | Platba kartou | Expense |
| FEE | Poplatek | Expense |
| TRANSFER / Transfer / Rev Payment | Převod | Depends on amount sign |
| CARD_REFUND | Vrácení peněz na kartu | Skipped |
| TEMP_BLOCK | — | Skipped |

Any other transaction type is logged as a warning and skipped — check the import
output if a transaction seems to be missing (this is exactly how a real gap was
found and fixed: `Deposit` rows, e.g. incoming bank transfers, were silently
skipped before the mapping above was added).

## Automated Statement Download

Downloading the CSV statement from Revolut by hand is optional — the
[`revolut_automation/`](revolut_automation/) tool automates it with a browser:
it logs into Revolut using the QR-code login flow (scan with your phone, no
password/PIN typed by the script), navigates to the Statement export, and
downloads one CSV per currency for accounts with more than one currency pocket
(e.g. CZK + EUR), ready to feed straight into `abraflexi-revolut-csv-import`.

```shell
# Debian/Ubuntu package (installed above)
revolut-statement-downloader --month-from 2025-10 --month-to 2025-10 \
  --download-dir /path/to/downloads --currencies CZK,EUR
```

It auto-detects whichever browser driver is installed — Firefox (`geckodriver`,
Debian package `gecko-driver`) is preferred when present, falling back to
Chrome/Chromium (`chromedriver`, package `chromium-driver`). See
[`revolut_automation/README.md`](revolut_automation/README.md) for full usage,
environment variables, and troubleshooting notes (Revolut's login/statement
pages change from time to time; that file documents the current selectors and
how to diagnose a broken step).

## MultiFlexi Integration

AbraFlexi Revolut is ready to run as a [MultiFlexi](https://multiflexi.eu) application.

[![MultiFlexi App](https://github.com/VitexSoftware/MultiFlexi/blob/main/doc/multiflexi-app.svg)](https://www.multiflexi.eu/apps.php)

### MultiFlexi Configuration

The application is configured via the MultiFlexi interface with the following parameters:

- **Name**: AbraFlexi Revolut statements import
- **Description**: Import Revolut bank statements into AbraFlexi
- **Topics**: Revolut, Statement, Importer
- **Requirements**: AbraFlexi
- **Minimum MultiFlexi Version**: 1.27+

See the full list of ready-to-run applications within the MultiFlexi platform on the [application list page](https://www.multiflexi.eu/apps.php).

## Development

### Dependencies

This project uses the following main dependencies:

- **spojenet/flexibee** (^3.7): AbraFlexi PHP library
- **vitexsoftware/ease-core** (^1.50): Core functionality and utilities

### Development Tools

- **PHPUnit**: Unit testing framework
- **PHPStan**: Static analysis tool
- **PHP-CS-Fixer**: Code style fixer
- **Composer Normalize**: Composer.json normalization

### Running Tests

```shell
composer install --dev
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

- **Homepage**: https://github.com/VitexSoftware/AbraFlexi-Revolut
- **Issues**: https://github.com/VitexSoftware/AbraFlexi-Revolut/issues
- **Author**: VítězslaV Dvořák <info@vitexsoftware.cz>
- **Company**: [VitexSoftware](https://vitexsoftware.com)

## Exit Codes

This application uses the following exit codes:

- `0`: Success
- `1`: General error
