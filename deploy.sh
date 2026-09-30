#!/bin/bash
# Déploiement Webcup sur Hodi : bash ~/webcup-2026/deploy.sh
# Étapes : code -> dépendances -> front -> sauvegarde de la base -> migrations -> caches -> contrôle de santé.
# Journal : ~/deploy.log · Sauvegardes : ~/backups (10 dernières conservées)
set -eo pipefail

PHP=/opt/cpanel/ea-php84/root/usr/bin/php
export PATH=/opt/alt/alt-nodejs24/root/usr/bin:$PATH
APP_DIR=~/webcup-2026
BACKUP_DIR=~/backups
LOG=~/deploy.log

exec > >(tee -a "$LOG") 2>&1
echo
echo "================ Déploiement du $(date '+%Y-%m-%d %H:%M:%S') ================"
trap 'echo "!!! ÉCHEC à la ligne $LINENO. Le site tourne toujours sur la version précédente du code compilé, lire l erreur ci-dessus."' ERR

cd "$APP_DIR"

env_value() {
    grep -E "^$1=" .env | tail -n 1 | cut -d '=' -f 2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"
}

echo "==> Récupération du code"
git pull --ff-only origin main
echo "    Version : $(git log -1 --format='%h %s')"

echo "==> Dépendances PHP"
$PHP /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Compilation du front"
npm ci --no-audit --no-fund
npm run build

echo "==> Sauvegarde de la base"
if [ "$(env_value DB_CONNECTION)" = "mysql" ] || [ "$(env_value DB_CONNECTION)" = "mariadb" ]; then
    mkdir -p "$BACKUP_DIR"
    CNF=$(mktemp)
    chmod 600 "$CNF"
    printf '[client]\nuser=%s\npassword=%s\nhost=%s\n' "$(env_value DB_USERNAME)" "$(env_value DB_PASSWORD)" "$(env_value DB_HOST)" > "$CNF"
    FILE="$BACKUP_DIR/$(env_value DB_DATABASE)-$(date +%Y%m%d-%H%M%S).sql.gz"
    mysqldump --defaults-extra-file="$CNF" --single-transaction --quick "$(env_value DB_DATABASE)" | gzip > "$FILE"
    rm -f "$CNF"
    echo "    $FILE ($(du -h "$FILE" | cut -f1))"
    ls -1t "$BACKUP_DIR"/*.sql.gz 2>/dev/null | tail -n +11 | xargs -r rm -f
else
    echo "    (base non MySQL/MariaDB : sauvegarde ignorée)"
fi

echo "==> Base de données"
$PHP artisan migrate --force

echo "==> Optimisation"
$PHP artisan storage:link || true
$PHP artisan filament:assets
$PHP artisan optimize

echo "==> Contrôle de santé"
URL=$(env_value APP_URL)
CODE=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$URL/up" || true)
if [ "$CODE" = "200" ]; then
    echo "    $URL/up -> 200 OK"
else
    echo "!!! $URL/up a répondu '$CODE'. Lire : tail -n 60 $APP_DIR/storage/logs/laravel.log"
    exit 1
fi

echo "==> Déploiement terminé ($(date '+%H:%M:%S')). Prévenir Njaraniaina pour la recette."
echo
echo "Pour restaurer la dernière sauvegarde en cas de catastrophe :"
echo "  gunzip -c \$(ls -1t $BACKUP_DIR/*.sql.gz | head -1) | mysql -u <utilisateur> -p <base>"
