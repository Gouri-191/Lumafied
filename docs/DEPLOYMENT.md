# AICTE FDR Portal — Production Deployment & Server Configuration

## 1. System Requirements
- **Operating System**: Ubuntu 22.04 LTS / Debian 12 / RHEL 9 (or Windows Server with IIS / WAMP)
- **PHP**: 8.3+ with extensions: `pdo_mysql`, `openssl`, `mbstring`, `fileinfo`, `gd`, `bcmath`, `curl`, `json`
- **Database**: MySQL 8.0+ or MariaDB 10.11+
- **Node.js**: v20+ with npm
- **Web Server**: Nginx or Apache (mod_rewrite enabled)

## 2. Nginx Production Configuration
```nginx
server {
    listen 443 ssl http2;
    server_name fdr-portal.aicte-india.org;

    ssl_certificate /etc/letsencrypt/live/fdr-portal.aicte-india.org/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/fdr-portal.aicte-india.org/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    # Security Headers
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; font-src 'self' data:; img-src 'self' data: blob:;" always;

    root /var/www/aicte-portal/backend/public;
    index index.php index.html;

    # Serve Built Frontend SPA
    location / {
        root /var/www/aicte-portal/frontend/dist;
        try_files $uri $uri/ /index.html;
    }

    # API Routes proxy to Laravel
    location ^~ /api/ {
        root /var/www/aicte-portal/backend/public;
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME /var/www/aicte-portal/backend/public$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    # Deny direct access to vault files and dotfiles
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

## 3. Queue Workers & Cron Scheduler
Configure `systemd` service for background jobs:
```ini
[Unit]
Description=AICTE FDR Portal Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php /var/www/aicte-portal/backend/artisan queue:work --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
```

Configure cron scheduler (`/etc/cron.d/aicte-fdr`):
```cron
* * * * * www-data php /var/www/aicte-portal/backend/artisan schedule:run >> /dev/null 2>&1
```
