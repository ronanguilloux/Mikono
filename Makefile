.PHONY: help up down db_update oauth_keys quality cs-fix rector test e2e-test deploy backup fixtures

help: ## Show this help message
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}'

up: ## Start the application locally and wait for it to be ready
	docker compose up -d --wait

down: ## Stop the application locally (preserves DB data)
	docker compose down

db_update: ## Fetch the remote production DB and use it locally
	./scripts/sync-prod-db.sh

oauth_keys: ## Generate local OAuth keys into the gitignored .env.dev.local
	@openssl genrsa -out .oauth.pem 2048 2>/dev/null
	@{ grep -v '^OAUTH_' .env.dev.local 2>/dev/null || true; \
	  echo "OAUTH_PRIVATE_KEY=$$(base64 < .oauth.pem | tr -d '\n')"; \
	  echo "OAUTH_PUBLIC_KEY=$$(openssl rsa -in .oauth.pem -pubout 2>/dev/null | base64 | tr -d '\n')"; \
	  echo "OAUTH_ENCRYPTION_KEY=$$(openssl rand -base64 32)"; } > .env.tmp.local
	@mv .env.tmp.local .env.dev.local && rm -f .oauth.pem

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
