#!/usr/bin/env bash
set -Eeuo pipefail
[[ $EUID -eq 0 ]] || { echo "Executez avec sudo." >&2; exit 1; }
. /etc/os-release
[[ "$ID" == ubuntu && ( "$VERSION_ID" == "24.04" || "$VERSION_ID" == "26.04" ) ]] || { echo "Ubuntu 24.04 ou 26.04 requis." >&2; exit 1; }
DEB="${1:-}"
[[ -f "$DEB" && "$DEB" == *.deb ]] || { echo "Usage: sudo bash install-ubuntu.sh ./oddworks_VERSION_all.deb" >&2; exit 1; }
DEB="$(realpath "$DEB")"
[[ "$(dpkg-deb -f "$DEB" Package)" == oddworks ]] || { echo "Paquet incorrect." >&2; exit 1; }
apt-get update
apt-get install -y "$DEB"
echo "OddWorks installed; see http://127.0.0.1:18081/login.php"
