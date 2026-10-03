# vim: set tabstop=8 softtabstop=8 noexpandtab:
.PHONY: help
help: ## Displays this list of targets with descriptions
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}'

.PHONY: static-code-analysis
static-code-analysis: vendor ## Runs a static code analysis with phpstan/phpstan
	vendor/bin/phpstan analyse --configuration=phpstan-default.neon.dist --memory-limit=-1

.PHONY: static-code-analysis-baseline
static-code-analysis-baseline: check-symfony vendor ## Generates a baseline for static code analysis with phpstan/phpstan
	vendor/bin/phpstan analyze --configuration=phpstan-default.neon.dist --generate-baseline=phpstan-default-baseline.neon --memory-limit=-1

.PHONY: tests
tests: vendor
	vendor/bin/phpunit tests

.PHONY: vendor
vendor: composer.json composer.lock ## Installs composer dependencies
	composer install

.PHONY: cs
cs: ## Update Coding Standards
	vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --diff --verbose

buildimage:
	docker build -f Containerfile  -t vitexsoftware/abraflexi-revolut:latest .

buildx:
	docker buildx build  -f Containerfile  . --push --platform linux/arm/v7,linux/arm64/v8,linux/amd64 --tag vitexsoftware/abraflexi-revolut:latest

drun:
	docker run  -f Containerfile --env-file .env vitexsoftware/abraflexi-revolut:latest

# Optional overrides: MONTH_FROM=YYYY-MM MONTH_TO=YYYY-MM DOWNLOAD_DIR=path CURRENCIES=CZK,EUR LOGIN_TIMEOUT=600
MONTH_FROM ?=
MONTH_TO ?=
DOWNLOAD_DIR ?= downloads
CURRENCIES ?=
LOGIN_TIMEOUT ?= 600

.PHONY: download-statements
download-statements: ## Download bank statements from Revolut web (QR login)
	python3 revolut_automation/revolut-statement-downloader \
		$(if $(MONTH_FROM),--month-from $(MONTH_FROM)) \
		$(if $(MONTH_TO),--month-to $(MONTH_TO)) \
		--download-dir $(DOWNLOAD_DIR) \
		$(if $(CURRENCIES),--currencies $(CURRENCIES)) \
		--login-timeout $(LOGIN_TIMEOUT)
