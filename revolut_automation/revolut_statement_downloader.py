import time
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.chrome.service import Service
from webdriver_manager.chrome import ChromeDriverManager
from selenium.webdriver.chrome.options import Options

REVOLUT_APP_URL = "https://app.revolut.com/login"

def wait_for_element(driver, by, value, timeout=60):
    for _ in range(timeout):
        try:
            el = driver.find_element(by, value)
            return el
        except Exception:
            time.sleep(1)
    return None

def download_revolut_statement(pin: str, month_from: str, month_to: str, download_dir: str = None):
    options = Options()
    options.add_argument('--start-maximized')
    if download_dir:
        prefs = {"download.default_directory": download_dir}
        options.add_experimental_option("prefs", prefs)
    driver = webdriver.Chrome(service=Service(ChromeDriverManager().install()), options=options)
    driver.get(REVOLUT_APP_URL)

    # Wait for PIN input
    print("Waiting for PIN input field...")
    pin_input = wait_for_element(driver, By.XPATH, "//input[@type='password']", timeout=60)
    if not pin_input:
        print("PIN input not found. Exiting.")
        driver.quit()
        return
    print("Entering PIN...")
    pin_input.send_keys(pin)
    pin_input.send_keys(Keys.RETURN)

    # Wait for QR code
    print("Waiting for QR code to appear...")
    qr_found = wait_for_element(driver, By.XPATH, "//img[contains(@src, 'qr') or contains(@alt, 'QR')]", timeout=60)
    if not qr_found:
        print("QR code not found. Exiting.")
        driver.quit()
        return
    print("QR code displayed. Please scan it with your Revolut mobile app and confirm login on your phone.")

    # Wait for redirect to /home (after mobile confirmation)
    for _ in range(120):
        if "/home" in driver.current_url:
            print("Login confirmed!")
            break
        time.sleep(2)
    else:
        print("Login not confirmed in time. Exiting.")
        driver.quit()
        return

    # Wait for the Statement section/button
    print("Navigating to Statement section...")
    statement_btn = wait_for_element(driver, By.XPATH, "//span[contains(text(),'Statement') or contains(text(),'Výpis')]", timeout=60)
    if not statement_btn:
        print("Statement button not found. Exiting.")
        driver.quit()
        return
    statement_btn.click()
    time.sleep(3)

    # Select Excel/CSV tab
    print("Selecting Excel/CSV export...")
    excel_btn = wait_for_element(driver, By.XPATH, "//button[contains(text(),'Excel') or contains(text(),'CSV') or contains(text(),'XLS') or contains(text(),'csv') or contains(text(),'xls') or contains(text(),'excel') or contains(text(),'EXCEL')]", timeout=10)
    if excel_btn:
        excel_btn.click()
        time.sleep(1)

    # Set date range
    print("Setting date range...")
    start_input = wait_for_element(driver, By.XPATH, "//input[@aria-labelledby and contains(@value, '20') and contains(@class, 'InputBase')][1]", timeout=10)
    end_input = wait_for_element(driver, By.XPATH, "//input[@aria-labelledby and contains(@value, '20') and contains(@class, 'InputBase')][2]", timeout=10)
    if start_input and end_input:
        start_input.clear()
        start_input.send_keys(month_from)
        end_input.clear()
        end_input.send_keys(month_to)
        time.sleep(1)

    # Click Generate
    print("Generating statement...")
    generate_btn = wait_for_element(driver, By.XPATH, "//button[span[contains(text(),'Generate') or contains(text(),'Vygenerovat')]]", timeout=10)
    if generate_btn:
        generate_btn.click()
        print("Statement generation triggered.")
    else:
        print("Generate button not found.")

    # Wait for download to complete (or for download link to appear)
    print("Waiting for download...")
    time.sleep(10)
    driver.quit()

if __name__ == "__main__":
    user_pin = input("Zadejte PIN pro Revolut: ")
    month_from = input("Zadejte počáteční měsíc (např. 2025-10): ")
    month_to = input("Zadejte koncový měsíc (např. 2025-10): ")
    download_dir = input("Zadejte adresář pro stažení (nepovinné): ") or None
    download_revolut_statement(user_pin, month_from, month_to, download_dir)
