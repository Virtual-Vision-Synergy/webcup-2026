#!/bin/bash
# Sauvegarde de la base de production (MariaDB) dans ~/backups.
#   Automatique : cron cPanel toutes les 30 min
#     */30 * * * * bash $HOME/app/scripts/sauvegarde-base.sh >> $HOME/backups/cron.log 2>&1
#   À la main, avant de merger une migration risquée :
#     bash ~/app/scripts/sauvegarde-base.sh avant-pr12
# Restaurer : gunzip -c ~/backups/<fichier>.sql.gz | mysql -u <utilisateur> -p <base>
set -euo pipefail

APP_DIR=${APP_DIR:-$HOME/app}          # ~/app -> version en ligne (Hodifly)
BACKUP_DIR=${BACKUP_DIR:-$HOME/backups}
GARDER=${GARDER:-48}                   # 48 sauvegardes automatiques = 24 h à raison d'une toutes les 30 min

cd "$APP_DIR"

v() {
    grep -E "^$1=" .env | tail -n 1 | cut -d '=' -f 2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"
}

case "$(v DB_CONNECTION)" in
    mysql|mariadb) ;;
    *) echo "Base non MySQL/MariaDB : rien à sauvegarder."; exit 0 ;;
esac

DB=$(v DB_DATABASE)
HOST=$(v DB_HOST)
mkdir -p "$BACKUP_DIR"

# Identifiants dans un fichier temporaire lisible par nous seuls (jamais en argument de commande).
CNF=$(mktemp)
trap 'rm -f "$CNF"' EXIT
chmod 600 "$CNF"
printf '[client]\nuser="%s"\npassword="%s"\nhost="%s"\n' "$(v DB_USERNAME)" "$(v DB_PASSWORD)" "${HOST:-localhost}" > "$CNF"

ETIQUETTE=${1:-}
FICHIER="$BACKUP_DIR/$DB-$(date +%Y%m%d-%H%M%S)${ETIQUETTE:+-$ETIQUETTE}.sql.gz"
mysqldump --defaults-extra-file="$CNF" --single-transaction --quick "$DB" | gzip > "$FICHIER"
echo "$(date '+%Y-%m-%d %H:%M:%S') $FICHIER ($(du -h "$FICHIER" | cut -f1))"

# Rotation des sauvegardes automatiques uniquement (sans étiquette) ; les sauvegardes étiquetées sont gardées.
ls -1t "$BACKUP_DIR/$DB"-????????-??????.sql.gz 2>/dev/null | tail -n +$((GARDER + 1)) | xargs -r rm -f
