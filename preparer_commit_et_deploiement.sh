#!/usr/bin/env bash
# Version TEST LOCAL
# ./preparer_commit_et_deploiement.sh "Message de commit"
#
# Fonctionnement :
# 1) travaille depuis /home/sylvere/Git_depot_local/sr_licences
# 2) fait git add -A
# 3) fait le commit si nécessaire
# 4) prépare un dossier de déploiement dans :
#    C:\Users\soulr\Downloads\sr_licences_deploiement
# 5) propage ce même dossier vers le site local :
#    /var/www/sr_licences_dev
#
# Important :
# - config/, sql/, var/ et .htaccess restent exclus
# - le site local conserve donc sa configuration propre
# - la propagation locale passe bien par le dossier préparé

set -euo pipefail

DOSSIER_DEPOT="/home/sylvere/Git_depot_local/sr_licences"
DOSSIER_DEPLOIEMENT="/mnt/c/Users/soulr/Downloads/sr_licences_deploiement"
DOSSIER_DEV_LOCAL="/var/www/sr_licences_dev"

if [ $# -lt 1 ]; then
  echo "Usage : $0 \"Message de commit\""
  exit 1
fi

MESSAGE_COMMIT="$1"

if [ ! -d "$DOSSIER_DEPOT" ]; then
  echo "Erreur : dépôt introuvable : $DOSSIER_DEPOT"
  exit 1
fi

if [ -z "$DOSSIER_DEPLOIEMENT" ] || [ "$DOSSIER_DEPLOIEMENT" = "/" ]; then
  echo "Erreur : dossier de déploiement invalide."
  exit 1
fi

if [ ! -d "$DOSSIER_DEV_LOCAL" ]; then
  echo "Erreur : dossier dev local introuvable : $DOSSIER_DEV_LOCAL"
  exit 1
fi

cd "$DOSSIER_DEPOT"

git add -A

if git diff --cached --quiet; then
  echo "Aucune modification à commit."
else
  git commit -m "$MESSAGE_COMMIT"
fi

rm -rf "$DOSSIER_DEPLOIEMENT"
mkdir -p "$DOSSIER_DEPLOIEMENT"

rsync -avm \
  --exclude='.git/' \
  --exclude='.github/' \
  --exclude='.well-known/' \
  --exclude='.htaccess' \
  --exclude='.gitignore' \
  --exclude='config/' \
  --exclude='sql/' \
  --exclude='var/' \
  --exclude='*.bak' \
  --exclude='*.bak.*' \
  --exclude='preparer_deploiement.sh' \
  --exclude='preparer_commit_et_deploiement.sh' \
  --exclude='readme.txt' \
  --exclude='propager_vers_dev.sh' \
  --exclude='preparer_zip.sh' \
  "$DOSSIER_DEPOT"/ \
  "$DOSSIER_DEPLOIEMENT"/

echo "Dossier de déploiement prêt : $DOSSIER_DEPLOIEMENT"

sudo rsync -av --delete \
  --chown=www-data:www-data \
  --exclude='.htaccess' \
  --exclude='config/' \
  --exclude='sql/' \
  --exclude='var/' \
  "$DOSSIER_DEPLOIEMENT"/ \
  "$DOSSIER_DEV_LOCAL"/

echo "Propagation locale terminée : $DOSSIER_DEV_LOCAL"