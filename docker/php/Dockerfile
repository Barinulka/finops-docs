FROM php:8.4-cli-bookworm

ARG USER_ID=1000
ARG GROUP_ID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libicu-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        intl \
        pdo_pgsql \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN if getent group ${GROUP_ID} >/dev/null; then \
        EXISTING_GROUP="$(getent group ${GROUP_ID} | cut -d: -f1)"; \
    else \
        groupadd --gid ${GROUP_ID} app; \
        EXISTING_GROUP="app"; \
    fi \
    && if getent passwd ${USER_ID} >/dev/null; then \
        usermod --login app --home /home/app --move-home "$(getent passwd ${USER_ID} | cut -d: -f1)"; \
    else \
        useradd --uid ${USER_ID} --gid "${EXISTING_GROUP}" --create-home --shell /bin/bash app; \
    fi

WORKDIR /app

USER app

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "docker/php/router.php"]