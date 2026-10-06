#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

if ! sudo mysqladmin ping --silent 2>/dev/null; then
  sudo service mariadb start
fi

ready=0
for _ in $(seq 1 30); do
  if sudo mysqladmin ping --silent 2>/dev/null; then
    ready=1
    break
  fi
  sleep 1
done
if [ "$ready" != "1" ]; then
  echo "MariaDB did not become ready" >&2
  exit 1
fi

if [ ! -f config.php ]; then
  echo "Missing config.php. Run scripts/cloud-agent-install.sh first." >&2
  exit 1
fi

if ss -ltn | grep -q ':8080 '; then
  echo "PHP server already listening on port 8080"
  exit 0
fi

exec php -S 0.0.0.0:8080 router.php
