.DEFAULT_GOAL := help
.PHONY: help install test lint

help:
	@echo "Targets:"
	@echo "  help     - show this help"
	@echo "  install  - composer install (dev dependencies included)"
	@echo "  test     - run PHPUnit test suite (ARGS=... passes extra options, e.g. ARGS=tests/Foo.php)"
	@echo "  lint     - php -l over all src/**/*.php; fails on errors or deprecations"

install:
	composer install

# auto_prepend_file defines DATA_DIR before composer loads glog_util.
test:
	php -d auto_prepend_file=tests/support/prepend.php vendor/bin/phpunit $(ARGS)

lint:
	@fail=0; \
	for f in $$(find src -name '*.php'); do \
		out=$$(php -d error_reporting=-1 -d display_errors=1 -l "$$f" 2>&1 | grep -v 'PHP Startup'); \
		if echo "$$out" | grep -qE 'Deprecated|Fatal|Parse error|Warning|Errors parsing'; then \
			echo "$$out"; fail=1; \
		fi; \
	done; \
	if [ $$fail -ne 0 ]; then echo "lint: FAILED"; exit 1; fi; \
	echo "lint: OK"
