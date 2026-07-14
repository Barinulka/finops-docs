# CRM App

CRM/back-office приложение на Symfony для ведения клиентов, загрузки PDF-документов, разбора данных операций, учета операций и аудита действий пользователей.

## Требования

- Docker
- Docker Compose
- Make

Локальные PHP, Composer и Symfony CLI для обычного запуска проекта не обязательны: приложение запускается через Docker.

## Первый запуск

Собрать Docker-образы:

```bash
make build
```

Установить PHP-зависимости внутри контейнера:

```bash
make composer-install
```

Запустить контейнеры:

```bash
make up
```

Применить миграции:

```bash
make migrate
```

Проверить схему базы:

```bash
make schema-validate
```

Собрать ассеты:

```bash
make assets
```

Открыть админ-панель:

```text
http://127.0.0.1:8000/admin
```

## Основные команды

Запустить контейнеры:

```bash
make up
```

Остановить контейнеры:

```bash
make down
```

Перезапустить контейнеры:

```bash
make restart
```

Посмотреть статус контейнеров:

```bash
make ps
```

Смотреть логи всех сервисов:

```bash
make logs
```

Открыть shell внутри PHP-контейнера:

```bash
make sh
```

Очистить Symfony cache:

```bash
make cache-clear
```

Применить миграции:

```bash
make migrate
```

Проверить Doctrine mapping и синхронизацию схемы БД:

```bash
make schema-validate
```

Собрать Sass и AssetMapper assets:

```bash
make assets
```

Очистить временные файлы Dart Sass и заново собрать assets:

```bash
make sass-clean
make assets
```

`sass-clean` нужен, если Sass внутри Docker сломался из-за старого бинарника или неудачной распаковки.

## Локальные сервисы

- Symfony app: `http://127.0.0.1:8000`
- PostgreSQL: `127.0.0.1:5432`
- Redis: `127.0.0.1:6379`
- MinIO API: `http://127.0.0.1:19000`
- MinIO console: `http://127.0.0.1:9001`

## Переменные окружения

Для Docker-запуска основные переменные заданы в `compose.yaml`.

Файл `.env.local` можно использовать для запуска команд с хоста, но Docker-приложение берет подключения из `compose.yaml`.

Пример `.env.local` для запуска команд с хоста:

```dotenv
DATABASE_URL="postgresql://app:app@127.0.0.1:5432/app?serverVersion=16&charset=utf8"

MINIO_ENDPOINT="http://127.0.0.1:19000"
MINIO_PUBLIC_ENDPOINT="http://127.0.0.1:19000"
MINIO_ACCESS_KEY="app"
MINIO_SECRET_KEY="ChangeMeMinioPassword"
MINIO_BUCKET="crm-documents"
MINIO_REGION="us-east-1"

MESSENGER_TRANSPORT_DSN="redis://127.0.0.1:6379/messages"
MESSENGER_FAILED_TRANSPORT_DSN="redis://127.0.0.1:6379/failed_messages"
```

## Ассеты

Проект использует `symfonycasts/sass-bundle` и AssetMapper.

Исходный SCSS:

```text
assets/styles/app.scss
```

В EasyAdmin подключается логический путь:

```php
->addCssFile('styles/app.scss')
```

Сборка выполняется командой:

```bash
make assets
```

Важно: `docker/php/router.php` нужен для PHP built-in server, чтобы CSS и JS из `public/` отдавались как статические файлы, а не проходили через `public/index.php`.

## Полезные Symfony-команды

Выполнять через контейнер:

```bash
docker compose exec app php bin/console lint:container
docker compose exec app php bin/console doctrine:migrations:status
docker compose exec app php bin/console debug:router
docker compose exec app php bin/console app:user:create
```

## Production

Production-развертывание выполняется через отдельный compose-файл:

```bash
compose.prod.yaml
```

Пример переменных окружения:

```bash
.env.prod.example
```

Реальный файл `.env.prod` создается только на сервере и не коммитится. В нем должны быть заданы секреты и внешние интеграции:

```dotenv
APP_SECRET=
APP_LOCALE=ru

POSTGRES_DB=app
POSTGRES_USER=app
POSTGRES_PASSWORD=

MINIO_ROOT_USER=app
MINIO_ROOT_PASSWORD=
MINIO_BUCKET=crm-documents
MINIO_REGION=us-east-1

TELEGRAM_BOT_TOKEN=
TELEGRAM_WEBHOOK_SECRET=
TELEGRAM_ALLOWED_MAX_FILE_SIZE=20971520

GOOGLE_SHEETS_SPREADSHEET_ID=
GOOGLE_SHEETS_SHEET_NAME=Лист1
GOOGLE_SERVICE_ACCOUNT_CREDENTIALS_PATH=/app/var/google/service-account.json
```

Файл сервисного аккаунта Google должен лежать на сервере:

```text
var/google/service-account.json
```

Этот файл содержит секреты и не должен попадать в Git.

### Первый запуск на сервере

Собрать образы:

```bash
docker compose --env-file .env.prod -f compose.prod.yaml build
```

Запустить контейнеры:

```bash
docker compose --env-file .env.prod -f compose.prod.yaml up -d
```

Установить PHP-зависимости:

```bash
docker compose --env-file .env.prod -f compose.prod.yaml exec app composer install --no-dev --optimize-autoloader
```

Применить миграции:

```bash
docker compose --env-file .env.prod -f compose.prod.yaml exec app php bin/console doctrine:migrations:migrate --no-interaction
```

Собрать ассеты и очистить cache:

```bash
docker compose --env-file .env.prod -f compose.prod.yaml exec app php bin/console sass:build
docker compose --env-file .env.prod -f compose.prod.yaml exec app php bin/console asset-map:compile
docker compose --env-file .env.prod -f compose.prod.yaml exec app php bin/console cache:clear
```

Проверить контейнеры:

```bash
docker compose --env-file .env.prod -f compose.prod.yaml ps
```

Проверить приложение локально на сервере:

```bash
curl -I http://127.0.0.1:8000/admin
```

Ожидаемый ответ для закрытой админки:

```text
HTTP/1.1 302 Found
Location: /login
```

### Caddy

В production Caddy работает на сервере как reverse proxy:

```caddyfile
finops-docs.ru {
    reverse_proxy 127.0.0.1:8000
}

www.finops-docs.ru {
    redir https://finops-docs.ru{uri} permanent
}
```

Проверка конфигурации:

```bash
caddy validate --config /etc/caddy/Caddyfile
systemctl reload caddy
systemctl status caddy --no-pager
```

Проверка HTTPS:

```bash
curl -I https://finops-docs.ru/admin
```

### Telegram webhook

Роут webhook:

```text
POST /telegram/webhook/{secret}
```

Установить webhook:

```bash
source .env.prod
curl -s "https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/setWebhook?url=https://finops-docs.ru/telegram/webhook/${TELEGRAM_WEBHOOK_SECRET}"
```

Проверить webhook:

```bash
curl -s "https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/getWebhookInfo"
```

Секрет из URL webhook нельзя публиковать. Если он попал в открытый чат или логи, нужно сгенерировать новый `TELEGRAM_WEBHOOK_SECRET`, обновить `.env.prod`, пересоздать контейнеры `app` и `worker`, затем заново вызвать `setWebhook`.

### Telegram-пользователи

Доступ к боту разрешается через сущность `TelegramUser`.

Если неизвестный пользователь пишет боту, бот должен вернуть его Telegram ID. После этого администратора добавляет пользователя в админке:

```text
Telegram ID
Username
First name
Is active
Role
```

После добавления пользователь может отправлять PDF-файлы боту. Бот парсит документ, показывает результат и дает кнопки для записи в Google Sheets или отклонения.
