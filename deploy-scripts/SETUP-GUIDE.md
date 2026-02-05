# 🚀 Noreko Deploy Setup Guide

## Översikt

Detta system låter dig jobba direkt i dev-miljön och deploya till produktion med ett enkelt kommando.

```
Dev-miljö (du kodar här) → Kommando → Prod-miljö (live-sajten)
```

---

## 📋 Steg-för-steg Installation

### 1. Skapa Mappar på Servern

Logga in på din server och skapa mappstrukturen:

```bash
sudo mkdir -p /var/www/noreko-dev/frontend
sudo mkdir -p /var/www/noreko-dev/backend
sudo mkdir -p /var/www/noreko-prod/frontend
sudo mkdir -p /var/www/noreko-prod/backend
sudo mkdir -p /var/www/noreko-backups

# Sätt rätt ägare
sudo chown -R $USER:www-data /var/www/noreko-*
```

### 2. Flytta Nuvarande Kod till Dev

Om du redan har kod på servern, flytta den till dev:

```bash
# Säkerhetskopiera först!
sudo cp -r /var/www/html /var/www/backup-$(date +%Y%m%d)

# Flytta till dev (anpassa sökvägar efter ditt nuvarande setup)
sudo mv /var/www/html/frontend/* /var/www/noreko-dev/frontend/
sudo mv /var/www/html/backend/* /var/www/noreko-dev/backend/
```

### 3. Konfigurera Apache2

Skapa två Virtual Hosts - en för dev och en för prod.

#### A. Dev Virtual Host

Skapa filen: `/etc/apache2/sites-available/noreko-dev.conf`

```apache
<VirtualHost *:80>
    ServerName dev.noreko.se
    # Eller använd IP: ServerName 192.168.1.100:8080
    
    DocumentRoot /var/www/noreko-dev/frontend
    
    <Directory /var/www/noreko-dev/frontend>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
        
        # Angular routing support
        RewriteEngine On
        RewriteBase /
        RewriteRule ^index\.html$ - [L]
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule . /index.html [L]
    </Directory>
    
    # PHP Backend
    Alias /api /var/www/noreko-dev/backend
    <Directory /var/www/noreko-dev/backend>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
        
        # PHP handling
        <FilesMatch \.php$>
            SetHandler application/x-httpd-php
        </FilesMatch>
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/noreko-dev-error.log
    CustomLog ${APACHE_LOG_DIR}/noreko-dev-access.log combined
</VirtualHost>
```

#### B. Prod Virtual Host

Skapa filen: `/etc/apache2/sites-available/noreko-prod.conf`

```apache
<VirtualHost *:80>
    ServerName noreko.se
    ServerAlias www.noreko.se
    
    DocumentRoot /var/www/noreko-prod/frontend
    
    <Directory /var/www/noreko-prod/frontend>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
        
        # Angular routing support
        RewriteEngine On
        RewriteBase /
        RewriteRule ^index\.html$ - [L]
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule . /index.html [L]
    </Directory>
    
    # PHP Backend
    Alias /api /var/www/noreko-prod/backend
    <Directory /var/www/noreko-prod/backend>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
        
        <FilesMatch \.php$>
            SetHandler application/x-httpd-php
        </FilesMatch>
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/noreko-prod-error.log
    CustomLog ${APACHE_LOG_DIR}/noreko-prod-access.log combined
</VirtualHost>
```

#### C. Aktivera Sites och Moduler

```bash
# Aktivera rewrite module för Angular routing
sudo a2enmod rewrite
sudo a2enmod php8.2  # Eller din PHP-version

# Aktivera sites
sudo a2ensite noreko-dev.conf
sudo a2ensite noreko-prod.conf

# Inaktivera default site om du vill
sudo a2dissite 000-default.conf

# Testa konfigurationen
sudo apache2ctl configtest

# Starta om Apache
sudo systemctl restart apache2
```

### 4. Uppdatera Deploy Scripts

Öppna `deploy-scripts/deploy-to-prod.sh` och ändra sökvägarna längst upp om de inte stämmer:

```bash
DEV_FRONTEND="/var/www/noreko-dev/frontend"
DEV_BACKEND="/var/www/noreko-dev/backend"
PROD_FRONTEND="/var/www/noreko-prod/frontend"
PROD_BACKEND="/var/www/noreko-prod/backend"
```

### 5. Gör Scripts Körbara

```bash
cd deploy-scripts
chmod +x *.sh
```

---

## 🎯 Användning

### Daglig Utveckling

Jobba direkt i dev-miljön som vanligt:

```bash
# Redigera filer i dev
vim /var/www/noreko-dev/backend/classes/TvattlinjeController.php

# Testa på dev-domänen
# http://dev.noreko.se
```

### Deploy till Produktion

När du är redo att pusha till live:

```bash
cd /path/to/Noreko_frontend/deploy-scripts

# Säker deploy med backup
sudo ./deploy-to-prod.sh

# ELLER snabb deploy utan säkerhetskontroller
sudo ./quick-deploy.sh
```

### Om Något Går Fel

Återställ från backup:

```bash
# Visa backups och välj
sudo ./rollback-prod.sh

# Eller direkt med timestamp
sudo ./rollback-prod.sh 20260203_143022
```

---

## 🔧 Alternativa Konfigurationer

### Om du inte har separat domän för dev

Använd port 8080 för dev:

```apache
<VirtualHost *:8080>
    ServerName din-server-ip
    # ... resten av config
</VirtualHost>
```

Lägg till i `/etc/apache2/ports.conf`:
```
Listen 8080
```

Öppna port i firewall:
```bash
sudo ufw allow 8080
```

### Om du kör allt från samma domän

Använd subdomäner med olika portar eller sökvägar:
- Prod: `noreko.se/`
- Dev: `noreko.se:8080/` eller `dev.noreko.se/`

---

## 📝 Kom ihåg

1. **Bygg alltid frontend** innan deploy (scriptet gör detta automatiskt)
2. **Backups skapas automatiskt** vid varje deploy
3. **Gamla backups rensas** automatiskt (10 senaste behålls)
4. **Kör som sudo** för att kunna sätta www-data permissions

---

## 🐛 Felsökning

### Deploy fungerar inte?

```bash
# Kontrollera permissions
ls -la /var/www/noreko-*

# Kontrollera Apache-loggar
sudo tail -f /var/log/apache2/noreko-prod-error.log

# Testa Apache-config
sudo apache2ctl configtest
```

### Frontend visar inte rätt?

```bash
# Kontrollera build output
cd noreko-frontend
npm run build

# Kolla dist-mappen
ls -la dist/noreko-frontend/browser/
```

### PHP fungerar inte?

```bash
# Kontrollera PHP-version
php -v

# Testa PHP
echo "<?php phpinfo(); ?>" | sudo tee /var/www/noreko-prod/backend/test.php
# Besök: http://din-site/api/test.php
```

---

## ✅ Checklista

- [ ] Mappstruktur skapad
- [ ] Apache Virtual Hosts konfigurerade
- [ ] Sites aktiverade
- [ ] Rewrite module aktiverat
- [ ] Scripts körbara (chmod +x)
- [ ] Sökvägar i scripts uppdaterade
- [ ] Testat dev-miljö
- [ ] Testat deploy
- [ ] Testat rollback

---

**Lycka till! 🎉**

Vid frågor, kolla loggarna eller hör av dig!
