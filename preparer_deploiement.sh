#!/usr/bin/env bash
# ./preparer_deploiement.sh
set -euo pipefail

DOSSIER_SOURCE_DEV="/home/sylvere/Git_depot_local/sr_licences"
DOSSIER_DEPLOIEMENT_PROD="/mnt/c/Users/soulr/Downloads/sr_licences_deploiement_prod"

if [ ! -d "$DOSSIER_SOURCE_DEV" ]; then
  echo "Erreur : dossier source dev introuvable : $DOSSIER_SOURCE_DEV"
  exit 1
fi

if [ -z "${DOSSIER_DEPLOIEMENT_PROD:-}" ] || [ "$DOSSIER_DEPLOIEMENT_PROD" = "/" ]; then
  echo "Erreur : dossier de déploiement prod invalide."
  exit 1
fi

rm -rf "$DOSSIER_DEPLOIEMENT_PROD"
mkdir -p "$DOSSIER_DEPLOIEMENT_PROD"

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
  --exclude='readme.txt' \
  --exclude='readme_sr_licences.txt' \
  --exclude='preparer_deploiement.sh' \
  --exclude='preparer_commit_et_deploiement.sh' \
  --exclude='propager_vers_dev.sh' \
  --exclude='preparer_zip.sh' \
  "$DOSSIER_SOURCE_DEV"/ \
  "$DOSSIER_DEPLOIEMENT_PROD"/

echo "Dossier de déploiement prod prêt : $DOSSIER_DEPLOIEMENT_PROD"
