#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

export DEBIAN_FRONTEND=noninteractive

if ! command -v php >/dev/null 2>&1 \
  || ! php -m | grep -qx 'curl' \
  || ! php -m | grep -qx 'pdo_mysql' \
  || ! command -v mysql >/dev/null 2>&1; then
  sudo apt-get update
  sudo apt-get install -y php-cli php-mysql php-curl php-mbstring mariadb-server
fi

if ! sudo mysqladmin ping --silent 2>/dev/null; then
  sudo service mariadb start
fi

for _ in $(seq 1 30); do
  if sudo mysqladmin ping --silent 2>/dev/null; then
    break
  fi
  sleep 1
done
sudo mysqladmin ping --silent

sudo mysql --batch <<'SQL'
CREATE DATABASE IF NOT EXISTS whatsgroup CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'whatsgroup'@'127.0.0.1' IDENTIFIED BY 'change-me';
ALTER USER 'whatsgroup'@'127.0.0.1' IDENTIFIED BY 'change-me';
GRANT ALL PRIVILEGES ON whatsgroup.* TO 'whatsgroup'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

if [ ! -f config.php ]; then
  cp config.sample.php config.php
fi

cnf="$(mktemp)"
trap 'rm -f "$cnf"' EXIT
cat >"$cnf" <<'EOF'
[client]
host=127.0.0.1
user=whatsgroup
password=change-me
database=whatsgroup
EOF

tables="$(mysql --defaults-extra-file="$cnf" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='whatsgroup' AND table_name='admins'")"
if [ "$tables" = "0" ]; then
  mysql --defaults-extra-file="$cnf" < schema.sql
fi
