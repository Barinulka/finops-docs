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
