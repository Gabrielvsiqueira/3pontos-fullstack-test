.DEFAULT_GOAL := help

.PHONY: help
help: ## Show available commands
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

.PHONY: setup
setup: ## Install dependencies, migrate, seed and build assets
	@composer run-script setup

.PHONY: pint
pint: ## Run Pint code style fixer
	@XDEBUG_MODE=off $(CURDIR)/vendor/bin/pint --parallel

.PHONY: test-pint
test-pint: ## Run Pint code style fixer in test mode
	@XDEBUG_MODE=off $(CURDIR)/vendor/bin/pint --test --parallel

.PHONY: rector
rector: ## Run Rector
	@$(CURDIR)/vendor/bin/rector process

.PHONY: test-rector
test-rector: ## Run Rector in test mode
	@$(CURDIR)/vendor/bin/rector process --dry-run

.PHONY: phpstan
phpstan: ## Run PHPStan
	@$(CURDIR)/vendor/bin/phpstan analyse --ansi --memory-limit=1G

.PHONY: test-phpstan
test-phpstan: ## Run PHPStan in test mode
	@$(CURDIR)/vendor/bin/phpstan analyse --ansi --memory-limit=1G

.PHONY: format
format: rector pint ## Run Rector and Pint and fix the source code

.PHONY: check
check: test-rector test-pint test-phpstan ## Run Rector, Pint and PHPStan in dry-run mode

.PHONY: test
test: ## Run all tests
	@$(CURDIR)/vendor/bin/pest --parallel --compact

.PHONY: test-unit
test-unit: ## Run unit tests
	@$(CURDIR)/vendor/bin/pest --parallel --compact --group=unit

.PHONY: test-feature
test-feature: ## Run feature tests
	@$(CURDIR)/vendor/bin/pest --parallel --compact --group=feature

.PHONY: migrate-fresh
migrate-fresh: ## Run migrations and seed the database
	@php artisan migrate:fresh --seed

.PHONY: env-up
env-up: ## Start the development environment (Postgres, Redis, Mailpit)
	@docker compose up --detach --build

.PHONY: env-down
env-down: ## Stop the development environment and remove volumes
	@docker compose down --rmi all --volumes

.PHONY: dev
dev: ## Start the server, queue, logs and Vite
	@composer run-script dev
