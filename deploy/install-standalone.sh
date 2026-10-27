#!/usr/bin/env bash
set -Eeuo pipefail
[[ $EUID -eq 0 ]] || { echo "Run as root" >&2; exit 1; }
[[ -f /etc/os-release ]] && . /etc/os-release
[[ "${ID:-}" == ubuntu ]] || { echo "Ubuntu required" >&2; exit 1; }
command -v nginx >/dev/null && command -v php-fpm8.5 >/dev/null && command -v php >/dev/null || { echo "Install nginx, php8.5-fpm and php-cli first" >&2; exit 1; }
[[ -d /etc/php/8.5/fpm/pool.d ]] || { echo "Missing PHP 8.5 FPM pool directory" >&2; exit 1; }
[[ ! -e /etc/nginx/sites-enabled/oddworks.conf ]] || { echo "OddWorks nginx config already exists; inspect manually" >&2; exit 1; }
[[ ! -e /etc/php/8.5/fpm/pool.d/oddworks.conf ]] || { echo "OddWorks FPM pool already exists; inspect manually" >&2; exit 1; }
if ss -lnt | grep -Fq '127.0.0.1:18081'; then echo 'Port 18081 in use' >&2; exit 1; fi
install -d -m 755 /opt/oddworks/releases /opt/oddworks/shared
install -d -o www-data -g www-data -m 750 /var/lib/oddworks /var/lib/oddworks/sessions
install -m 644 "$(dirname "$0")/oddworks-fpm.conf" /etc/php/8.5/fpm/pool.d/oddworks.conf
install -m 644 "$(dirname "$0")/oddworks-nginx.conf" /etc/nginx/sites-available/oddworks.conf
ln -s /etc/nginx/sites-available/oddworks.conf /etc/nginx/sites-enabled/oddworks.conf
if ! php-fpm8.5 -t || ! nginx -t; then
  rm -f /etc/nginx/sites-enabled/oddworks.conf /etc/nginx/sites-available/oddworks.conf /etc/php/8.5/fpm/pool.d/oddworks.conf
  echo 'Invalid configuration; files removed' >&2
  exit 1
fi
systemctl reload php8.5-fpm
systemctl reload nginx
echo "Standalone local web endpoint prepared on 127.0.0.1:18081. Deploy application separately."
