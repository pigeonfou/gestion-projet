#!/usr/bin/env bash
set -Eeuo pipefail
[[ $EUID -eq 0 ]] || { echo "Run as root" >&2; exit 1; }
SHA="${1:-}"
[[ "$SHA" =~ ^[0-9a-f]{40}$ ]] || { echo "Expected full Git commit SHA" >&2; exit 1; }
ROOT=/opt/oddworks
REPO="$ROOT/repository.git"
RELEASE="$ROOT/releases/$SHA"
install -d -m 755 "$ROOT/releases" "$ROOT/shared"
if [[ ! -d "$REPO" ]]; then git clone --mirror https://github.com/pigeonfou/gestion-projet.git "$REPO"; fi
git --git-dir="$REPO" fetch --prune origin main
git --git-dir="$REPO" merge-base --is-ancestor "$SHA" refs/heads/main || { echo "Commit not on main" >&2; exit 1; }
if [[ ! -d "$RELEASE" ]]; then
  TMP="$(mktemp -d "$ROOT/releases/.stage.XXXXXX")"
  trap 'rm -rf "$TMP"' EXIT
  git --git-dir="$REPO" archive "$SHA" | tar -x -C "$TMP"
  while IFS= read -r -d '' f; do php -l "$f" >/dev/null; done < <(find "$TMP" -name '*.php' -print0)
  mv "$TMP" "$RELEASE"
  trap - EXIT
fi
[[ -S /run/php/oddworks.sock ]] || { echo "OddWorks FPM pool unavailable" >&2; exit 1; }
install -d -o www-data -g www-data -m 750 /var/lib/oddworks /var/lib/oddworks/sessions
if [[ ! -f /var/lib/oddworks/database.sqlite ]]; then
  echo "Initialize SQLite and administrator with the documented installer before first deployment." >&2
  exit 1
fi
runuser -u www-data -- env PROJECTFLOW_DB_PATH=/var/lib/oddworks/database.sqlite php -r 'require $argv[1]; runSchemaMigrations();' "$RELEASE/includes/schema.php"
PREVIOUS="$(readlink "$ROOT/current" || true)"
ln -sfn "$RELEASE" "$ROOT/current.new"
mv -Tf "$ROOT/current.new" "$ROOT/current"
if ! curl -fsS --max-time 10 http://127.0.0.1:18081/login.php >/dev/null; then
  if [[ -n "$PREVIOUS" ]]; then ln -sfn "$PREVIOUS" "$ROOT/current.new"; mv -Tf "$ROOT/current.new" "$ROOT/current"; fi
  echo "HTTP verification failed; previous code restored if present" >&2
  exit 1
fi
echo "OddWorks deployed: $SHA"
