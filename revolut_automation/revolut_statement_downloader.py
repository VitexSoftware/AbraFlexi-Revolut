import argparse
import glob
import logging
import os
import shutil
import time

from selenium import webdriver
from selenium.common.exceptions import TimeoutException, WebDriverException
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import WebDriverWait
from webdriver_manager.chrome import ChromeDriverManager

REVOLUT_APP_URL = "https://app.revolut.com/login"

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
log = logging.getLogger("revolut_statement_downloader")


class RevolutDownloadError(RuntimeError):
    """Raised when a required page step could not be completed."""


def wait_for_element(driver, by, value, timeout=60, description=None):
    """Wait for a single element to be present, raising RevolutDownloadError on timeout."""
    try:
        return WebDriverWait(driver, timeout).until(EC.presence_of_element_located((by, value)))
    except TimeoutException as exc:
        raise RevolutDownloadError(
            f"Timed out after {timeout}s waiting for {description or value}"
        ) from exc


def wait_for_new_file(download_dir, before_files, timeout=60):
    """Wait until a new, non-partial file appears in download_dir."""
    deadline = time.monotonic() + timeout
    while time.monotonic() < deadline:
        current_files = set(glob.glob(os.path.join(download_dir, "*")))
        new_files = [f for f in current_files - before_files if not f.endswith(".crdownload")]
        if new_files:
            return new_files[0]
        time.sleep(1)
    return None


def login(driver, pin: str):
    """Log in to app.revolut.com with the given PIN and wait for the mobile-confirmed redirect home."""
    driver.get(REVOLUT_APP_URL)

    log.info("Waiting for PIN input field...")
    pin_input = wait_for_element(driver, By.XPATH, "//input[@type='password']", description="PIN input field")
    log.info("Entering PIN...")
    pin_input.send_keys(pin)
    pin_input.send_keys(Keys.RETURN)

    log.info("Waiting for QR code to appear...")
    wait_for_element(
        driver, By.XPATH, "//img[contains(@src, 'qr') or contains(@alt, 'QR')]", description="login QR code"
    )
    log.info("QR code displayed. Please scan it with your Revolut mobile app and confirm login on your phone.")

    try:
        WebDriverWait(driver, 120).until(EC.url_contains("/home"))
        log.info("Login confirmed!")
    except TimeoutException as exc:
        raise RevolutDownloadError("Login not confirmed within 120s") from exc


def select_currency_account(driver, currency: str):
    """
    Best-effort switch of the active account/pocket to the given currency (e.g. 'CZK', 'EUR')
    before generating a statement. Revolut multi-currency accounts expose an account switcher
    listing each currency pocket; this looks for an entry whose text contains the currency code.
    Non-fatal if it can't be found - the statement flow will just run against whatever account
    is currently active.
    """
    log.info("Selecting %s account...", currency)
    try:
        switcher = wait_for_element(
            driver,
            By.XPATH,
            "//*[self::button or self::div or self::a][contains(., 'Accounts') or contains(., 'Účty')]",
            timeout=10,
            description="account switcher",
        )
        switcher.click()
        time.sleep(1)

        account_entry = wait_for_element(
            driver,
            By.XPATH,
            f"//*[self::button or self::div or self::a][contains(., '{currency}')]",
            timeout=10,
            description=f"{currency} account entry",
        )
        account_entry.click()
        time.sleep(1)
        return True
    except RevolutDownloadError:
        log.warning(
            "Could not automatically switch to the %s account. "
            "Make sure it is selected manually before the statement is generated.",
            currency,
        )
        return False


def generate_and_download_statement(driver, month_from: str, month_to: str, download_dir: str, currency: str = None):
    """Run the Statement -> CSV export -> date range -> Generate flow for the currently active account."""
    log.info("Navigating to Statement section...")
    statement_btn = wait_for_element(
        driver,
        By.XPATH,
        "//span[contains(text(),'Statement') or contains(text(),'Výpis')]",
        description="Statement button",
    )
    statement_btn.click()
    time.sleep(3)

    log.info("Selecting Excel/CSV export...")
    try:
        excel_btn = wait_for_element(
            driver,
            By.XPATH,
            "//button[contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'csv') "
            "or contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), 'excel')]",
            timeout=10,
            description="CSV/Excel export tab",
        )
        excel_btn.click()
        time.sleep(1)
    except RevolutDownloadError:
        log.warning("CSV/Excel export tab not found, continuing with default export format.")

    log.info("Setting date range...")
    try:
        start_input = wait_for_element(
            driver,
            By.XPATH,
            "//input[@aria-labelledby and contains(@value, '20') and contains(@class, 'InputBase')][1]",
            timeout=10,
            description="start date field",
        )
        end_input = wait_for_element(
            driver,
            By.XPATH,
            "//input[@aria-labelledby and contains(@value, '20') and contains(@class, 'InputBase')][2]",
            timeout=10,
            description="end date field",
        )
        start_input.clear()
        start_input.send_keys(month_from)
        end_input.clear()
        end_input.send_keys(month_to)
        time.sleep(1)
    except RevolutDownloadError:
        log.warning("Date range fields not found, continuing with the default range shown in the UI.")

    log.info("Generating statement...")
    generate_btn = wait_for_element(
        driver,
        By.XPATH,
        "//button[span[contains(text(),'Generate') or contains(text(),'Vygenerovat')]]",
        timeout=10,
        description="Generate button",
    )
    before_files = set(glob.glob(os.path.join(download_dir, "*")))
    generate_btn.click()
    log.info("Statement generation triggered.")

    log.info("Waiting for download to complete...")
    downloaded = wait_for_new_file(download_dir, before_files, timeout=60)
    if not downloaded:
        log.error("No new file appeared in %s within timeout.", download_dir)
        return None

    if currency:
        ext = os.path.splitext(downloaded)[1]
        renamed = os.path.join(download_dir, f"revolut-{currency}{ext}")
        shutil.move(downloaded, renamed)
        downloaded = renamed

    log.info("Downloaded statement: %s", downloaded)
    return downloaded


def download_revolut_statements(pin: str, month_from: str, month_to: str, currencies=None, download_dir: str = None):
    """
    Log in once and download one statement CSV per currency pocket.

    currencies: list of currency codes to fetch (e.g. ["CZK", "EUR"]). If omitted or empty,
    a single statement is downloaded for whatever account is active by default.

    Returns a dict {currency_or_None: downloaded_file_path_or_None}.
    """
    download_dir = download_dir or os.getcwd()
    os.makedirs(download_dir, exist_ok=True)
    currencies = currencies or [None]

    options = Options()
    options.add_argument("--start-maximized")
    options.add_experimental_option(
        "prefs",
        {
            "download.default_directory": download_dir,
            "download.prompt_for_download": False,
        },
    )

    driver = webdriver.Chrome(service=Service(ChromeDriverManager().install()), options=options)
    results = {}

    try:
        login(driver, pin)

        for currency in currencies:
            try:
                if currency:
                    select_currency_account(driver, currency)
                results[currency] = generate_and_download_statement(driver, month_from, month_to, download_dir, currency)
            except RevolutDownloadError as exc:
                log.error("Failed to download statement for %s: %s", currency or "default account", exc)
                results[currency] = None

        return results

    except RevolutDownloadError as exc:
        log.error(str(exc))
        return results
    except WebDriverException as exc:
        log.error("Browser automation error: %s", exc)
        return results
    finally:
        driver.quit()


def download_revolut_statement(pin: str, month_from: str, month_to: str, download_dir: str = None):
    """Backwards-compatible single-account helper."""
    results = download_revolut_statements(pin, month_from, month_to, currencies=None, download_dir=download_dir)
    return results.get(None)


def parse_args():
    parser = argparse.ArgumentParser(description="Download Revolut bank statement(s) via browser automation.")
    parser.add_argument("--pin", default=os.environ.get("REVOLUT_PIN"), help="Revolut login PIN")
    parser.add_argument("--month-from", default=os.environ.get("REVOLUT_MONTH_FROM"), help="Start month, e.g. 2025-10")
    parser.add_argument("--month-to", default=os.environ.get("REVOLUT_MONTH_TO"), help="End month, e.g. 2025-10")
    parser.add_argument("--download-dir", default=os.environ.get("REVOLUT_DOWNLOAD_DIR"), help="Download directory")
    parser.add_argument(
        "--currencies",
        default=os.environ.get("REVOLUT_CURRENCIES"),
        help="Comma-separated currency codes to download, e.g. CZK,EUR. "
        "Omit to download a single statement for the currently active account.",
    )
    return parser.parse_args()


if __name__ == "__main__":
    args = parse_args()
    pin = args.pin or input("Zadejte PIN pro Revolut: ")
    month_from = args.month_from or input("Zadejte počáteční měsíc (např. 2025-10): ")
    month_to = args.month_to or input("Zadejte koncový měsíc (např. 2025-10): ")
    download_dir = args.download_dir or (input("Zadejte adresář pro stažení (nepovinné): ") or None)
    currencies_raw = args.currencies
    if currencies_raw is None:
        currencies_raw = input("Zadejte měny oddělené čárkou, např. CZK,EUR (nepovinné): ") or None
    currencies = [c.strip() for c in currencies_raw.split(",") if c.strip()] if currencies_raw else None

    results = download_revolut_statements(pin, month_from, month_to, currencies, download_dir)
    failures = [c for c, path in results.items() if not path]
    for currency, path in results.items():
        label = currency or "default"
        log.info("%s: %s", label, path or "FAILED")

    raise SystemExit(1 if failures else 0)
