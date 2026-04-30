#!/usr/bin/env bash
# =============================================================================
# WatchBoard — Fresh-server setup script
# Target: Ubuntu 24.04 LTS (Hetzner CX22 / DigitalOcean Droplet / any VPS)
# Run as root: bash deploy/setup.sh
# =============================================================================
set -euo pipefail

APP_USER="deploy"
APP_DIR="/var/www/watchboard"
PHP="8.4"
NODE="20"
DOMAIN="watchboard.app"
REPO="https://github.com/gemanzo/watchboard.git"   # change if private

info()  { echo -e "\n\033[1;34m▶ $*\033[0m"; }
ok()    { echo -e "\033[1;32m✔ $*\033[0m"; }
die()   { echo -e "\033[1;31m✖ $*\033[0m" >&2; exit 1; }

[[ $EUID -eq 0 ]] || die "Run as root (sudo bash deploy/setup.sh)"

# ── 1. System update ──────────────────────────────────────────────────────────
info "Updating system packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq && apt-get upgrade -y -qq
apt-get install -y -qq curl wget git unzip supervisor logrotate ufw fail2ban

# ── 2. PHP 8.4 ───────────────────────────────────────────────────────────────
info "Installing PHP $PHP"
add-apt-repository -y ppa:ondrej/php
apt-get update -qq
apt-get install -y -qq \
  php${PHP}-fpm php${PHP}-cli \
  php${PHP}-pgsql php${PHP}-redis \
  php${PHP}-mbstring php${PHP}-xml php${PHP}-curl \
  php${PHP}-zip php${PHP}-bcmath php${PHP}-pcntl \
  php${PHP}-intl php${PHP}-gd

# Tune PHP-FPM for production
sed -i 's/^;pm.max_children.*/pm.max_children = 20/'   /etc/php/${PHP}/fpm/pool.d/www.conf
sed -i 's/^;pm.start_servers.*/pm.start_servers = 4/'  /etc/php/${PHP}/fpm/pool.d/www.conf
systemctl enable php${PHP}-fpm && systemctl restart php${PHP}-fpm
ok "PHP $PHP ready"

# ── 3. Nginx ──────────────────────────────────────────────────────────────────
info "Installing Nginx"
apt-get install -y -qq nginx
systemctl enable nginx
ok "Nginx ready"

# ── 4. PostgreSQL ─────────────────────────────────────────────────────────────
info "Installing PostgreSQL"
apt-get install -y -qq postgresql postgresql-contrib
systemctl enable postgresql

# Create DB user & database
sudo -u postgres psql -tc "SELECT 1 FROM pg_roles WHERE rolname='watchboard'" \
  | grep -q 1 || sudo -u postgres psql -c "CREATE USER watchboard WITH CREATEDB;"
sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname='watchboard'" \
  | grep -q 1 || sudo -u postgres createdb watchboard -O watchboard
ok "PostgreSQL ready — set a password: sudo -u postgres psql -c \"\\password watchboard\""

# ── 5. Redis ──────────────────────────────────────────────────────────────────
info "Installing Redis"
apt-get install -y -qq redis-server
systemctl enable redis-server && systemctl start redis-server
ok "Redis ready"

# ── 6. Node.js $NODE ─────────────────────────────────────────────────────────
info "Installing Node.js $NODE"
curl -fsSL https://deb.nodesource.com/setup_${NODE}.x | bash - > /dev/null
apt-get install -y -qq nodejs
ok "Node $(node -v) ready"

# ── 7. Composer ───────────────────────────────────────────────────────────────
info "Installing Composer"
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
ok "Composer $(composer --version 2>/dev/null | head -1) ready"

# ── 8. Certbot ────────────────────────────────────────────────────────────────
info "Installing Certbot"
apt-get install -y -qq certbot python3-certbot-nginx
ok "Certbot ready"

# ── 9. Deploy user ────────────────────────────────────────────────────────────
info "Creating deploy user: $APP_USER"
id "$APP_USER" &>/dev/null || useradd -m -s /bin/bash "$APP_USER"
usermod -aG www-data "$APP_USER"
mkdir -p /home/${APP_USER}/.ssh
chmod 700 /home/${APP_USER}/.ssh
touch /home/${APP_USER}/.ssh/authorized_keys
chmod 600 /home/${APP_USER}/.ssh/authorized_keys
chown -R ${APP_USER}:${APP_USER} /home/${APP_USER}/.ssh
ok "User $APP_USER created — add SSH key to /home/$APP_USER/.ssh/authorized_keys"

# ── 10. App directory ─────────────────────────────────────────────────────────
info "Creating app directory: $APP_DIR"
mkdir -p "$APP_DIR"
chown -R ${APP_USER}:www-data "$APP_DIR"
chmod -R 775 "$APP_DIR"

# ── 11. Clone repository ──────────────────────────────────────────────────────
info "Cloning repository"
if [[ ! -d "${APP_DIR}/.git" ]]; then
  sudo -u "$APP_USER" git clone "$REPO" "$APP_DIR"
else
  ok "Repo already cloned — skipping"
fi

# ── 12. Storage & cache dirs ─────────────────────────────────────────────────
info "Setting up storage permissions"
sudo -u "$APP_USER" mkdir -p \
  "${APP_DIR}/storage/app/public" \
  "${APP_DIR}/storage/framework/cache/data" \
  "${APP_DIR}/storage/framework/sessions" \
  "${APP_DIR}/storage/framework/views" \
  "${APP_DIR}/storage/logs"
chown -R ${APP_USER}:www-data "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
chmod -R 775 "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"

# ── 13. Supervisor programs ───────────────────────────────────────────────────
info "Installing Supervisor config"
cp "${APP_DIR}/deploy/supervisor.conf" /etc/supervisor/conf.d/watchboard.conf
# Replace /var/www/watchboard if DEPLOY_PATH differs
sed -i "s|/var/www/watchboard|${APP_DIR}|g" /etc/supervisor/conf.d/watchboard.conf
systemctl enable supervisor && systemctl start supervisor

# ── 14. Log rotation ─────────────────────────────────────────────────────────
info "Installing logrotate config"
cp "${APP_DIR}/deploy/logrotate.conf" /etc/logrotate.d/watchboard
sed -i "s|/var/www/watchboard|${APP_DIR}|g" /etc/logrotate.d/watchboard

# ── 15. Nginx site ────────────────────────────────────────────────────────────
info "Installing Nginx site"
cp "${APP_DIR}/deploy/nginx.conf" /etc/nginx/sites-available/watchboard
sed -i "s|/var/www/watchboard|${APP_DIR}|g" /etc/nginx/sites-available/watchboard
ln -sf /etc/nginx/sites-available/watchboard /etc/nginx/sites-enabled/watchboard
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

# ── 16. Firewall ──────────────────────────────────────────────────────────────
info "Configuring firewall"
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable
ok "UFW enabled (SSH + HTTP/HTTPS open)"

# ── 17. Scheduler cron ───────────────────────────────────────────────────────
info "Adding Laravel scheduler cron"
CRON_LINE="* * * * * ${APP_USER} php ${APP_DIR}/artisan schedule:run >> /dev/null 2>&1"
if ! crontab -l 2>/dev/null | grep -qF "$APP_DIR/artisan schedule:run"; then
  (crontab -l 2>/dev/null; echo "$CRON_LINE") | crontab -
fi
ok "Cron installed"

echo ""
echo "================================================================"
echo " Setup complete. Remaining manual steps:"
echo "================================================================"
echo ""
echo " 1. Set PostgreSQL password:"
echo "    sudo -u postgres psql -c \"\\password watchboard\""
echo ""
echo " 2. Copy and fill in .env:"
echo "    cp ${APP_DIR}/deploy/.env.production.example ${APP_DIR}/.env"
echo "    nano ${APP_DIR}/.env"
echo "    # Fill in: APP_KEY, DB_PASSWORD, MAIL_PASSWORD, REVERB_* values"
echo ""
echo " 3. Install PHP dependencies & build assets:"
echo "    cd ${APP_DIR}"
echo "    sudo -u ${APP_USER} composer install --no-dev --optimize-autoloader"
echo "    sudo -u ${APP_USER} npm ci && sudo -u ${APP_USER} npm run build"
echo ""
echo " 4. Bootstrap Laravel:"
echo "    cd ${APP_DIR}"
echo "    sudo -u ${APP_USER} php artisan key:generate"
echo "    sudo -u ${APP_USER} php artisan migrate --force"
echo "    sudo -u ${APP_USER} php artisan storage:link"
echo "    sudo -u ${APP_USER} php artisan config:cache"
echo "    sudo -u ${APP_USER} php artisan route:cache"
echo "    sudo -u ${APP_USER} php artisan view:cache"
echo "    sudo -u ${APP_USER} php artisan event:cache"
echo ""
echo " 5. Obtain SSL certificate:"
echo "    certbot --nginx -d ${DOMAIN} -d www.${DOMAIN}"
echo ""
echo " 6. Start Supervisor workers:"
echo "    supervisorctl reread && supervisorctl update"
echo "    supervisorctl start watchboard:*"
echo ""
echo " 7. Add GitHub Secrets for CD (Settings → Secrets → Actions):"
echo "    SSH_HOST         = <server IP or hostname>"
echo "    SSH_USER         = ${APP_USER}"
echo "    SSH_PRIVATE_KEY  = <private key content>"
echo "    SSH_PORT         = 22"
echo "    DEPLOY_PATH      = ${APP_DIR}"
echo ""
echo " 8. Seed demo data (optional):"
echo "    php artisan db:seed --class=DemoSeeder"
echo ""
echo " 9. Push to master to trigger the first automated deploy."
echo ""
