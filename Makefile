PHP ?= php
COMPOSER ?= composer

.DEFAULT_GOAL := help
.PHONY: help install test phpstan cs cs-check cs-fix qa

help:
	@echo "install   Install development dependencies"
	@echo "test      Run Pest"
	@echo "phpstan   Run static analysis at level 9"
	@echo "cs        Check coding standards with PHP_CodeSniffer"
	@echo "cs-check  Check formatting with PHP-CS-Fixer (no changes)"
	@echo "cs-fix    Apply formatting with PHP-CS-Fixer"
	@echo "qa        Run tests, static analysis and style checks"

install:
	$(COMPOSER) install --no-interaction --prefer-dist

test:
	$(PHP) vendor/bin/pest --do-not-cache-result

phpstan:
	$(PHP) vendor/phpstan/phpstan/phpstan analyse --configuration=phpstan.neon --memory-limit=512M
	$(PHP) tools/check-value-types.php

cs:
	$(PHP) vendor/squizlabs/php_codesniffer/bin/phpcs --standard=phpcs.xml.dist

cs-check:
	$(PHP) vendor/friendsofphp/php-cs-fixer/php-cs-fixer fix --config=.php-cs-fixer.dist.php --dry-run --diff

cs-fix:
	$(PHP) vendor/friendsofphp/php-cs-fixer/php-cs-fixer fix --config=.php-cs-fixer.dist.php --diff

qa: test phpstan cs cs-check
