# Revolut Statement Downloader

This tool uses Selenium to automate the download of bank statements from Revolut. The only manual step required is to confirm login on your mobile device when prompted by Revolut.

## Usage

1. Install Python dependencies:
   ```bash
   pip install selenium webdriver-manager
   ```
2. Run the script:
   ```bash
   python revolut_statement_downloader.py
   ```
3. When prompted, confirm the login on your mobile device.

The tool will automatically log in, navigate to the statements section, and download the latest statement.
