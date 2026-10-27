#!/usr/bin/env bash
set -Eeuo pipefail
[[ $EUID -eq 0 ]] || { echo "Executez avec sudo." >&2; exit 1; }
. /etc/os-release
[[ "$ID" == ubuntu && ( "$VERSION_ID" == "24.04" || "$VERSION_ID" == "26.04" ) ]] || { echo "Ubuntu 24.04 ou 26.04 requis." >&2; exit 1; }
DEB="${1:-}"
[[ -f "$DEB" && "$DEB" == *.deb ]] || { echo "Usage: sudo bash install-ubuntu.sh ./oddworks_VERSION_all.deb" >&2; exit 1; }
DEB="$(realpath "$DEB")"
[[ "$(dpkg-deb -f "$DEB" Package)" == oddworks ]] || { echo "Ce paquet n'est pas OddWorks." >&2; exit 1; }
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y --no-install-recommends ca-certificates nginx php-cli php-fpm php-sqlite3 php-mbstring php-curl php-xml php-zip sqlite3 python3-venv python3-pip
apt-get install -y --no-install-recommends "$DEB"
php -r 'if (!extension_loaded("pdo_sqlite") || !extension_loaded("mbstring")) exit(1);'
install -d -m 755 /opt/oddworks/shared
python3 -m venv /opt/oddworks/shared/cdc-msg-venv
/opt/oddworks/shared/cdc-msg-venv/bin/python3 -m pip install --upgrade pip
/opt/oddworks/shared/cdc-msg-venv/bin/python3 -m pip install -r /usr/share/oddworks/requirements-cdc-msg.txt
echo "Paquet installe. Lancez sudo oddworks-configure pour initialiser l'administrateur."
echo "Aucun service web existant n'a ete reconfigure."
