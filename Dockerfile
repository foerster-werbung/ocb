FROM dwchiang/nginx-php-fpm:latest

# --- clear nginx static files --- #


# --- docker-octobercms --- #
RUN apt-get update && \
  apt-get install -y cron git-core jq unzip vim zip curl dos2unix libxrender1 \
  libjpeg-dev libpng-dev libpq-dev libsqlite3-dev libwebp-dev libzip-dev libyaml-dev libicu-dev && \
  rm -rf /var/lib/apt/lists/* && \
  docker-php-ext-configure zip --with-zip && \
  docker-php-ext-configure gd --with-jpeg --with-webp && \
  docker-php-ext-install exif gd mysqli opcache pdo_pgsql pdo_mysql zip bcmath intl

# RUN apt-get install -y libxrender1 libfontconfig1 libx11-dev libxtst6

# php options
RUN { \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=4000'; \
    echo 'opcache.revalidate_freq=2'; \
    echo 'opcache.fast_shutdown=1'; \
    echo 'opcache.enable_cli=1'; \
  } > /usr/local/etc/php/conf.d/docker-oc-opcache.ini

RUN { \
    echo 'log_errors=on'; \
    echo 'display_errors=off'; \
    echo 'upload_max_filesize=32M'; \
    echo 'post_max_size=32M'; \
    echo 'memory_limit=128M'; \
  } > /usr/local/etc/php/conf.d/docker-oc-php.ini

# yaml
RUN pecl install yaml-2.2.1 && echo "extension=yaml.so" > /usr/local/etc/php/conf.d/ext-yaml.ini

# xdebug
RUN pecl install xdebug
RUN { \
    echo "#zend_extension=$(find /usr/local/lib/php/extensions/ -name xdebug.so)" ; \
    echo 'xdebug.mode=debug'; \
    echo 'xdebug.remote_autostart=off'; \
    echo 'xdebug.client_host=host.docker.internal'; \
  } > /usr/local/etc/php/conf.d/docker-xdebug-php.ini

# composer
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
# --- /docker-octobercms --- #

# override nginx settings #
RUN rm -Rf /etc/nginx/conf.d/default.conf
COPY ./config/nginx-default.conf /etc/nginx/conf.d/default.conf

# supervisor settings #
COPY ./config/supervisord.conf /etc/supervisord.conf

# --- ocb --- #
COPY ./src /opt/ocb/src
COPY ./composer.json /opt/ocb/composer.json
COPY ./templates /opt/ocb/templates
COPY ./ocb /opt/ocb/ocb
COPY ./ocb-entrypoint /opt/ocb/ocb-entrypoint
COPY ./docker-oc-entrypoint /opt/ocb/docker-oc-entrypoint

RUN cd /opt/ocb && composer install

RUN mkdir /composer \
    && cd /composer \
    && composer require --prefer-dist laravel/envoy --no-interaction

RUN cd /opt/ocb && dos2unix ocb ocb-entrypoint docker-oc-entrypoint
RUN cd /opt/ocb && chmod +x ocb && chmod +x ocb-entrypoint && chmod +x docker-oc-entrypoint

RUN ln -s /opt/ocb/ocb /usr/bin/ocb
RUN ln -s /opt/ocb/ocb-entrypoint /usr/bin/ocb-entrypoint
RUN ln -s /opt/ocb/docker-oc-entrypoint /usr/bin/docker-oc-entrypoint
RUN ln -s /composer/vendor/bin/envoy /usr/bin/envoy

ENTRYPOINT ["ocb-entrypoint"]

CMD ["/docker-entrypoint.sh"]
