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

echo
echo "=========================================="
echo "   ROLLBACK GESTION-PROJET"
echo "=========================================="
echo
echo "Branche cible : $BRANCH"
echo

cd "$PROJECT_DIR"

echo "[1/8] Vérification de l'état Git..."
echo

CURRENT_COMMIT=$(sudo -u www-data git rev-parse HEAD)
CURRENT_BRANCH=$(sudo -u www-data git branch --show-current)

echo "Branche actuelle : $CURRENT_BRANCH"
echo "Commit actuel    : $CURRENT_COMMIT"
echo
echo "Dernier commit :"
sudo -u www-data git log -1 --oneline
echo

echo "[2/8] Récupération de la branche..."
sudo -u www-data git fetch origin "refs/heads/$BRANCH:refs/remotes/origin/$BRANCH"

if ! sudo -u www-data git show-ref --verify --quiet "refs/remotes/origin/$BRANCH"; then
    echo "ERREUR : la branche 'origin/$BRANCH' n'existe pas."
    exit 1
fi

if [ "$CURRENT_BRANCH" != "$BRANCH" ]; then
    if sudo -u www-data git status --porcelain | grep -q .; then
        echo "ERREUR : des modifications locales existent."
        sudo -u www-data git status --short
        echo "Rollback annulé pour éviter d'écraser des modifications."
        exit 1
    fi

    if sudo -u www-data git show-ref --verify --quiet "refs/heads/$BRANCH"; then
        sudo -u www-data git checkout "$BRANCH"
    else
        sudo -u www-data git checkout -b "$BRANCH" "origin/$BRANCH"
    fi

    CURRENT_COMMIT=$(sudo -u www-data git rev-parse HEAD)
fi

echo

echo "[3/8] Vérification des modifications locales..."
if [ -n "$(sudo -u www-data git status --porcelain)" ]; then
    echo "ERREUR : des modifications locales existent."
    sudo -u www-data git status --short
    echo "Rollback annulé pour éviter d'écraser des modifications."
    exit 1
fi

echo "Aucune modification locale."
echo

echo "[4/8] Choisissez la version à restaurer"
echo
echo "Les 10 derniers commits de $BRANCH sont :"
echo

mapfile -t COMMITS < <(
    sudo -u www-data git log -10 \
        --pretty=format:"%H|%h|%ad|%s" \
        --date=format:"%Y-%m-%d %H:%M"
)

i=1
for COMMIT in "${COMMITS[@]}"; do
    IFS='|' read -r FULL HASH DATE MESSAGE <<< "$COMMIT"

    if [ "$FULL" = "$CURRENT_COMMIT" ]; then
        echo "  $i) $HASH  $DATE  $MESSAGE   [ACTUEL]"
    else
        echo "  $i) $HASH  $DATE  $MESSAGE"
    fi

    ((i++))
done

echo
echo "  0) Annuler"
echo

read -rp "Votre choix : " CHOICE

if ! [[ "$CHOICE" =~ ^[0-9]+$ ]]; then
    echo "Choix invalide."
    exit 1
fi

if [ "$CHOICE" -eq 0 ]; then
    echo "Rollback annulé."
    exit 0
fi

if [ "$CHOICE" -lt 1 ] || [ "$CHOICE" -gt "${#COMMITS[@]}" ]; then
    echo "Choix invalide."
    exit 1
fi

SELECTED="${COMMITS[$((CHOICE-1))]}"
IFS='|' read -r TARGET_COMMIT TARGET_SHORT TARGET_DATE TARGET_MESSAGE <<< "$SELECTED"

echo
echo "=========================================="
echo "   VERSION SELECTIONNEE"
echo "=========================================="
echo
echo "Version actuelle :"
sudo -u www-data git log -1 --oneline
echo
echo "Retour vers :"
echo "$TARGET_SHORT  $TARGET_DATE  $TARGET_MESSAGE"
echo
echo "Commit complet :"
echo "$TARGET_COMMIT"
echo
echo "La base de données sera sauvegardée avant l'opération."
echo "Les données SQLite ne seront PAS restaurées."
echo

if [ "$TARGET_COMMIT" = "$CURRENT_COMMIT" ]; then
    echo "La version sélectionnée est déjà installée."
    exit 0
fi

read -rp "Tapez ROLLBACK pour confirmer : " CONFIRMATION

if [ "$CONFIRMATION" != "ROLLBACK" ]; then
    echo
    echo "Confirmation incorrecte."
    echo "Rollback annulé."
    exit 1
fi

echo
echo "[5/8] Sauvegarde de la base de données..."
mkdir -p "$BACKUP_DIR"

BACKUP_FILE="$BACKUP_DIR/database.sqlite.rollback-$(date +%Y%m%d-%H%M%S)"
cp -a "$DB_FILE" "$BACKUP_FILE"

echo "      Base sauvegardée : $BACKUP_FILE"
echo

echo "[6/8] Création d'un point de retour Git..."
SNAPSHOT_BRANCH="rollback-backup-$(date +%Y%m%d-%H%M%S)"
sudo -u www-data git branch "$SNAPSHOT_BRANCH" "$CURRENT_COMMIT"

echo "      Branche de sauvegarde : $SNAPSHOT_BRANCH"
echo

echo "[7/8] Retour vers la version sélectionnée..."
sudo -u www-data git reset --hard "$TARGET_COMMIT"

echo
echo "Rollback Git effectué."
echo

echo "Vérification de la syntaxe PHP..."
find "$PROJECT_DIR" -type f -name "*.php" -print0 \
    | xargs -0 -n1 php -l

echo
echo "Syntaxe PHP : OK"
echo

echo "[8/8] Vérification et rechargement d'Apache..."
apachectl configtest
systemctl reload apache2

echo
echo "=========================================="
echo "   ROLLBACK TERMINE AVEC SUCCES"
echo "=========================================="
echo
echo "Branche : $BRANCH"
echo "Version installée :"
sudo -u www-data git log -1 --oneline
echo
echo "Point de retour Git : $SNAPSHOT_BRANCH"
echo "Sauvegarde de la base : $BACKUP_FILE"
echo
