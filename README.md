# AbraFlexi-Revolut
Import revolut transactions into AbraFlexi

![Logo](abraflexi-revolut-social-preview.svg?raw=true)

Configuration:

```ini
ABRAFLEXI_URL="https://demo.flexibee.eu:5434"
ABRAFLEXI_LOGIN="winstrom"
ABRAFLEXI_PASSWORD="winstrom"
ABRAFLEXI_COMPANY="demo_de"

ACCOUNT_IBAN="EUXX XXXX XXXX XXXX XXXX"

DOCUMENT_TYPE=STAND
DOCUMENT_NUMROW=REVO+
```

Optional config keys:

```ini
APP_DEBUG=True
EASE_LOGGER=syslog|console
EMAIL_FROM=fbchanges@localhost
SEND_INFO_TO=admin@localhost
```


Installation
------------

There is repository for Debian/Ubuntu Linux distributions:

```shell
sudo apt install lsb-release wget apt-transport-https bzip2

wget -qO- https://repo.vitexsoftware.com/keyring.gpg | sudo tee /etc/apt/trusted.gpg.d/vitexsoftware.gpg
echo "deb [signed-by=/etc/apt/trusted.gpg.d/vitexsoftware.gpg]  https://repo.vitexsoftware.com  $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/vitexsoftware.list
sudo apt update

sudo apt install abraflexi-revolut
```

MultiFlexi
----------

AbraFlexi Revolut is ready for run as [MultiFlexi](https://multiflexi.eu) application.
See the full list of ready-to-run applications within the MultiFlexi platform on the [application list page](https://www.multiflexi.eu/apps.php).

[![MultiFlexi App](https://github.com/VitexSoftware/MultiFlexi/blob/main/doc/multiflexi-app.svg)](https://www.multiflexi.eu/apps.php)
