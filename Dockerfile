ARG FRANKENPHP_VERSION=1.9.0
ARG PHP_VERSION=8.2.29
ARG COMPOSER_VERSION=2.8.12
ARG BUN_VERSION=1.2.23
ARG STACKER_VERSION=1.1.3

FROM composer:${COMPOSER_VERSION} AS composer
FROM oven/bun:${BUN_VERSION}-debian AS bun
FROM eslym/stacker:${STACKER_VERSION} AS stacker

# ========================================================== #
#  Base Build Stage
# ========================================================== #
FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-php${PHP_VERSION} AS base

ARG PHP_REDIS_VERSION=6.2.0

RUN apt update &&\
    apt -y install --no-install-recommends \
           curl \
           unzip \
           libzip-dev &&\
    docker-php-ext-configure pcntl --enable-pcntl &&\
    docker-php-ext-install -j$(nproc) pdo_mysql zip pcntl opcache &&\
    pecl install redis-${PHP_REDIS_VERSION} &&\
    docker-php-ext-enable redis &&\
    rm -rf /var/lib/apt/lists/* &&\
    rm -rf /tmp/* &&\
    chown www-data:www-data /app /data/caddy /config/caddy &&\
    setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp

# ========================================================== #
#  For Dev Container
# ========================================================== #
FROM base AS devcontainer

RUN pecl install xdebug &&\
    docker-php-ext-enable xdebug &&\
    echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini &&\
    echo "xdebug.client_host=127.0.0.1" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

RUN apt update &&\
    apt -y upgrade &&\
    apt -y install git \
                   redis-tools \
                   mariadb-client \
                   gnupg2 \
                   ssh-client \
                   socat \
                   sudo \
                   lsof \
                   tree \
                   jq \
                   procps \
                   psutils &&\
    rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY --from=bun /usr/local/bin/bun /usr/local/bin/bun
COPY --from=stacker /usr/local/bin/stacker /usr/local/bin/stacker

RUN sudo ln -s /usr/local/bin/bun /usr/local/bin/bunx &&\
    curl -fsSL https://deb.nodesource.com/setup_23.x -o /tmp/nodesource_setup.sh &&\
    sudo chmod +x /tmp/nodesource_setup.sh &&\
    sudo bash -E /tmp/nodesource_setup.sh &&\
    sudo apt install -y nodejs &&\
    rm -f /tmp/nodesource_setup.sh &&\
    useradd --shell /bin/bash --create-home --home-dir /home/eslym --uid 1000 -U eslym &&\
    echo "eslym ALL=(ALL) NOPASSWD:ALL" >> /etc/sudoers &&\

WORKDIR /home/eslym
USER eslym

CMD ["/bin/bash"]

# ========================================================== #
#  Composer Vendor Stage
# ========================================================== #
FROM base AS vendor

COPY --from=composer /usr/bin/composer /usr/bin/composer

COPY composer.json /root/app/composer.json
COPY composer.lock /root/app/composer.lock

WORKDIR /root/app
RUN apt update &&\
    apt -y install unzip &&\
    composer install --no-dev --no-interaction --optimize-autoloader --no-scripts

# ========================================================== #
#  Frontend Build Stage
# ========================================================== #
FROM bun AS frontend

COPY package.json /root/app/package.json
COPY bun.lock /root/app/bun.lock
COPY vite.config.js /root/app/vite.config.js
COPY .env.example /root/app/.env
ADD resources/css /root/app/resources/css
ADD resources/js /root/app/resources/js

WORKDIR /root/app

RUN mkdir -p /root/app/{public,bootstrap} &&\
    bun install --frozen-lockfile &&\
    bunx vite build &&\
    bunx vite build --ssr &&\
    bun build --production \
              --target=bun \
              --chunk-naming="chunks/[name]-[hash].js" \
              --outdir=bootstrap/ssr-bun \
              bootstrap/ssr/index.js \
              bootstrap/ssr/worker.js

# ========================================================== #
#  Cleanup Stage
# ========================================================== #
FROM base AS cleanup

ADD . /app

RUN cd /app &&\
    rm -rf ./app/Providers/TelescopeServiceProvider.php &&\
    rm -rf ./config/telescope.php &&\
    rm -rf ./database/migrations/0001_01_01_000003_create_telescope_entries_table.php &&\
    rm -rf ./config/ide-helper.php &&\
    rm -rf ./resources/{js,css} &&\
    cp .env.example .env &&\
    find storage -type d -print > struct.txt &&\
    chmod +x docker/*

# ========================================================== #
#  Final Build Stage
# ========================================================== #
FROM base AS final

COPY --chown=www-data:www-data --from=cleanup /app /app

COPY --chown=www-data:www-data --from=frontend /root/app/public/build /app/public/build
COPY --chown=www-data:www-data --from=frontend /root/app/bootstrap/ssr-bun /app/bootstrap/ssr
COPY --chown=www-data:www-data --from=vendor /root/app/vendor /app/vendor

WORKDIR /app

RUN ln -s /usr/local/bin/bun /usr/local/bin/bunx &&\
    /app/docker/as-web.sh php artisan package:discover

ENV INERTIA_SSR_ENABLED=true
ENV STACKER_CONFIG_PATH=/app/docker/stacker.yaml

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY --from=stacker /usr/local/bin/stacker /usr/local/bin/stacker
COPY --from=bun /usr/local/bin/bun /usr/local/bin/bun

ENTRYPOINT ["/usr/bin/bash", "/app/docker/entrypoint.sh"]
CMD ["/usr/local/bin/stacker"]
