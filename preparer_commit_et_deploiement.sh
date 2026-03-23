#!/usr/bin/env bash
# ./preparer_commit_et_deploiement.sh
set -euo pipefail

DOSSIER_DEPOT="/home/sylvere/Git_depot_local/sr_licences"
DOSSIER_DEPLOIEMENT="/mnt/c/Users/soulr/Downloads/sr_licences_deploiement"

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
  "$DOSSIER_DEPOT"/ \
  "$DOSSIER_DEPLOIEMENT"/

echo "Commit traité et dossier de déploiement prêt : $DOSSIER_DEPLOIEMENT"