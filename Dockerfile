FROM prooph/php:7.2-cli-xdebug

ARG UID=1000

ENV XDEBUG_CONFIG_FILE=/usr/local/etc/php/conf.d/xdebug-cli.ini

RUN cp $XDEBUG_CONFIG_FILE ${XDEBUG_CONFIG_FILE}--disabled && \
	echo "" > $XDEBUG_CONFIG_FILE && \
	apk --no-cache add shadow autoconf gcc musl-dev alpine-sdk zip libzip-dev && \
	usermod -u $UID www-data && \
	pecl install ast && \
	docker-php-ext-enable ast && \
	docker-php-ext-configure zip && docker-php-ext-install zip && \
	# install runkit
    wget https://github.com/runkit7/runkit7/releases/download/2.0.3/runkit7-2.0.3.tgz -O /tmp/runkit.tgz && \
    pecl install /tmp/runkit.tgz && \
    echo -e 'extension=runkit.so\nrunkit.internal_override=On' > /usr/local/etc/php/conf.d/docker-php-ext-runkit.ini

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

ENV WORKDIR=/var/www
WORKDIR $WORKDIR

ENV PATH=$WORKDIR/bin:$WORKDIR/vendor/bin:$PATH

COPY --chown=www-data . $WORKDIR

RUN chown www-data $WORKDIR && \
	chmod 777 $XDEBUG_CONFIG_FILE ${XDEBUG_CONFIG_FILE}--disabled

USER www-data

RUN composer install

ENTRYPOINT ["php", "/var/www/bin/bitrix-cli-install"]
