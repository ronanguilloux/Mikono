.PHONY: help up down db_update quality cs-fix rector test e2e-test deploy backup fixtures

help: ## Show this help message
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}'

up: ## Start the application locally and wait for it to be ready
	docker compose up -d --wait

down: ## Stop the application locally (preserves DB data)
	docker compose down

db_update: ## Fetch the remote production DB and use it locally
	./scripts/sync-prod-db.sh

quality: ## Run code quality checks (PHPStan, PHPat, code style, security audit)
	docker compose exec php composer quality

cs-fix: ## Auto-fix PHP code style (Symfony + PER-CS2.0)
	docker compose exec php composer cs-fix

rector: ## Preview Rector refactors (dry-run, apply manually if needed)
	docker compose exec php composer rector

test: ## Run the main test suite (Functional & Integration)
	docker compose exec php php bin/phpunit

e2e-test: ## Run End-to-End tests with Panther
	docker compose exec php php bin/phpunit --testsuite="End-to-End Test Suite"

deploy: ## Deploy production over SSH (wait for the CI image build first: gh run list)
	ssh deploy@deploy.mikono.guilloux.org 'cd /opt/mikono && ./scripts/deploy.sh'

backup: ## Perform a hot backup of the SQLite database
	./scripts/backup-db.sh

fixtures: ## Load development fixtures and reset the local schema
	docker compose exec php bin/console foundry:load-fixtures --no-interaction
