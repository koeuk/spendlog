FROM php:8.4-cli

WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    zip \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        bcmath \
        exif \
        pcntl \
        gd \
        zip \
        xml \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Node.js 22
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && npm --version \
    && node --version

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy Laravel project
COPY . .

# Install PHP dependencies
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# Install Vue dependencies
RUN npm install

# Build Vue/Vite
RUN npm run build

# Clear Laravel cache
RUN php artisan config:clear

# Create storage link
RUN php artisan storage:link || true

EXPOSE 8080

# Two processes: the scheduler alongside the web server.
#
# Without the scheduler nothing ever runs spendlog:run-recurring, so a
# recurring rule only became a real row when someone opened the dashboard —
# and never at all for an account that does not log in. schedule:work is the
# long-running form of schedule:run, so no crontab is needed in the image; it
# needs pcntl, installed above.
#
# serve is exec'd so it becomes PID 1 and receives the container's signals,
# and so the container's health follows the web server. The trade-off is that
# a scheduler that dies is not restarted on its own — the platform's restart
# policy only sees serve. Worth replacing with a real process manager, or a
# platform cron hitting `php artisan schedule:run`, if that matters.
CMD ["sh", "-c", "php artisan schedule:work >> /dev/stdout 2>&1 & exec php artisan serve --host=0.0.0.0 --port=${PORT}"]