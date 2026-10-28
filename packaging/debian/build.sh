#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
bash "$ROOT/packaging/debian/check-production-source.sh"
python3 "$ROOT/packaging/debian/check-dev-parity.py"
VERSION="${1:?Version réservée par le workflow obligatoire}"
[[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo "Expected version X.Y.Z" >&2; exit 1; }
command -v dpkg-deb >/dev/null || { echo "dpkg-deb is required" >&2; exit 1; }
OUT="${2:-$ROOT/dist}"
mkdir -p "$OUT"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT
PKG="$STAGE/package"
mkdir -p "$PKG/DEBIAN" "$PKG/usr/share/oddworks" "$PKG/usr/lib/oddworks" "$PKG/usr/share/doc/oddworks"
git -C "$ROOT" archive "$GITHUB_SHA" | tar -C "$PKG/usr/share/oddworks" --exclude=packaging --exclude=.github --exclude=tests -xf -
install -m 755 "$ROOT/packaging/debian/oddworks-configure" "$PKG/usr/lib/oddworks/oddworks-configure"
install -m 755 "$ROOT/packaging/debian/oddworks-web-setup" "$PKG/usr/lib/oddworks/oddworks-web-setup"
install -m 644 "$ROOT/packaging/debian/README.md" "$PKG/usr/share/doc/oddworks/README.md"
install -m 755 "$ROOT/packaging/debian/installer-support" "$PKG/DEBIAN/preinst"
install -m 755 "$ROOT/packaging/debian/installer-support" "$PKG/DEBIAN/postrm"
install -m 755 "$ROOT/packaging/debian/installer-support" "$PKG/usr/lib/oddworks/installer-support"
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
/usr/lib/oddworks/installer-support restore-pools
/usr/lib/oddworks/oddworks-web-setup
/usr/lib/oddworks/installer-support finish
echo "OddWorks ready at http://127.0.0.1/login.php"

EOF
chmod 755 "$PKG/DEBIAN/postinst"
mkdir -p "$PKG/usr/sbin"
ln -s /usr/lib/oddworks/oddworks-configure "$PKG/usr/sbin/oddworks-configure"
ln -s /usr/lib/oddworks/oddworks-web-setup "$PKG/usr/sbin/oddworks-web-setup"
printf "%s\n" "Source-Commit: $(git -C "$ROOT" rev-parse HEAD)" > "$PKG/usr/share/doc/oddworks/source-commit"
dpkg-deb --build --root-owner-group "$PKG" "$OUT/oddworks_${VERSION}_all.deb"
echo "Built $OUT/oddworks_${VERSION}_all.deb"
