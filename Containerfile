# abraflexi-revolut

FROM php:8.2-cli
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && install-php-extensions gettext intl zip
COPY src /usr/src/abraflexi-revolut/src
RUN sed -i -e 's/..\/.env//' /usr/src/abraflexi-revolut/src/*.php
COPY composer.json /usr/src/abraflexi-revolut
WORKDIR /usr/src/abraflexi-revolut
RUN curl -s https://getcomposer.org/installer | php
RUN ./composer.phar install -o -a
WORKDIR /usr/src/abraflexi-revolut/src
CMD [ "php", "./abraflexi-revolut.php" ]
