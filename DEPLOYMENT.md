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

MIDTRANS_SERVER_KEY=your-production-server-key
MIDTRANS_CLIENT_KEY=your-production-client-key
MIDTRANS_IS_PRODUCTION=true
MIDTRANS_EXPIRY_MINUTES=60
MIDTRANS_AUTO_REFUND=false

BITESHIP_API_KEY=your-production-api-key
BITESHIP_WEBHOOK_SECRET=your-random-secret
```

Set `MIDTRANS_AUTO_REFUND=true` only after section 10 is running, because refunds are sent by the queue worker.

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

## 10. Run the queue worker and the scheduler (required)

The store depends on two background processes. If either one stops, these features stop silently, without any error on the website.

| Process | Used for | What happens if it is not running |
|---------|----------|-----------------------------------|
| `php artisan queue:work` | Automatic refunds (`RefundLatePayment`) | A payment that arrives after the order was closed is never refunded. It stays `refund_required` until an admin handles it. |
| `php artisan schedule:run`, every minute | `orders:expire-unpaid` (every minute), `shipments:sync-tracking` (hourly) | Unpaid orders never expire and their stock stays locked. Tracking is not updated. |

Cron entry for the scheduler:

```bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

Keep the worker alive with a process manager (Supervisor, systemd, or the process manager in CloudPanel) so it restarts after a crash or a server reboot:

```bash
php artisan queue:work --tries=4 --sleep=3 --max-time=3600
```

After every deploy, make the worker load the new code:

```bash
php artisan queue:restart
```

Shared hosting that cannot keep a process running is not suitable for automatic refunds. Use a VPS, or keep `MIDTRANS_AUTO_REFUND=false` and refund from the admin panel by hand.

Things to watch:

- The `failed_jobs` table should stay empty. Check it regularly.
- The "Needs refund" filter in the admin panel. An order that stays there means the worker is down or Midtrans rejected the refund. Use "Retry refund" on that order once the cause is fixed, or return the money manually and use "Mark as refunded". Virtual Account payments are always refunded manually.
- The log `storage/logs/laravel.log`: search for "Refund otomatis gagal" and "Midtrans menolak permintaan".

## 11. Final check

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
- A Midtrans payment (QRIS or Virtual Account) can be paid and the order becomes Paid
- Cancel Order works and releases the stock
- The queue worker and the scheduler are running (section 10)
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