EXEC_PHP        = php -d memory_limit=-1
COMPOSER        = composer

##
##Dev
##-------------

dev: ## Start dev server
	docker-compose up -d

cs-fix: ## Check and fix coding styles using PHP CS Fixer
	composer cs-fix

phpstan: ## Execute PHPStan analysis
	composer phpstan

test: ## Launch PHPUnit test suite
	composer test

docs-serve: ## Start documentation server locally
	./node_modules/.bin/hugo/hugo server -s docs -D

# DEFAULT
.DEFAULT_GOAL := help
help:
	@grep -E '(^[a-zA-Z_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'
.PHONY: help

##
