#!/usr/bin/env bash
# Administrator opt-in for this one experiment. Does not alter the main deployment helper.
set -Eeuo pipefail
[[ $EUID -eq 0 ]] || { echo 'Exécuter avec sudo.' >&2; exit 1; }
ip -4 -o addr show | grep -Eq 'inet 192[.]168[.]6[.]28/'
id ubuntu >/dev/null
id ofa-oddworks >/dev/null
test -L /opt/oneforall/apps/oddworks/current
test -f /var/lib/oneforall/oddworks/database.sqlite
systemctl is-active --quiet oneforall-oddworks-web.service
systemctl is-active --quiet oneforall-nginx.service
test -x /usr/local/sbin/oddworks-deploy
script_dir=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
bash -n "$script_dir/oddworks-deploy-etudes"
sudoers_tmp=$(mktemp)
trap 'rm -f "$sudoers_tmp"' EXIT
printf '%s\n' 'ubuntu ALL=(root) NOPASSWD: /usr/local/sbin/oddworks-deploy-etudes *' > "$sudoers_tmp"
visudo -cf "$sudoers_tmp"
install -o root -g root -m 0755 "$script_dir/oddworks-deploy-etudes" /usr/local/sbin/oddworks-deploy-etudes
install -o root -g root -m 0440 "$sudoers_tmp" /etc/sudoers.d/oddworks-deploy-etudes
echo 'Expérimentation autorisée : experimentation/etudes-fiches. Aucun déploiement effectué.'
