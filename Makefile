# Raccourcis pour installer et piloter le projet avec DDEV.
# Lancer « make » ou « make help » pour la liste des commandes.

PROFILE      ?= standard
ACCOUNT_NAME ?= admin
ACCOUNT_PASS ?= admin

DRUSH_SI = ddev drush site:install $(PROFILE) \
	--account-name=$(ACCOUNT_NAME) --account-pass=$(ACCOUNT_PASS) -y

.DEFAULT_GOAL := help
.PHONY: help install start deps hooks check-all drupal-reinstall launch login stop

help: ## Affiche cette aide.
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*## "} {printf "  make %-18s %s\n", $$1, $$2}'

install: start deps hooks drupal-reinstall login ## Installe tout (ou réinstalle de zéro), puis ouvre le site connecté.

start: ## Démarre les conteneurs DDEV.
	ddev start

deps: ## Installe les dépendances Composer.
	ddev composer install

hooks: ## Active les hooks Git du projet (.githooks : phpcs, phpmd, PHPStan, twigcs, Conventional Commits).
	git config core.hooksPath .githooks

check-all: ## Vérifie tout le code custom (phpcs, phpmd, PHPStan, twigcs), avant un push ou une MR.
	@.githooks/pre-commit --all

drupal-reinstall: ## Supprime la base et réinstalle Drupal (drush site:install seul).
	$(DRUSH_SI)

launch: ## Ouvre le site dans le navigateur.
	ddev launch

login: ## Ouvre le site connecté en administrateur.
	ddev launch "$$(ddev drush uli --no-browser)"

stop: ## Arrête les conteneurs DDEV.
	ddev stop
