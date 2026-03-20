#!/usr/bin/env bash
set -euo pipefail

DOSSIER_SOURCE_DEV="/home/sylvere/Git_depot_local/sr_licences"
DOSSIER_CIBLE_DEV="/var/www/sr_licences_dev"

if [ ! -d "$DOSSIER_SOURCE_DEV" ]; then
  echo "Erreur : dossier source dev introuvable : $DOSSIER_SOURCE_DEV"
  exit 1
fi

if [ -z "${DOSSIER_CIBLE_DEV:-}" ] || [ "$DOSSIER_CIBLE_DEV" = "/" ]; then
  echo "Erreur : dossier cible dev invalide."
  exit 1
fi

sudo mkdir -p "$DOSSIER_CIBLE_DEV"

sudo rsync -a --delete --chown=www-data:www-data \
  --exclude='.git/' \
  --exclude='.github/' \
  --exclude='.well-known/' \
  --exclude='sql/' \
  --exclude='var/' \
  --exclude='*.bak' \
  --exclude='*.bak.*' \
  --exclude='readme.txt' \
  --exclude='readme_sr_licences.txt' \
  --exclude='preparer_deploiement.sh' \
  --exclude='preparer_commit_et_deploiement.sh' \
  --exclude='propager_vers_dev.sh' \
  "$DOSSIER_SOURCE_DEV"/ \
  "$DOSSIER_CIBLE_DEV"/

echo "Propagation vers dev terminée : $DOSSIER_CIBLE_DEV"
