#!/usr/bin/env bash
# One-time administrator bootstrap; leaves the active release and services intact.
set -Eeuo pipefail
[[ $EUID -eq 0 ]] || { echo 'Exécuter avec sudo.' >&2; exit 1; }
ip -4 -o addr show | grep -Eq 'inet 192[.]168[.]6[.]28/'
id ubuntu >/dev/null
id ofa-oddworks >/dev/null
test -L /opt/oneforall/apps/oddworks/current
test -f /var/lib/oneforall/oddworks/database.sqlite
systemctl is-active --quiet oneforall-oddworks-web.service
script_dir=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
bash -n "$script_dir/oddworks-deploy"
apt-get -o DPkg::Lock::Timeout=300 update
apt-get -o DPkg::Lock::Timeout=300 install -y git curl sqlite3 php-cli php-sqlite3 sudo
php -r 'exit(extension_loaded("pdo_sqlite") ? 0 : 1);'
install -d -o root -g root -m 0700 /var/lib/oddworks-deploy /var/backups/oddworks
install -o root -g root -m 0755 "$script_dir/oddworks-deploy" /usr/local/sbin/oddworks-deploy
sudoers_tmp=$(mktemp)
trap 'rm -f "$sudoers_tmp"' EXIT
printf '%s\n' 'ubuntu ALL=(root) NOPASSWD: /usr/local/sbin/oddworks-deploy *' > "$sudoers_tmp"
visudo -cf "$sudoers_tmp"
install -o root -g root -m 0440 "$sudoers_tmp" /etc/sudoers.d/oddworks-deploy
echo 'Déploiement OddWorks installé. Aucun code actif ni donnée applicative modifiés.'
