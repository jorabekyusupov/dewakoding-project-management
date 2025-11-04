FROM php:8.3-fpm

WORKDIR /var/www

RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    jpegoptim optipng pngquant gifsicle \
    vim \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zlib1g-dev \
    libicu-dev \
    libxslt-dev \
    libpq-dev

RUN apt-get clean && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install pdo pdo_pgsql mbstring zip exif pcntl bcmath opcache intl xsl sockets
RUN curl -fsSL https://nodejs.org/dist/v20.12.0/node-v20.12.0-linux-x64.tar.xz | tar -xJf - -C /usr/local --strip-components=1

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

ARG USER=user
ARG UID=1000
ARG GID=1000
RUN groupadd -g ${GID} ${USER} \
    && useradd -u ${UID} -g ${USER} -m ${USER}

COPY . .
RUN composer install
RUN npm install
RUN npm run build

RUN chown -R ${USER}:${USER} /var/www \
    && chmod -R ug+rwx /var/www/storage /var/www/bootstrap/cache

USER ${USER}

EXPOSE 9000
CMD ["php-fpm"]
