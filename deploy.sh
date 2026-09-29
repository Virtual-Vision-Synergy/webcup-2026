#!/bin/bash
# Déploiement Webcup sur Hodi : bash ~/webcup-2026/deploy.sh
set -e

PHP=/opt/cpanel/ea-php84/root/usr/bin/php
export PATH=/opt/alt/alt-nodejs24/root/usr/bin:$PATH

cd ~/webcup-2026
echo "==> Récupération du code"
git pull --ff-only origin main

echo "==> Dépendances PHP"
$PHP /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Compilation du front"
npm ci --no-audit --no-fund
npm run build

echo "==> Base de données"
$PHP artisan migrate --force

echo "==> Optimisation"
$PHP artisan storage:link || true
$PHP artisan filament:assets
$PHP artisan optimize

echo "==> Déploiement terminé"
