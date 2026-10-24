.PHONY: test lint
test:
	composer test
lint:
	find . -name '*.php' -not -path './vendor/*' -not -path './node_modules/*' -exec php -l {} \;
