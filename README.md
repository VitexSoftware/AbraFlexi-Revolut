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

