# SpendLog — one-time VPS setup (Ubuntu/Debian)

Everything after this is just `bash deploy/deploy.sh` per release.

## 1. Packages

```bash
sudo add-apt-repository ppa:ondrej/php -y && sudo apt update
sudo apt install -y nginx mysql-server git unzip \
  php8.4-fpm php8.4-mysql php8.4-mbstring php8.4-xml php8.4-curl \
  php8.4-zip php8.4-gd php8.4-bcmath php8.4-intl
# Composer
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
# Node 22
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash - && sudo apt install -y nodejs
```

## 2. Database

```bash
sudo mysql
```
```sql
CREATE DATABASE spendlog CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'spendlog'@'localhost' IDENTIFIED BY 'CHOOSE_A_PASSWORD';
GRANT ALL PRIVILEGES ON spendlog.* TO 'spendlog'@'localhost';
FLUSH PRIVILEGES;
```

## 3. App

```bash
sudo mkdir -p /var/www/spendlog && sudo chown $USER:www-data /var/www/spendlog
git clone git@github.com:koeuk/spendlog.git /var/www/spendlog
cd /var/www/spendlog

cp deploy/env.production.example .env   # then edit: domain, DB password
composer install --no-dev --optimize-autoloader
npm ci && npm run build

php artisan key:generate
php artisan migrate --force
php artisan db:seed --force            # seeds roles/permissions + admin user
php artisan storage:link
php artisan optimize

# Laravel only writes here; group-write for www-data.
sudo chgrp -R www-data storage bootstrap/cache
sudo chmod -R g+w storage bootstrap/cache
```

## 4. Nginx + HTTPS

```bash
sudo cp deploy/nginx.conf /etc/nginx/sites-available/spendlog
sudo nano /etc/nginx/sites-available/spendlog   # replace YOUR_DOMAIN
sudo ln -s /etc/nginx/sites-available/spendlog /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx

sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d YOUR_DOMAIN
```

## 5. Later deploys

```bash
bash /var/www/spendlog/deploy/deploy.sh
```
