#!/bin/bash

set -e

PROJECT_DIR="/var/www/html/gestion-projet"
DB_FILE="/var/lib/projectflow/database.sqlite"
BACKUP_DIR="/var/backups/gestion-projet"

BRANCH="${1:-main}"

if [ -z "$BRANCH" ]; then
    echo "Usage : $0 <branche>"
    exit 1
fi

echo "=========================================="
echo "   MISE A JOUR GESTION-PROJET"
echo "=========================================="
echo
echo "Branche cible : $BRANCH"
echo

cd "$PROJECT_DIR"

# Toutes les opérations Git sont exécutées par www-data. Répare les droits
# d'un dépôt anciennement manipulé par root avant tout fetch.
chown -R www-data:www-data "$PROJECT_DIR/.git"
find "$PROJECT_DIR/.git" -type d -exec chmod u+rwx,go+rx {} +
find "$PROJECT_DIR/.git" -type f -exec chmod u+rw,go+r {} +

echo "[1/7] Sauvegarde de la base de données..."
mkdir -p "$BACKUP_DIR"

BACKUP_FILE="$BACKUP_DIR/database.sqlite.$(date +%Y%m%d-%H%M%S)"
cp -a "$DB_FILE" "$BACKUP_FILE"

echo "      Sauvegarde : $BACKUP_FILE"
echo

echo "[2/7] Récupération de GitHub..."
# Force the requested remote-tracking branch to refresh, including after
# rewritten history or when the local origin/<branch> ref is stale.
sudo -u www-data git fetch origin "+refs/heads/$BRANCH:refs/remotes/origin/$BRANCH"
echo

echo "[3/7] Vérification de la branche..."
if ! sudo -u www-data git show-ref --verify --quiet "refs/remotes/origin/$BRANCH"; then
    echo "ERREUR : la branche 'origin/$BRANCH' n'existe pas."
    exit 1
fi
echo

echo "[4/7] Passage sur la branche $BRANCH..."
if sudo -u www-data git show-ref --verify --quiet "refs/heads/$BRANCH"; then
    sudo -u www-data git checkout "$BRANCH"
else
    sudo -u www-data git checkout -b "$BRANCH" "origin/$BRANCH"
fi
echo

echo "[5/7] Mise à jour du code..."
sudo -u www-data git reset --hard "origin/$BRANCH"
echo

echo "[6/7] Vérification de la syntaxe PHP..."
find "$PROJECT_DIR" -type f -name "*.php" -print0 \
    | xargs -0 -n1 php -l
echo

echo "[7/7] Vérification et rechargement d'Apache..."
apachectl configtest
systemctl reload apache2
echo

echo "=========================================="
echo "   MISE A JOUR TERMINEE AVEC SUCCES"
echo "=========================================="
echo
echo "Branche installée : $BRANCH"
echo "Commit installé :"
sudo -u www-data git log -1 --oneline
echo
echo "Base sauvegardée :"
echo "$BACKUP_FILE"
echo
