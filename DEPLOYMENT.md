# Panduan Deployment ke VPS

## Persiapan di Local (Sebelum Push)

1. Pastikan semua testing lokal sudah pass
2. Jalankan cache optimization:
   ```bash
   php artisan config:clear
   php artisan cache:clear
   php artisan route:clear
   ```

3. Push ke GitHub:
   ```bash
   git add .
   git commit -m "chore: production ready"
   git push origin main
   ```

## Deployment ke VPS (Ubuntu 22.04 / Debian)

### 1. Clone Repository
```bash
cd /var/www
sudo git clone https://github.com/Bramramadhani/invenMGTM.git
cd invenMGTM
```

### 2. Setup Environment File
```bash
# Copy template
cp .env.example .env

# Edit dengan credentials produksi
nano .env
```

**Pastikan ubah nilai-nilai ini di `.env`:**
```dotenv
APP_ENV=production          # ← UBAH dari 'local'
APP_DEBUG=false             # ← UBAH dari 'true' — jangan expose error
APP_URL=https://your-domain.com  # ← UBAH ke domain Anda

DB_HOST=127.0.0.1           # atau IP server MySQL
DB_DATABASE=invenwarehouse_prod
DB_USERNAME=app_user        # jangan gunakan root
DB_PASSWORD=your-strong-password-here

SESSION_DRIVER=database     # REKOMENDASI: ubah dari 'file' ke 'database'
LOG_LEVEL=warning           # REKOMENDASI: ubah dari 'debug'
CACHE_DRIVER=file           # atau 'redis' jika tersedia
```

### 3. Install Dependencies
```bash
# Install composer packages (tanpa dev dependencies)
composer install --no-dev --optimize-autoloader

# Install Node packages dan build assets
npm ci
npm run prod
```

### 4. Generate Application Key
```bash
php artisan key:generate --force
```

### 5. Migrate Database
```bash
# Jalankan migrations
php artisan migrate --force

# Jika menggunakan SESSION_DRIVER=database, buat sessions table
php artisan session:table
php artisan migrate --force
```

### 6. Optimize untuk Production
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

### 7. Setup File Permissions & Storage
```bash
# Create storage symlink
php artisan storage:link

# Set correct ownership (www-data adalah user Nginx)
sudo chown -R www-data:www-data storage bootstrap/cache public
sudo chmod -R 775 storage bootstrap/cache
```

### 8. Configure Nginx

Buat file konfigurasi Nginx:
```bash
sudo nano /etc/nginx/sites-available/invenMGTM
```

Isi dengan:
```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.com;
    
    root /var/www/invenMGTM/public;
    index index.php;
    
    # SSL Certificate (gunakan Let's Encrypt)
    ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    
    # Logging
    access_log /var/log/nginx/invenMGTM_access.log;
    error_log /var/log/nginx/invenMGTM_error.log;
    
    # Gzip compression
    gzip on;
    gzip_types text/plain text/css text/javascript application/json;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    
    # Laravel routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_param HTTP_AUTHORIZATION $http_authorization;
    }
    
    # Deny access to hidden files
    location ~ /\.(?!well-known).* {
        deny all;
    }
}

# Redirect HTTP → HTTPS
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$server_name$request_uri;
}
```

Enable site:
```bash
sudo ln -s /etc/nginx/sites-available/invenMGTM /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

### 9. Setup SSL Certificate (Let's Encrypt)
```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot certonly --nginx -d your-domain.com
```

### 10. Setup PHP-FPM (jika belum)
```bash
sudo apt install -y php8.1-fpm php8.1-mysql php8.1-xml php8.1-mbstring php8.1-zip php8.1-gd php8.1-curl php8.1-intl
sudo systemctl start php8.1-fpm
sudo systemctl enable php8.1-fpm
```

### 11. Setup Supervisor untuk Queue Workers (Opsional)
Jika menggunakan background jobs:
```bash
sudo apt install -y supervisor
sudo nano /etc/supervisor/conf.d/invenMGTM.conf
```

Isi:
```ini
[program:invenMGTM-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/invenMGTM/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisor/invenMGTM-worker.log
user=www-data
```

Start:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start invenMGTM-worker:*
```

### 12. Setup Cron untuk Scheduled Tasks
```bash
sudo crontab -e
```

Tambahkan:
```cron
* * * * * cd /var/www/invenMGTM && php artisan schedule:run >> /dev/null 2>&1
```

---

## Verification & Testing

### Cek apakah aplikasi berjalan:
```bash
# SSH ke VPS
ssh user@your-vps-ip

# Cek Laravel status
php artisan tinker
>> \App\Models\User::count()  # seharusnya return >0
>> exit

# Cek logs
tail -f /var/log/nginx/invenMGTM_error.log
tail -f storage/logs/laravel.log
```

### Test fitur utama:
1. Login ke `/login` dengan kredensial admin
2. Cek dashboard
3. Buat PO baru
4. Receive & Post receipt
5. Verifikasi stock movements

---

## Backup & Rollback

### Backup Database sebelum production:
```bash
mysqldump -u root -p invenwarehouse > backup_$(date +%Y%m%d_%H%M%S).sql
```

### Rollback jika ada issue:
```bash
# Restore database
mysql -u root -p invenwarehouse < backup_YYYYMMDD_HHMMSS.sql

# Revert code
git reset --hard HEAD~1
php artisan migrate:rollback
```

---

## Monitoring & Logs

### View aplikasi logs:
```bash
tail -f /var/www/invenMGTM/storage/logs/laravel.log
```

### Nginx logs:
```bash
tail -f /var/log/nginx/invenMGTM_error.log
tail -f /var/log/nginx/invenMGTM_access.log
```

### System resources:
```bash
htop        # CPU, Memory, Processes
df -h       # Disk usage
free -h     # RAM status
```

---

## Troubleshooting

### 500 Internal Server Error
```bash
# Check PHP errors
php artisan tinker
php artisan migrate:status
php artisan optimize:clear
```

### Permission Denied
```bash
sudo chown -R www-data:www-data /var/www/invenMGTM
sudo chmod -R 775 storage bootstrap/cache
```

### Database Connection Error
```bash
# Check MySQL status
sudo systemctl status mysql
# Check .env DB credentials
cat .env | grep DB_
```

### Queue not working
```bash
# Check Supervisor
sudo supervisorctl status
sudo supervisorctl restart invenMGTM-worker:*
```

---

## Support

Jika ada masalah, cek:
1. `.env` sudah sesuai credentials VPS?
2. Database sudah di-migrate?
3. Nginx/PHP-FPM sudah restart?
4. File permissions sudah benar?
5. SSL certificate sudah valid?

Good luck! 🚀
