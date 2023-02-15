FROM php:8.2.3-fpm-alpine3.17

RUN apk upgrade --no-cache && apk add --no-cache openssl

# Install Composer
RUN curl https://getcomposer.org/composer.phar > /usr/sbin/composer && chmod +x /usr/sbin/composer

CMD ["/var/app/bootstrap"]
WORKDIR /var/app

# Set up bootstrap, point at /var/app
ENV LAMBDA_TASK_ROOT="/var/app"
COPY runtime/bootstrap-container /var/app/bootstrap
COPY runtime/bootstrap.php /var/app/bootstrap.php

# set up app; order of operations optimized for maximum layer reuse
COPY composer.lock /var/app/composer.lock
COPY composer.json /var/app/composer.json
RUN cd /var/app && CI=true php /usr/sbin/composer install --prefer-dist -o
COPY *.php /var/app/

######## Uncomment these lines to add in the Lambda RIE

# Uncomment one of the below depending on local system architecture
# x86
# COPY rie/aws-lambda-rie /var/app/rie
# Arm
# COPY rie/aws-lambda-rie-arm64 /var/app/rie

# Run RIE, and have it run our bootstrap
# CMD ["/var/app/rie", "/var/app/bootstrap"]
# EXPOSE 8080