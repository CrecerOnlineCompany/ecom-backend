.PHONY: help
.DEFAULT_GOAL := help

# Docker container name
CONTAINER := gofriz.phpv83
WORKDIR := /var/www/public_html/cinea

# Colors for terminal output
BLUE := \033[1;34m
GREEN := \033[1;32m
YELLOW := \033[1;33m
RED := \033[1;31m
NC := \033[0m # No Color

help: ## Show this help message
	@echo '$(BLUE)═══════════════════════════════════════════$(NC)'
	@echo '$(BLUE)   CINEA - Laravel Artisan Commands$(NC)'
	@echo '$(BLUE)═══════════════════════════════════════════$(NC)'
	@echo ''
	@echo '$(GREEN)Container:$(NC) $(CONTAINER)'
	@echo '$(GREEN)Project:$(NC) cinea (Cinema Management System)'
	@echo ''
	@echo '$(YELLOW)Usage: make [command]$(NC)'
	@echo ''
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sed 's/Makefile://g' | awk 'BEGIN {FS = ":.*?## "}; {printf "  $(BLUE)%-30s$(NC) %s\n", $$1, $$2}'
	@echo ''
	@echo '$(YELLOW)Examples:$(NC)'
	@echo '  make migrate           # Run migrations'
	@echo '  make seed             # Seed the database'
	@echo '  make logs             # Watch logs'
	@echo '  make tinker           # Start Laravel Tinker REPL'
	@echo ''

# ═══════════════════════════════════════════════════════════
# MIGRATIONS
# ═══════════════════════════════════════════════════════════
exec:
ifndef CMD
	$(error Please provide CMD="your command")
endif
	docker exec -it -w $(WORKDIR)  $(CONTAINER) $(CMD)


migrate: ## Run database migrations
	@echo '$(BLUE)Running migrations...$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan migrate

migrate-fresh: ## Fresh migrations (drop and recreate tables)
	@echo '$(BLUE)Running fresh migrations...$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan migrate:fresh

migrate-fresh-seed: ## Fresh migrations with seeding
	@echo '$(BLUE)Running fresh migrations with seed...$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan migrate:fresh --seed

migrate-rollback: ## Rollback last migration
	@echo '$(BLUE)Rolling back migrations...$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan migrate:rollback

migrate-status: ## Show migration status
	@echo '$(BLUE)Migration status...$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan migrate:status

# ═══════════════════════════════════════════════════════════
# SEEDING
# ═══════════════════════════════════════════════════════════
permissions: ## Set correct permissions for storage and bootstrap/cache
	@echo '$(BLUE)Setting permissions...$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) chmod -R 777 storage/logs bootstrap/cache
	@echo '$(GREEN)✓ Permissions set$(NC)'
seed: ## Run database seeders
	@echo '$(BLUE)Seeding database...$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan db:seed

seed-class: ## Seed specific class (usage: make seed-class SEEDER=PaymentProviderSeeder)
	@echo '$(BLUE)Seeding $(SEEDER)...$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan db:seed --class=$(SEEDER)

# ═══════════════════════════════════════════════════════════
# CACHE & OPTIMIZATION
# ═══════════════════════════════════════════════════════════

cache-clear: ## Clear application cache
	@echo '$(BLUE)Clearing cache...$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan cache:clear

config-cache: ## Cache configuration files
	@echo '$(BLUE)Caching configuration...$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan config:cache

route-cache: ## Cache routes
	@echo '$(BLUE)Caching routes...$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan route:cache

optimize: ## Optimize application
	@echo '$(BLUE)Optimizing application...$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan optimize

clear-all: ## Clear all caches and optimize
	@echo '$(BLUE)Clearing all caches...$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan cache:clear
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan config:clear
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan route:clear
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan view:clear
	@echo '$(GREEN)✓ All caches cleared$(NC)'

# ═══════════════════════════════════════════════════════════
# QUEUE & JOBS
# ═══════════════════════════════════════════════════════════

queue-work: ## Start queue worker
	@echo '$(BLUE)Starting queue worker...$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan queue:work

queue-failed: ## Show failed jobs
	@echo '$(BLUE)Failed jobs...$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan queue:failed

queue-retry-all: ## Retry all failed jobs
	@echo '$(BLUE)Retrying failed jobs...$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan queue:retry all

queue-flush: ## Clear all jobs
	@echo '$(BLUE)Flushing queue...$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan queue:flush

# ═══════════════════════════════════════════════════════════
# TINKER & DEBUGGING
# ═══════════════════════════════════════════════════════════

tinker: ## Start Laravel Tinker REPL
	@echo '$(BLUE)Starting Tinker...$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan tinker

logs: ## Watch application logs
	@echo '$(BLUE)Watching logs (Ctrl+C to exit)...$(NC)'
	tail -f storage/logs/laravel.log

logs-payment: ## Watch payment logs
	@echo '$(BLUE)Watching payment logs...$(NC)'
	tail -f storage/logs/laravel.log | grep -i "payment\|qr\|terminal"

logs-error: ## Watch error logs only
	@echo '$(BLUE)Watching error logs...$(NC)'
	tail -f storage/logs/laravel.log | grep -i "error\|exception\|failed"

# ═══════════════════════════════════════════════════════════
# DATABASE
# ═══════════════════════════════════════════════════════════

db-seed-all: ## Run all seeders
	@echo '$(BLUE)Running all seeders...$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan db:seed

db-wipe: ## Drop all tables (no backup!)
	@echo '$(RED)WARNING: This will drop all tables!$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan db:wipe --force

# ═══════════════════════════════════════════════════════════
# KEY & AUTHENTICATION
# ═══════════════════════════════════════════════════════════

key-generate: ## Generate application key
	@echo '$(BLUE)Generating APP_KEY...$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan key:generate

key-generate-jwt: ## Generate JWT secret (if using JWT)
	@echo '$(BLUE)Generating JWT secret...$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan jwt:secret

# ═══════════════════════════════════════════════════════════
# TINKER SPECIFIC OPERATIONS
# ═══════════════════════════════════════════════════════════

payment-providers: ## List all payment providers (via tinker)
	@echo '$(BLUE)Listing payment providers...$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan tinker --execute='App\Models\PaymentProvider::all()'

test-payment-qr: ## Test QR payment handler
	@echo '$(BLUE)Testing QR payment handler...$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan test-mercado-pago

# ═══════════════════════════════════════════════════════════
# GENERAL ARTISAN COMMANDS
# ═══════════════════════════════════════════════════════════

artisan: ## Run custom artisan command (usage: make artisan CMD="migrate:status")
	@echo '$(BLUE)Running: php artisan $(CMD)$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan $(CMD)

list: ## List all available artisan commands
	@echo '$(BLUE)Available Artisan commands...$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan list

serve: ## Start development server
	@echo '$(BLUE)Starting Laravel development server...$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan serve --host=0.0.0.0 --port=8000

# ═══════════════════════════════════════════════════════════
# UTILITIES
# ═══════════════════════════════════════════════════════════

container-shell: ## Open container shell
	@echo '$(BLUE)Opening $(CONTAINER) shell...$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) bash

container-status: ## Check container status
	docker ps | grep $(CONTAINER)

versions: ## Show PHP and Laravel versions
	@echo '$(BLUE)Environment versions:$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php --version
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan --version

env-test: ## Test environment configuration
	@echo '$(BLUE)Testing environment...$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan env

# ═══════════════════════════════════════════════════════════
# SETUP & INITIALIZATION
# ═══════════════════════════════════════════════════════════

setup: ## Complete setup (migrate + seed + cache)
	@echo '$(BLUE)Setting up application...$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan migrate:fresh --seed
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan config:cache
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan route:cache
	@echo '$(GREEN)✓ Setup complete!$(NC)'

fresh-install: ## Fresh installation (warning: clears all data)
	@echo '$(RED)WARNING: This will delete all data and reinstall!$(NC)'
	@read -p "Continue? [y/N] " -n 1 -r; \
	echo; \
	if [[ $$REPLY =~ ^[Yy]$$ ]]; then \
		docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan migrate:fresh --seed; \
		docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan key:generate; \
		echo '$(GREEN)✓ Fresh installation complete!$(NC)'; \
	fi

# ═══════════════════════════════════════════════════════════
# PAYMENTS SPECIFIC
# ═══════════════════════════════════════════════════════════

payment-setup: ## Setup payment providers
	@echo '$(BLUE)Setting up payment providers...$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan db:seed --class=PaymentProviderSeeder
	@echo '$(GREEN)✓ Payment providers configured$(NC)'

test-qr-payment: ## Test QR payment flow
	@echo '$(BLUE)Testing QR payment...$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan test --filter="QrPayment"

test-terminal-payment: ## Test terminal payment flow
	@echo '$(BLUE)Testing terminal payment...$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan test --filter="TerminalPayment"

#  ═══════════════════════════════════════════════════════════
# TESTING
# ═══════════════════════════════════════════════════════════

test: ## Run all tests
	@echo '$(BLUE)Running tests...$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan test

test-payment: ## Run payment tests only
	@echo '$(BLUE)Running payment tests...$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan test --filter="Payment"

test-feature: ## Run feature tests
	@echo '$(BLUE)Running feature tests...$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan test tests/Feature --filter="Feature"

test-unit: ## Run unit tests
	@echo '$(BLUE)Running unit tests...$(NC)'
	docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan test tests/Unit --filter="Unit"

# ═══════════════════════════════════════════════════════════
# CLEANUP
# ═══════════════════════════════════════════════════════════

clean: ## Clean up temporary files
	@echo '$(BLUE)Cleaning up...$(NC)'
	rm -rf storage/logs/laravel.log.* 2>/dev/null || true
	rm -rf bootstrap/cache/* 2>/dev/null || true
	@echo '$(GREEN)✓ Cleanup complete$(NC)'

# ═══════════════════════════════════════════════════════════
# INFORMATION
# ═══════════════════════════════════════════════════════════

info: ## Show environment information
	@echo '$(BLUE)═══════════════════════════════════════════$(NC)'
	@echo '$(BLUE)   CINEA - Environment Information$(NC)'
	@echo '$(BLUE)═══════════════════════════════════════════$(NC)'
	@echo ''
	@echo '$(GREEN)Container:$(NC) $(CONTAINER)'
	@echo '$(GREEN)Workdir:$(NC) $(WORKDIR)'
	@echo ''
	@echo '$(BLUE)PHP Version:$(NC)'
	@docker exec -it -w $(WORKDIR)  $(CONTAINER) php --version | head -1
	@echo ''
	@echo '$(BLUE)Laravel Version:$(NC)'
	@docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan --version
	@echo ''
	@echo '$(BLUE)Database Status:$(NC)'
	@docker exec -it -w $(WORKDIR)  $(CONTAINER) php artisan tinker --execute='echo "✓ Database connected"' 2>/dev/null || echo '✗ Database connection failed'
	@echo ''
	@echo '$(BLUE)Packages Installed:$(NC)'
	@docker exec -it -w $(WORKDIR)  $(CONTAINER) composer --version 2>/dev/null | head -1
	@echo ''

.PHONY: help migrate migrate-fresh migrate-fresh-seed migrate-rollback migrate-status seed \
        seed-class cache-clear config-cache route-cache optimize clear-all queue-work \
        queue-failed queue-retry-all queue-flush tinker logs logs-payment logs-error \
        db-seed-all db-wipe key-generate key-generate-jwt payment-providers test-payment-qr \
        artisan list serve container-shell container-status versions env-test setup \
        fresh-install payment-setup test-qr-payment test-terminal-payment test test-payment \
        test-feature test-unit clean info
