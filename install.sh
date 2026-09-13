#!/bin/bash

echo "==========================================="
echo "   NEXUS API PANEL - VPS INSTALLER"
echo "==========================================="

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
  echo "Please run as root (use sudo bash install.sh)"
  exit 1
fi

echo "[1/5] Updating system packages..."
apt update && apt upgrade -y

echo "[2/5] Installing Nginx, PHP, SQLite, and Git..."
apt install -y nginx git curl
apt install -y php-fpm php-sqlite3 php-curl php-cli php-xml

echo "[3/5] Downloading panel from GitHub..."
cd /var/www/html
rm -rf *  # Remove default nginx files
git clone https://github.com/swapiolds/apisell_panel.git .

echo "[4/5] Setting up permissions for SQLite database..."
chown -R www-data:www-data /var/www/html
find /var/www/html -type d -exec chmod 755 {} \;
find /var/www/html -type f -exec chmod 644 {} \;
# Give full write permissions to the database and its folder so PHP can update it
chmod 777 /var/www/html
chmod 777 /var/www/html/osint_api.db

echo "[5/5] Configuring Nginx..."
PHP_SOCK=$(find /var/run/php -name "php*-fpm.sock" | head -n 1)

cat > /etc/nginx/sites-available/default << EOF
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    root /var/www/html;
    index index.php index.html index.htm;
    server_name _;

    location / {
        try_files \$uri \$uri/ =404;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:$PHP_SOCK;
    }

    location ~ /\.ht {
        deny all;
    }
}
EOF

# Restart services
systemctl restart nginx
systemctl restart php*-fpm

echo "==========================================="
echo "   INSTALLATION COMPLETE! 🚀"
echo "   Your panel is now live on your VPS IP address."
echo "   Default Admin Password: admin123"
echo "==========================================="
