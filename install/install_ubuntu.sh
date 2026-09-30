#!/usr/bin/env bash
set -Eeuo pipefail
IFS=$'\n\t'
REPO_URL="https://github.com/pigeonfou/gestion-projet.git"
BRANCH="main"
APP_NAME="gestion-projet"
APP_DIR="/var/www/html/$APP_NAME"
DB_DIR="/var/lib/projectflow"
DB_PATH="$DB_DIR/database.sqlite"
APACHE_CONF="/etc/apache2/conf-available/projectflow.conf"
LOG_FILE="/var/log/projectflow-install.log"
GREEN="\033[0;32m"; YELLOW="\033[1;33m"; RED="\033[0;31m"; BLUE="\033[0;34m"; NC="\033[0m"
STEP=0; TOTAL=9
mkdir -p "$(dirname "$LOG_FILE")"; touch "$LOG_FILE"; chmod 600 "$LOG_FILE"
exec > >(tee -a "$LOG_FILE") 2>&1
trap 'echo -e "$RED ERREUR à l’étape $STEP/$TOTAL. Consultez $LOG_FILE$NC"' ERR
info(){ echo -e "$BLUEℹ$NC $*"; }
ok(){ echo -e "$GREEN✓$NC $*"; }
warn(){ echo -e "$YELLOW⚠$NC $*"; }
die(){ echo -e "$RED✗$NC $*"; exit 1; }
step(){ STEP=$((STEP+1)); echo; echo "============================================================"; echo "ÉTAPE $STEP/$TOTAL — $*"; echo "============================================================"; }
pause(){ echo; read -r -p "Appuyez sur Entrée pour continuer..." _; }
require_root(){ [[ $EUID -eq 0 ]] || die "Lancez ce script avec sudo ou en root : sudo bash $0"; }
check_os(){
  [[ -f /etc/os-release ]] || die "Impossible d'identifier le système."
  source /etc/os-release
  [[ "$ID" == "ubuntu" ]] || die "Ce script est prévu pour Ubuntu. Système : $PRETTY_NAME"
  case "$VERSION_ID" in
    22.04|24.04) ok "Ubuntu $VERSION_ID détecté." ;;
    *) warn "Ubuntu $VERSION_ID n'est pas une version cible 22.04/24.04."
       read -r -p "Continuer malgré tout ? [o/N] " answer
       [[ "$answer" =~ ^[oOyY]$ ]] || exit 0 ;;
  esac
}
install_packages(){
  export DEBIAN_FRONTEND=noninteractive
  info "Mise à jour de l'index APT..."
  apt-get update
  info "Mise à niveau des paquets système..."
  apt-get upgrade -y
  info "Installation d'Apache, PHP, SQLite, Git et outils..."
  apt-get install -y apache2 git curl ca-certificates php php-cli php-common php-sqlite3 php-mbstring libapache2-mod-php sqlite3
  ok "Paquets installés."
  php -v | head -n 1; apache2 -v | head -n 1; git --version
}
deploy_application(){
  if [[ -d "$APP_DIR/.git" ]]; then
    warn "Dépôt existant détecté dans $APP_DIR : mise à jour vers origin/$BRANCH."
    git -C "$APP_DIR" fetch origin
    git -C "$APP_DIR" checkout "$BRANCH"
    git -C "$APP_DIR" reset --hard "origin/$BRANCH"
  elif [[ -e "$APP_DIR" ]]; then
    die "$APP_DIR existe mais n'est pas un dépôt Git."
  else
    mkdir -p /var/www/html
    git clone --branch "$BRANCH" --single-branch "$REPO_URL" "$APP_DIR"
  fi
  chown -R www-data:www-data "$APP_DIR"
  find "$APP_DIR" -type d -exec chmod 755 {} +
  find "$APP_DIR" -type f -exec chmod 644 {} +
  [[ -f "$APP_DIR/install/install_ubuntu.sh" ]] && chmod 755 "$APP_DIR/install/install_ubuntu.sh" || true
  # Le dépôt est détenu par www-data pour l’exécution Apache. Les commandes Git
  # lancées par root doivent donc déclarer explicitement ce chemin comme sûr.
  git config --global --add safe.directory "$APP_DIR" 2>/dev/null || true
  ok "Projet déployé depuis $BRANCH."
  info "Commit : $(git -c safe.directory="$APP_DIR" -C "$APP_DIR" rev-parse --short HEAD)"
}
configure_database(){
  install -d -o www-data -g www-data -m 0750 "$DB_DIR"
  if [[ -e "$DB_PATH" ]]; then
    warn "La base $DB_PATH existe déjà : aucune réinitialisation."
    return
  fi
  echo; echo "Création du compte administrateur initial."
  echo "Laissez vide pour générer automatiquement un mot de passe."
  read -r -s -p "Mot de passe admin : " ADMIN_PASSWORD; echo
  if [[ -z "$ADMIN_PASSWORD" ]]; then
    info "Mot de passe aléatoire généré par le programme d'initialisation."
    runuser -u www-data -- env PROJECTFLOW_DB_PATH="$DB_PATH" php "$APP_DIR/install/init_database.php"
  else
    [[ ${#ADMIN_PASSWORD} -ge 12 ]] || die "Le mot de passe doit comporter au moins 12 caractères."
    runuser -u www-data -- env PROJECTFLOW_DB_PATH="$DB_PATH" PROJECTFLOW_ADMIN_PASSWORD="$ADMIN_PASSWORD" php "$APP_DIR/install/init_database.php"
  fi
  unset ADMIN_PASSWORD
  chmod 660 "$DB_PATH"; chown www-data:www-data "$DB_PATH"
  ok "Base SQLite initialisée."
}
run_schema_migrations(){
  info "Exécution des migrations applicatives..."
  runuser -u www-data -- env PROJECTFLOW_DB_PATH="$DB_PATH" php -r 'require $argv[1]; runSchemaMigrations(); echo "Migrations OK\n";' "$APP_DIR/includes/schema.php"
  runuser -u www-data -- env PROJECTFLOW_DB_PATH="$DB_PATH" php -r 'require $argv[1]; seedSettingsIfEmpty(); echo "Paramètres OK\n";' "$APP_DIR/includes/settings_helper.php"
  ok "Schéma et paramètres à jour."
}
configure_apache(){
  a2enmod headers >/dev/null; a2enmod env >/dev/null
  a2enmod "php$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')" >/dev/null 2>&1 || true
  cat > "$APACHE_CONF" <<EOF
# ProjectFlow
<Directory "$APP_DIR">
    Options -Indexes
    AllowOverride All
    Require all granted
</Directory>
<Directory "$APP_DIR/install">
    Require all denied
</Directory>
EOF
  a2enconf projectflow >/dev/null
  if [[ ! -f /etc/apache2/conf-available/servername.conf ]]; then
    printf 'ServerName localhost\n' > /etc/apache2/conf-available/servername.conf
    a2enconf servername >/dev/null
  fi
  apache2ctl configtest
  systemctl enable apache2
  systemctl restart apache2
  ok "Apache configuré et démarré."
}
verify_php(){
  php -m | grep -qi '^PDO$' || die "Extension PDO absente."
  php -m | grep -qi '^pdo_sqlite$' || die "Extension pdo_sqlite absente."
  php -m | grep -qi '^mbstring$' || die "Extension mbstring absente."
  local major minor
  major=$(php -r 'echo PHP_MAJOR_VERSION;'); minor=$(php -r 'echo PHP_MINOR_VERSION;')
  (( major > 8 || (major == 8 && minor >= 1) )) || die "PHP >= 8.1 requis, version $major.$minor."
  ok "PHP $major.$minor et extensions requises disponibles."
}
verify_database(){
  [[ -f "$DB_PATH" ]] || die "Base absente : $DB_PATH"
  [[ -r "$DB_PATH" && -w "$DB_PATH" ]] || die "www-data ne peut pas lire/écrire la base."
  local count
  count=$(runuser -u www-data -- sqlite3 "$DB_PATH" 'SELECT COUNT(*) FROM utilisateurs;')
  [[ "$count" -ge 1 ]] || die "Aucun utilisateur dans la base."
  ok "SQLite opérationnel. Utilisateurs : $count."
}
verify_web(){
  local code
  code=$(curl -sS -o /tmp/projectflow-http.txt -w '%{http_code}' "http://127.0.0.1/$APP_NAME/" || true)
  case "$code" in
    200|302|303) ok "HTTP local opérationnel (code $code)." ;;
    *) warn "Réponse HTTP locale : ${code:-aucune}."
       [[ -s /tmp/projectflow-http.txt ]] && head -n 10 /tmp/projectflow-http.txt
       return 1 ;;
  esac
}
show_summary(){
  local ip
  ip=$(hostname -I 2>/dev/null | awk '{print $1}')
  echo; echo "============================================================"
  echo -e "$GREEN INSTALLATION TERMINÉE $NC"
  echo "============================================================"
  echo "Application : http://${ip:-IP_DU_SERVEUR}/$APP_NAME/"
  echo "Répertoire  : $APP_DIR"; echo "Base SQLite : $DB_PATH"; echo "Journal     : $LOG_FILE"
  echo; echo "Compte initial : admin"
  echo "Le mot de passe n'est volontairement pas affiché."
  echo; echo "Apache : $(systemctl is-active apache2)"
  echo "PHP    : $(php -r 'echo PHP_VERSION;')"
  echo "Commit : $(git -c safe.directory="$APP_DIR" -C "$APP_DIR" rev-parse --short HEAD)"
  echo; echo "Pare-feu recommandé :"
  echo "  ufw allow OpenSSH"; echo "  ufw allow 'Apache Full'"; echo "  ufw enable"
}
full_install(){
  require_root; check_os
  step "Installation des prérequis"; install_packages
  step "Déploiement du code"; deploy_application
  step "Vérification de PHP"; verify_php
  step "Initialisation de SQLite"; configure_database
  step "Migrations du schéma"; run_schema_migrations
  step "Configuration Apache"; configure_apache
  step "Vérification de la base"; verify_database
  step "Test HTTP local"; verify_web || warn "Test HTTP échoué : consultez $LOG_FILE."
  step "Résumé"; show_summary
}
verify_installation(){
  require_root
  echo; echo "Vérification de ProjectFlow"; echo "=========================="
  [[ -d "$APP_DIR" ]] && ok "Répertoire applicatif présent." || warn "Répertoire absent."
  systemctl is-active --quiet apache2 && ok "Apache actif." || warn "Apache inactif."
  php -m | grep -qi '^pdo_sqlite$' && ok "pdo_sqlite disponible." || warn "pdo_sqlite absente."
  [[ -f "$DB_PATH" ]] && ok "Base SQLite présente." || warn "Base SQLite absente."
  if [[ -f "$APP_DIR/.git/HEAD" ]]; then
    ok "Commit : $(git -C "$APP_DIR" rev-parse --short HEAD)"
    ok "Branche : $(git -C "$APP_DIR" branch --show-current)"
  fi
  verify_web || true
  info "Journal : $LOG_FILE"
}
menu(){
  require_root
  while true; do
    clear 2>/dev/null || true
    echo "============================================================"
    echo "             PROJECTFLOW — INSTALLATEUR UBUNTU"
    echo "============================================================"
    echo; echo "  1) Installer / déployer ProjectFlow (main)"
    echo "  2) Vérifier une installation existante"
    echo "  3) Afficher le journal d'installation"
    echo "  4) Quitter"; echo
    read -r -p "Votre choix [1-4] : " choice
    case "$choice" in
      1) full_install; pause ;;
      2) verify_installation; pause ;;
      3) less "$LOG_FILE" 2>/dev/null || cat "$LOG_FILE" ;;
      4) exit 0 ;;
      *) warn "Choix invalide."; sleep 1 ;;
    esac
  done
}
case "${1:-}" in
  --install) full_install ;;
  --verify) verify_installation ;;
  --help|-h)
    echo "Usage : $0 [--install|--verify]"
    echo "  sans option : menu interactif"
    echo "  --install   : installation complète sans menu"
    echo "  --verify    : vérification uniquement"
    ;;
  *) menu ;;
esac
