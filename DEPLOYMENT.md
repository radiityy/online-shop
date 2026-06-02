# NEVERENDING Deployment Guide

## 1. Pull latest code

```bash
git pull origin develop
```

## 2. Install backend dependencies

```bash
composer install --no-dev --optimize-autoloader
```

## 3. Install frontend dependencies

```bash
npm install
npm run build
```

## 4. Configure environment

Copy `.env.example` to `.env`, then update the production values.

```env
APP_NAME=NEVERENDING
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_PORT=3306
DB_DATABASE=your-db-name
DB_USERNAME=your-db-user
DB_PASSWORD=your-db-password

FILESYSTEM_DISK=public
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

ADMIN_NAME="NEVERENDING Admin"
ADMIN_EMAIL="admin@your-domain.com"
ADMIN_PASSWORD="your-secure-password"
```

## 5. Generate app key

Run this only if `.env` does not have `APP_KEY` yet.

```bash
php artisan key:generate
```

## 6. Run migration

```bash
php artisan migrate --force
```

## 7. Create storage link

```bash
php artisan storage:link
```

## 8. Create production admin

Make sure `ADMIN_NAME`, `ADMIN_EMAIL`, and `ADMIN_PASSWORD` already exist in `.env`.

```bash
php artisan db:seed --class=ProductionAdminSeeder
```

## 9. Cache production config

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 10. Final check

Open these pages in browser:

```txt
/
 /products
 /admin
 /login
 /cart
```

Check these manually:

```txt
- Homepage loads correctly
- Product images appear
- Admin can login
- Customer can register/login
- Product detail can be opened
- Add to cart works
- Checkout works
- Payment proof upload works
- Admin can update order status
- Customer can see order progress
```

## Important production notes

```txt
APP_DEBUG must be false.
APP_ENV must be production.
APP_URL must use the real domain.
.env must never be committed.
Database backup should be enabled.
Use HTTPS.
Keep admin password strong.
```
