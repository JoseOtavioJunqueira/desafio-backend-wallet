.DEFAULT_GOAL := help
COMPOSE := docker compose
EXEC := $(COMPOSE) exec app

.PHONY: help up down build sh logs install migrate migrate-diff test test-unit test-integration test-functional stan cs-fix cs-check phpmd quality

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-18s\033[0m %s\n", $$1, $$2}'

up: ## Start the full stack (app, worker, database, cache) in the background
	$(COMPOSE) up -d --build

down: ## Stop and remove containers
	$(COMPOSE) down

build: ## Rebuild the app/worker image
	$(COMPOSE) build

sh: ## Open a shell in the app container
	$(EXEC) sh

logs: ## Tail logs from every service
	$(COMPOSE) logs -f

install: ## Install PHP dependencies inside the container
	$(EXEC) composer install

migrate: ## Apply pending Doctrine migrations
	$(EXEC) php bin/console doctrine:migrations:migrate --no-interaction

migrate-diff: ## Generate a new migration from the current entity mappings
	$(EXEC) php bin/console doctrine:migrations:diff --no-interaction

test: ## Run the full PHPUnit suite (unit + integration + functional)
	$(EXEC) php bin/phpunit

test-unit: ## Run only Domain/Application unit tests (no DB required)
	$(EXEC) php bin/phpunit --testsuite=unit

test-integration: ## Run repository/persistence tests against real PostgreSQL
	$(EXEC) php bin/phpunit --testsuite=integration

test-functional: ## Run end-to-end HTTP tests
	$(EXEC) php bin/phpunit --testsuite=functional

stan: ## Static analysis (PHPStan)
	$(EXEC) vendor/bin/phpstan analyse --memory-limit=512M

cs-fix: ## Fix code style (PHP-CS-Fixer, PSR-12)
	$(EXEC) vendor/bin/php-cs-fixer fix

cs-check: ## Check code style without modifying files
	$(EXEC) vendor/bin/php-cs-fixer fix --dry-run --diff

phpmd: ## Run PHPMD (as suggested in the challenge README)
	docker run --rm -v "$$(pwd)":/project -w /project jakzal/phpqa:php8.4 phpmd src text cleancode,codesize,controversial,design,naming,unusedcode

quality: cs-check stan test ## Run every quality gate used in CI, locally
