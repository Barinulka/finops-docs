USER_ID ?= $(shell id -u)
GROUP_ID ?= $(shell id -g)

export USER_ID
export GROUP_ID

DC := docker compose
APP := $(DC) exec app

.PHONY: build up down restart ps logs sh composer-install cache-clear migrate schema-validate assets sass-clean parser-shell parser-parse parser-logs parser-health parser-api-parse worker worker-once worker-logs worker-restart

build:
	$(DC) build

up:
	$(DC) up -d

down:
	$(DC) down

restart:
	$(DC) restart

ps:
	$(DC) ps

logs:
	$(DC) logs -f

sh:
	$(APP) bash

composer-install:
	$(DC) run --rm app composer install

cache-clear:
	$(APP) php bin/console cache:clear

migrate:
	$(APP) php bin/console doctrine:migrations:migrate --no-interaction

schema-validate:
	$(APP) php bin/console doctrine:schema:validate

assets:
	$(APP) php bin/console sass:build
	$(APP) php bin/console asset-map:compile

sass-clean:
	$(DC) exec --user root app sh -lc 'rm -f /app/var/dart-sass-*.tar /app/var/dart-sass-*.tar.gz'
	$(DC) exec --user root app sh -lc 'find /app/var/dart-sass -mindepth 1 -maxdepth 1 -exec rm -rf {} +'
	$(DC) exec --user root app sh -lc 'chown -R app:dialout /app/var/dart-sass'

PARSER := $(DC) run --rm parser

parser-shell:
	$(PARSER) bash

parser-parse:
	$(PARSER) python -m pdf_parser.cli $(file)

parser-logs:
	$(DC) logs -f parser

parser-health:
	curl -s http://127.0.0.1:8001/health

parser-api-parse:
	curl -s -X POST http://127.0.0.1:8001/parse -F "file=@$(file)"

worker:
	$(APP) php bin/console messenger:consume async -vv

worker-once:
	$(APP) php bin/console messenger:consume async -vv --limit=1

worker-logs:
	$(DC) logs -f worker

worker-restart:
	$(DC) restart worker