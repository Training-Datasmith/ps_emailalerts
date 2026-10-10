# syntax=docker/dockerfile:1
FROM debian@sha256:7c7b2c966bc9ee8cedfeef67e0e279108992c77681fa595db4a9d65c06ccc587 AS keyring
RUN apt-get update \
    && apt-get install -y --no-install-recommends debian-archive-keyring \
    && rm -rf /var/lib/apt/lists/*

FROM php@sha256:1e4671c8e26df6ae1d99b0e0059cdaa2fe72351c20c136e787dc463a165104e5

COPY --from=keyring /usr/share/keyrings/debian-archive-keyring.gpg /usr/share/keyrings/debian-archive-keyring.gpg

RUN set -eux; \
    printf '%s\n' \
      'deb [signed-by=/usr/share/keyrings/debian-archive-keyring.gpg] http://archive.debian.org/debian buster main' \
      > /etc/apt/sources.list; \
    printf '%s\n' \
      'Acquire::Check-Valid-Until "false";' \
      > /etc/apt/apt.conf.d/99no-check-valid-until; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
      default-libmysqlclient-dev \
      $PHPIZE_DEPS; \
    docker-php-ext-install pdo_mysql; \
    apt-get purge -y --auto-remove $PHPIZE_DEPS; \
    rm -rf /var/lib/apt/lists/*

WORKDIR /work
