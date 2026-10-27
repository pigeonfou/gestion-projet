#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
VERSION="${1:-1.0.0}"
[[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo "Expected version X.Y.Z" >&2; exit 1; }
command -v dpkg-deb >/dev/null || { echo "dpkg-deb is required" >&2; exit 1; }
OUT="${2:-$ROOT/dist}"
mkdir -p "$OUT"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT
PKG="$STAGE/package"
mkdir -p "$PKG/DEBIAN" "$PKG/usr/share/oddworks" "$PKG/usr/lib/oddworks" "$PKG/usr/share/doc/oddworks"
tar -C "$ROOT" --exclude=.git --exclude=dist --exclude=packaging --exclude=.github --exclude=tests -cf - . | tar -C "$PKG/usr/share/oddworks" -xf -
install -m 755 "$ROOT/packaging/debian/oddworks-configure" "$PKG/usr/lib/oddworks/oddworks-configure"
install -m 755 "$ROOT/packaging/debian/oddworks-web-setup" "$PKG/usr/lib/oddworks/oddworks-web-setup"
install -m 644 "$ROOT/packaging/debian/README.md" "$PKG/usr/share/doc/oddworks/README.md"
cat > "$PKG/DEBIAN/control" <<EOF
Package: oddworks
Version: $VERSION
Section: web
Priority: optional
Architecture: all
Maintainer: OddWorks <noreply@localhost>
Depends: php-cli, php-fpm, php-sqlite3, php-mbstring, php-curl, php-xml, php-zip, sqlite3, nginx, python3-venv, python3-pip, openssl, iproute2
Description: OddWorks standalone project management web application
 No GitHub or OneForAll dependency at runtime.
EOF
cat > "$PKG/DEBIAN/postinst" <<'EOF'
#!/bin/sh
set -eu
[ "$1" = configure ] || exit 0
install -d -o www-data -g www-data -m 750 /var/lib/oddworks /var/lib/oddworks/sessions
ODDWORKS_NONINTERACTIVE=1 /usr/lib/oddworks/oddworks-configure
if [ ! -x /opt/oddworks/shared/cdc-msg-venv/bin/python3 ]; then
    install -d -m 755 /opt/oddworks/shared
    python3 -m venv /opt/oddworks/shared/cdc-msg-venv
fi
/opt/oddworks/shared/cdc-msg-venv/bin/python3 -m pip install --disable-pip-version-check --upgrade -r /usr/share/oddworks/requirements-cdc-msg.txt
/usr/lib/oddworks/oddworks-web-setup
echo "OddWorks ready at http://127.0.0.1:18081/login.php"
echo "Admin user: admin"
echo "Read initial password with: sudo cat /var/lib/oddworks/initial-admin-password"
EOF
chmod 755 "$PKG/DEBIAN/postinst"
mkdir -p "$PKG/usr/sbin"
ln -s /usr/lib/oddworks/oddworks-configure "$PKG/usr/sbin/oddworks-configure"
ln -s /usr/lib/oddworks/oddworks-web-setup "$PKG/usr/sbin/oddworks-web-setup"
dpkg-deb --build --root-owner-group "$PKG" "$OUT/oddworks_${VERSION}_all.deb"
echo "Built $OUT/oddworks_${VERSION}_all.deb"
