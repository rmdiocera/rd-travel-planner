FROM serversideup/php:8.5-fpm-nginx

USER root
RUN install-php-extensions gd

USER www-data