# FreeKliping Deployment Guide

Panduan ini untuk deploy FreeKliping ke VPS/staging/production. Aplikasi ini bukan app Laravel biasa saja: proses video membutuhkan queue worker, scheduler, `ffmpeg`, `yt-dlp`, dan storage yang tidak hilang saat restart.

## Status Kesiapan

Layak deploy ke staging atau beta tertutup setelah semua checklist di bawah terpenuhi.

Belum disarankan dibuka public ramai sebelum:

- Queue worker production berjalan stabil.
- Scheduler cleanup berjalan.
- Storage output clip persistent.
- Monitoring basic tersedia untuk app, queue, disk, dan log.

## Requirement Server

Minimum awal yang realistis:

- Ubuntu 22.04/24.04 atau distro Linux setara.
- PHP 8.5 dengan extension Laravel umum: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`.
- Composer 2.
- Node.js 22 dan npm.
- MySQL 8.4 atau MariaDB kompatibel.
- Redis opsional. Saat ini `.env.example` default memakai `QUEUE_CONNECTION=database`.
- `ffmpeg`.
- `yt-dlp`.
- Node runtime tersedia untuk yt-dlp caption extraction.
- Python hanya wajib jika smart crop detector atau Whisper diaktifkan.

## Service Yang Wajib Berjalan

Production minimal membutuhkan tiga proses:

- Web server: Nginx/Apache + PHP-FPM.
- Queue worker: menjalankan job analisis dan render clip.
- Scheduler: menjalankan cleanup periodik.

Jika queue worker tidak berjalan, user akan stuck di status `Menunggu antrian render` / 5%.

## Environment Production

Gunakan `.env.example` sebagai dasar, lalu set minimal:

```env
APP_NAME=FreeKliping
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-kamu.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=freecliping
DB_USERNAME=freecliping
DB_PASSWORD=ISI_PASSWORD_KUAT

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

FILESYSTEM_DISK=local
FREEKLIPING_OUTPUT_DISK=local

FREEKLIPING_YT_DLP_BINARY=yt-dlp
FREEKLIPING_YT_DLP_JS_RUNTIME=node
FREEKLIPING_FFMPEG_BINARY=ffmpeg
FREEKLIPING_FFMPEG_PRESET=veryfast

FREEKLIPING_MAX_CLIP_LENGTH=180
FREEKLIPING_RETENTION_HOURS=1
FREEKLIPING_MAX_CONCURRENT_CLIPS=10
FREEKLIPING_MAX_PENDING_PER_IP=3
FREEKLIPING_MAX_CONCURRENT_ANALYSES=8
FREEKLIPING_MAX_PENDING_ANALYSES_PER_IP=2

DB_QUEUE_RETRY_AFTER=2100
```

Untuk production public, pertimbangkan Object Storage seperti S3/R2 daripada local disk. Kalau memakai local disk, pastikan `storage/` berada di disk persistent dan cukup besar.

## First Deploy

Contoh flow deploy manual di VPS:

```bash
cd /var/www
git clone <repo-url> freecliping
cd freecliping

cp .env.example .env
composer install --no-dev --optimize-autoloader
npm ci
npm run build

php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan optimize
```

Set permission:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rw storage bootstrap/cache
```

Verifikasi:

```bash
php artisan about
php artisan route:list --except-vendor
php artisan queue:failed
curl -I https://domain-kamu.com/up
```

## Deploy Update

Setiap deploy update:

```bash
cd /var/www/freecliping
git pull

composer install --no-dev --optimize-autoloader
npm ci
npm run build

php artisan migrate --force
php artisan optimize
php artisan queue:restart
php artisan schedule:interrupt
```

`queue:restart` penting karena worker adalah proses long-running dan tidak otomatis membaca code baru.

## Queue Worker

Command worker yang sesuai dengan timeout job video saat ini:

```bash
php artisan queue:work --timeout=1900 --tries=1
```

Contoh Supervisor config:

```ini
[program:freecliping-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/freecliping/artisan queue:work --timeout=1900 --tries=1
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/freecliping/storage/logs/worker.log
stopwaitsecs=2100
```

Reload Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

Kalau traffic mulai naik, naikkan `numprocs` secara hati-hati. Jangan terlalu tinggi di VPS kecil karena `ffmpeg` CPU intensive.

## Scheduler

Tambahkan cron untuk user yang menjalankan aplikasi:

```cron
* * * * * cd /var/www/freecliping && php artisan schedule:run >> /dev/null 2>&1
```

Scheduler saat ini menjalankan:

- `clips:prune` setiap jam untuk cleanup output clip.
- `cache:clear` harian.

## Nginx Contoh

```nginx
server {
    listen 80;
    server_name domain-kamu.com;
    root /var/www/freecliping/public;

    index index.php;

    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Aktifkan HTTPS dengan Certbot atau reverse proxy yang sudah menyediakan TLS.

## Binary Check

Pastikan binary bisa dipanggil oleh user worker/web:

```bash
which ffmpeg
ffmpeg -version

which yt-dlp
yt-dlp --version

which node
node --version
```

Jika path berbeda, set:

```env
FREEKLIPING_FFMPEG_BINARY=/usr/bin/ffmpeg
FREEKLIPING_YT_DLP_BINARY=/usr/local/bin/yt-dlp
FREEKLIPING_YT_DLP_JS_RUNTIME=/usr/bin/node
```

## Health Check

Laravel health route tersedia di:

```text
/up
```

Gunakan untuk uptime monitor atau load balancer.

Queue health manual:

```bash
php artisan queue:monitor database:default --max=10
php artisan queue:failed
```

Log:

```bash
tail -f storage/logs/laravel.log
tail -f storage/logs/worker.log
```

## Smoke Test Setelah Deploy

1. Buka homepage.
2. Paste URL YouTube pendek.
3. Tunggu analisis sampai rekomendasi muncul.
4. Generate satu clip pendek.
5. Pastikan status berubah dari queued ke processing.
6. Pastikan hasil clip bisa preview dan download.
7. Cek tidak ada failed job:

```bash
php artisan queue:failed
```

## Troubleshooting

### Stuck di 5% / Menunggu antrian render

Penyebab paling umum: queue worker tidak berjalan.

```bash
sudo supervisorctl status
php artisan queue:monitor database:default --max=0
php artisan queue:failed
```

Jalankan manual untuk debug:

```bash
php artisan queue:work --timeout=1900 --tries=1 -vvv
```

### Clip gagal diproses

Cek binary:

```bash
ffmpeg -version
yt-dlp --version
node --version
```

Cek log:

```bash
tail -n 200 storage/logs/laravel.log
tail -n 200 storage/logs/worker.log
```

### Output clip hilang

Kemungkinan storage tidak persistent atau scheduler cleanup berjalan sesuai retention.

Cek:

```env
FREEKLIPING_RETENTION_HOURS=1
FREEKLIPING_OUTPUT_DISK=local
```

Untuk public production, gunakan retention pendek untuk biaya, tapi storage tetap harus persistent selama masa download.

## Rollback

Rollback manual sederhana:

```bash
cd /var/www/freecliping
git log --oneline -5
git checkout <commit-sebelumnya>
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart
php artisan schedule:interrupt
```

Catatan: rollback database migration bisa butuh langkah manual kalau migration baru sudah mengubah struktur data production.

## Production Notes

- Jangan set `APP_DEBUG=true` di production.
- Jangan commit `.env`.
- Jangan menjalankan `php artisan serve` untuk production.
- Batasi concurrency sesuai CPU server.
- Pantau disk karena file video cepat menghabiskan storage.
- Untuk public traffic, pertimbangkan Redis queue, object storage, dan worker terpisah dari web server.
