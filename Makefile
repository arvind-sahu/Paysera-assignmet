.PHONY: up down build install migrate seed test test-unit test-integration load-test shell

COMPOSE := docker compose -f docker-compose.yml

up:
	$(COMPOSE) up -d --build

down:
	$(COMPOSE) down

build:
	$(COMPOSE) build php

install:
	$(COMPOSE) run --rm php composer install

migrate:
	$(COMPOSE) exec php php bin/console doctrine:migrations:migrate --no-interaction

seed:
	$(COMPOSE) exec php php bin/console app:seed-demo-accounts

test:
	$(COMPOSE) exec php ./vendor/bin/phpunit

test-unit:
	$(COMPOSE) exec php ./vendor/bin/phpunit --testsuite=Unit

test-integration:
	$(COMPOSE) exec php ./vendor/bin/phpunit --testsuite=Integration

load-test:
	$(COMPOSE) --profile loadtest run --rm k6

shell:
	$(COMPOSE) exec php bash

setup: up install migrate seed
	@echo "API ready at http://localhost:8080"
