#!/usr/bin/env bash
# ./preparer_zip.sh
set -euo pipefail

DOSSIER_REPO="/home/sylvere/Git_depot_local/sr_licences"
ZIP_SORTIE="/home/sylvere/sr_licences.zip"

cd "$DOSSIER_REPO"

rm -f "$ZIP_SORTIE"

zip -r "$ZIP_SORTIE" . \
  -x ".git/*" \
  -x ".well-known/*" \
  -x "config/config.php" \
  -x "sql/sr_licences_prod_dump.sql" \
  -x "*.pem" \
  -x "*.key" \
  -x "*.crt" \
  -x "*.zip" \
  -x "*.tar" \
  -x "*.tar.gz" \
  -x "*.bak" \
  -x "*.bak.*" \
  -x "var/log/*" \
  -x "propager_vers_dev.sh" \
  -x "preparer_deploiement.sh" \
  -x "preparer_commit_et_deploiement.sh" \
  -x "preparer_zip.sh"

echo "ZIP créé : $ZIP_SORTIE"
