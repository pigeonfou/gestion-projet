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
tar -C "$ROOT" --exclude=.git --exclude=dist --exclude=packaging --exclude=.github --exclude=tests --exclude=deploy -cf - . | tar -C "$PKG/usr/share/oddworks" -xf -
install -m 755 "$ROOT/packaging/debian/oddworks-configure" "$PKG/usr/lib/oddworks/oddworks-configure"
install -m 644 "$ROOT/packaging/debian/README.md" "$PKG/usr/share/doc/oddworks/README.md"
cat > "$PKG/DEBIAN/control" <<EOF
Package: oddworks
Version: $VERSION
Section: web
Priority: optional
Architecture: all
Maintainer: OddWorks <noreply@localhost>
Depends: php-cli, php-fpm, php-sqlite3, php-mbstring, php-curl, php-xml, php-zip, sqlite3, nginx
Description: OddWorks standalone project management web application
 No GitHub or OneForAll dependency at runtime.
EOF
cat > "$PKG/DEBIAN/postinst" <<'EOF'
#!/bin/sh
set -e
if [ "$1" = configure ]; then
    install -d -o www-data -g www-data -m 750 /var/lib/oddworks /var/lib/oddworks/sessions
    echo "OddWorks installed. Run sudo oddworks-configure after reviewing web configuration."
fi
EOF
chmod 755 "$PKG/DEBIAN/postinst"
mkdir -p "$PKG/usr/sbin"
ln -s /usr/lib/oddworks/oddworks-configure "$PKG/usr/sbin/oddworks-configure"
dpkg-deb --build --root-owner-group "$PKG" "$OUT/oddworks_${VERSION}_all.deb"
echo "Built $OUT/oddworks_${VERSION}_all.deb"
