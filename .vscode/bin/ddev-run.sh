#!/usr/bin/env bash
# Lance un outil de vendor/bin (phpcs, phpcbf, twigcs...) dans le conteneur web DDEV.
# Chaque lien symbolique de ce dossier porte le nom de l'outil à exécuter.
export PATH="/usr/local/bin:/opt/homebrew/bin:$PATH"
TOOL=$(basename "$0")
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"

# Nom du projet DDEV (champ "name" de config.yaml, sinon nom du dossier)
NAME=$(grep -E '^name:' "$ROOT/.ddev/config.yaml" | awk '{print $2}')
NAME=${NAME:-$(basename "$ROOT")}

# Remplace le chemin du Mac par celui du conteneur (/var/www/html)
args=()
for a in "$@"; do args+=("${a//$ROOT//var/www/html}"); done

exec docker exec -i -w /var/www/html "ddev-${NAME}-web" "vendor/bin/${TOOL}" "${args[@]}"
