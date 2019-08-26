FROM aspendigital/octobercms:latest

RUN apt-get update && apt-get install -y curl dos2unix

RUN composer global require --prefer-dist hirak/prestissimo --no-interaction

COPY ./src /opt/ocb/src
COPY ./composer.json /opt/ocb/composer.json
COPY ./templates /opt/ocb/templates
COPY ./ocb /opt/ocb/ocb
COPY ./ocb-entrypoint /opt/ocb/ocb-entrypoint

RUN cd /opt/ocb && composer install

RUN mkdir /composer \
    && cd /composer \
    && composer require --prefer-dist laravel/envoy --no-interaction

RUN cd /opt/ocb && dos2unix ocb ocb-entrypoint
RUN cd /opt/ocb && chmod +x ocb && chmod +x ocb-entrypoint

RUN ln -s /opt/ocb/ocb-entrypoint /usr/bin/ocb-entrypoint
RUN ln -s /opt/ocb/ocb /usr/bin/ocb
RUN ln -s /composer/vendor/bin/envoy /usr/bin/envoy

WORKDIR /var/www/html

ENTRYPOINT ["ocb-entrypoint"]
CMD ["apache2-foreground"]
