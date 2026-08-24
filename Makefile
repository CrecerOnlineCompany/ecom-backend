.PHONY: help exec exec-tty composer-install composer-update npm-install build migrate migrate-fresh migrate-fresh-seed seed cache-clear clear-all list tinker logs container-shell container-status versions aimeos-publish aimeos-setup aimeos-demo aimeos-site aimeos-superuser setup
.DEFAULT_GOAL := help

CONTAINER := gofriz.phpv83
WORKDIR := /var/www/public_html/ecom/backend
DOCKER := docker exec -w $(WORKDIR) $(CONTAINER)
DOCKER_TTY := docker exec -it -w $(WORKDIR) $(CONTAINER)

BLUE := \033[1;34m
GREEN := \033[1;32m
YELLOW := \033[1;33m
NC := \033[0m

help: ## Show this help
	@echo '$(BLUE)GOFRIZ ECOM - Laravel + Aimeos$(NC)'
	@echo ''
	@echo '$(YELLOW)Usage: make [command]$(NC)'
	@echo ''
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  $(BLUE)%-24s$(NC) %s\n", $$1, $$2}'

exec: ## Run non-interactive command: make exec CMD="php artisan list"
ifndef CMD
	$(error Please provide CMD="your command")
endif
	$(DOCKER) $(CMD)

exec-tty: ## Run interactive command: make exec-tty CMD="php artisan tinker"
ifndef CMD
	$(error Please provide CMD="your command")
endif
	$(DOCKER_TTY) $(CMD)

composer-install: ## Install PHP dependencies
	$(DOCKER) composer install

composer-update: ## Update PHP dependencies
	$(DOCKER) composer update -W

npm-install: ## Install Node dependencies
	npm install

build: ## Build Vite assets
	npm run build

migrate: ## Run migrations
	$(DOCKER) php artisan migrate

migrate-fresh: ## Fresh migrations
	$(DOCKER) php artisan migrate:fresh

migrate-fresh-seed: ## Fresh migrations with base seed
	$(DOCKER) php artisan migrate:fresh --seed

seed: ## Run base seeders
	$(DOCKER) php artisan db:seed

cache-clear: ## Clear application cache
	$(DOCKER) php artisan cache:clear

clear-all: ## Clear framework caches
	$(DOCKER) php artisan cache:clear
	$(DOCKER) php artisan config:clear
	$(DOCKER) php artisan route:clear
	$(DOCKER) php artisan view:clear

list: ## List Artisan commands
	$(DOCKER) php artisan list

tinker: ## Start Laravel Tinker
	$(DOCKER_TTY) php artisan tinker

logs: ## Watch Laravel log
	tail -f storage/logs/laravel.log

container-shell: ## Open container shell
	$(DOCKER_TTY) bash

container-status: ## Check container status
	docker ps | grep $(CONTAINER)

versions: ## Show PHP, Laravel and Aimeos command availability
	$(DOCKER) php --version
	$(DOCKER) php artisan --version
	$(DOCKER) php artisan list aimeos

aimeos-publish: ## Publish Aimeos assets/config
	$(DOCKER) php artisan vendor:publish --tag=public --force
	$(DOCKER) php artisan vendor:publish --tag=config --force

aimeos-setup: ## Install/update Aimeos tables: make aimeos-setup SITE=default
	$(DOCKER) php artisan aimeos:setup $(if $(SITE),$(SITE),default)

aimeos-demo: ## Install/update Aimeos demo data: make aimeos-demo SITE=default
	$(DOCKER) php artisan aimeos:setup --option=setup/default/demo:1 $(if $(SITE),$(SITE),default)

aimeos-site: ## Create/update an Aimeos site: make aimeos-site SITE=mitienda
	$(DOCKER) php artisan aimeos:setup $(if $(SITE),$(SITE),default)

aimeos-superuser: ## Create superadmin: make aimeos-superuser EMAIL=admin@example.com SITE=default PASSWORD=secret
ifndef EMAIL
	$(error Please provide EMAIL="admin@example.com")
endif
	$(if $(PASSWORD),$(DOCKER) php artisan aimeos:account --super --password=$(PASSWORD) $(EMAIL) $(if $(SITE),$(SITE),default),$(DOCKER_TTY) php artisan aimeos:account --super $(EMAIL) $(if $(SITE),$(SITE),default))

setup: composer-install npm-install aimeos-publish migrate-fresh-seed aimeos-setup build ## Full local setup
	@echo '$(GREEN)Setup complete. Manage SPA: /manage, Aimeos admin: /admin$(NC)'
