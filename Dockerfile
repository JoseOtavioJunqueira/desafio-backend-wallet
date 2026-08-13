# syntax=docker/dockerfile:1

# FrankenPHP bundles the PHP runtime and a Caddy-based webserver into a single process,
# replacing the traditional php-fpm + nginx pair with one image/one container. It's the
# runtime Symfony itself recommends (https://symfony.com/doc/current/setup/docker.html) and
# noticeably simplifies compose.yaml — no separate webserver service, no shared FPM socket
# volume to get right.
FROM dunglas/frankenphp:1-php8.4-bookworm AS base

RUN install-php-extensions \
    pdo_pgsql \
    intl \
    opcache \
    zip \
    apcu \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

ENV APP_ENV=prod \
    COMPOSER_ALLOW_SUPERUSER=1 \
    FRANKENPHP_CONFIG="worker ./public/index.php"

COPY docker/Caddyfile /etc/caddy/Caddyfile

# The base FrankenPHP image ships a HEALTHCHECK that polls Caddy's admin API on :2019 — but
# our Caddyfile turns that admin API off (`admin off`, docker/Caddyfile) as a hardening
# choice, so the inherited check fails permanently and every container reports "unhealthy"
# regardless of whether the app is actually serving traffic. Replaced below with a check
# against the app's own /health/live (compose.yaml) for the `app` service; the `worker`
# service runs no HTTP server at all, so it gets none.
HEALTHCHECK NONE

# ---------------------------------------------------------------------------------------------
FROM base AS dev

ENV APP_ENV=dev \
    FRANKENPHP_CONFIG=""

# Xdebug is intentionally not baked into the default dev image: it roughly doubles build time
# and this challenge doesn't call for step-debugging out of the box. Add
# `RUN install-php-extensions xdebug` back locally (and XDEBUG_MODE=debug) if you want it.
COPY docker/php/dev.ini "$PHP_INI_DIR/conf.d/99-app-dev.ini"

# Dependencies are installed by `make up` (composer.json/lock are bind-mounted in dev), so the
# image itself stays generic and doesn't need rebuilding on every `composer require`.
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

# ---------------------------------------------------------------------------------------------
FROM base AS prod

COPY docker/php/prod.ini "$PHP_INI_DIR/conf.d/99-app-prod.ini"

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY . .

RUN composer dump-autoload --no-dev --classmap-authoritative \
    && composer dump-env prod \
    && php bin/console cache:warmup --env=prod \
    && chown -R www-data:www-data var

USER www-data

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
