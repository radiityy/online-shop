# NEVERENDING Final Launch Checklist

## 1. Repository Status

* [x] `develop` branch sudah up to date
* [x] Semua perubahan sudah di-commit
* [x] Semua perubahan sudah di-push ke GitHub
* [x] `git status` menunjukkan working tree clean

```bash
git status
```

## 2. Local Final Test

* [x] Backend test sukses
* [x] Frontend build sukses
* [x] Tidak ada error migration
* [x] Tidak ada error route/cache

```bash
php artisan optimize:clear
php artisan test
npm run build
```

## 3. Storefront Check

* [x] Homepage terbuka
* [x] Banner aktif muncul
* [x] Product list terbuka
* [x] Product detail terbuka
* [x] Search produk berjalan
* [x] Filter category berjalan
* [x] Sale price tampil benar
* [x] Sold out tampil benar di product list
* [x] Sold out tidak muncul di homepage
* [x] Mobile layout rapi

Pages to check:

```txt
/
 /products
 /products/{product-slug}
 /about
 /faq
 /shipping
 /returns
```

## 4. Customer Flow Check

* [x] Customer bisa register
* [x] Customer bisa login
* [x] Customer bisa edit profile
* [x] Customer bisa tambah alamat
* [x] Customer bisa set default address
* [x] Customer bisa add to bag
* [x] Customer bisa update quantity cart
* [x] Customer bisa checkout
* [x] Stock berkurang setelah checkout
* [x] Customer bisa upload payment proof
* [x] Customer bisa lihat order detail
* [x] Customer bisa lihat order progress

## 5. Admin Flow Check

* [x] Admin bisa login ke `/admin`
* [x] Customer biasa tidak bisa masuk `/admin`
* [x] Admin bisa manage banner
* [x] Admin bisa manage category
* [x] Admin bisa manage product
* [x] Admin bisa manage product image
* [x] Admin bisa manage product variant/stock
* [x] Admin bisa melihat order
* [x] Admin bisa approve payment
* [x] Admin bisa reject payment
* [x] Admin bisa update shipping status
* [x] Admin bisa isi tracking number/resi
* [x] Admin bisa complete order

## 6. Order Safety Check

* [x] Order pending bisa upload payment proof
* [x] Order paid tidak bisa upload proof ulang
* [x] Order failed tidak bisa upload proof ulang
* [x] Order cancelled tidak bisa upload proof ulang
* [x] Payment failed mengembalikan stock
* [x] Cancelled order mengembalikan stock
* [x] Stock tidak kembali dua kali
* [x] Released/cancelled order tidak bisa diubah kembali ke paid
* [x] Order progress tampil customer-friendly

## 7. Security Check

* [x] `.env` tidak masuk GitHub
* [x] `APP_DEBUG=false` untuk production
* [x] `APP_ENV=production` untuk production
* [x] Admin password kuat
* [x] Upload payment proof hanya image
* [x] Upload image dibatasi ukuran
* [x] Customer tidak bisa akses order customer lain
* [x] Customer tidak bisa edit address customer lain
* [x] Customer tidak bisa edit cart item customer lain
* [x] Route customer action sudah rate limited

## 8. Production Environment Check

Production `.env` wajib berisi:

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

## 9. Production Commands

Run on server:

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build

php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan db:seed --class=ProductionAdminSeeder

php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 10. Soft Launch Rule

Before public launch:

* [x] Test from laptop
* [x] Test from phone
* [x] Test using customer account
* [x] Test using admin account
* [x] Test one full order flow
* [x] Confirm payment proof upload works on hosting
* [x] Confirm product images load on hosting
* [x] Confirm storage link works
* [x] Confirm database backup is available

## Launch Status

```txt
Status:
[ ] Not ready
[ ] Ready for staging
[ ] Ready for soft launch
[ ] Ready for public launch
```
