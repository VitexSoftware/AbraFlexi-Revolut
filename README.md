# AbraFlexi Revolut Statements Import

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-blue.svg)](https://php.net)
[![MultiFlexi Ready](https://img.shields.io/badge/MultiFlexi-Ready-green.svg)](https://multiflexi.eu)

Import Revolut bank statements (CSV format) into AbraFlexi accounting system.

![Logo](abraflexi-revolut-social-preview.svg?raw=true)

## Features

- **CSV Import**: Import Revolut bank statements from CSV files
- **AbraFlexi Integration**: Direct integration with AbraFlexi accounting system
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

wget -qO- https://repo.vitexsoftware.com/keyring.gpg | sudo tee /etc/apt/trusted.gpg.d/vitexsoftware.gpg
echo "deb [signed-by=/etc/apt/trusted.gpg.d/vitexsoftware.gpg]  https://repo.vitexsoftware.com  $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/vitexsoftware.list
sudo apt update

# Install the package
sudo apt install abraflexi-revolut
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

The Revolut CSV export should contain the following columns:
- Date
- Reference
- Paid out/in
- Exchange rate
- Paid out/in (original currency)
- Fee
- Description
- Balance

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

- **spojenet/flexibee** (^2025.7): AbraFlexi PHP library
- **vitexsoftware/ease-core** (^1.48): Core functionality and utilities

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
