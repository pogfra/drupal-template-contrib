#!/usr/bin/env bash
# Envoie les appels "php" de VS Code vers le conteneur web de DDEV
cd "$(dirname "$0")/.." || exit 1

# Nom du projet DDEV (champ "name" de config.yaml, sinon nom du dossier)
NAME=$(grep -E '^name:' .ddev/config.yaml | awk '{print $2}')
NAME=${NAME:-$(basename "$PWD")}

# Remplace le chemin du Mac par celui du conteneur (/var/www/html)
args=()
for a in "$@"; do args+=("${a//$PWD//var/www/html}"); done

exec docker exec -i "ddev-${NAME}-web" php "${args[@]}"