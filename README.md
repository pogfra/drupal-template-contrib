# Drupal — environnement de contribution

Environnement local pour développer et contribuer à **Drupal core** et aux **modules contrib**, avec VS Code et DDEV.
Tous les outils (Composer, Drush, phpcs, PHPStan…) s'exécutent dans le conteneur DDEV : aucun PHP n'est requis sur la machine hôte.

Ce que le dépôt met en place :

- **Installation en une commande** : `make install` démarre DDEV, installe les dépendances, active les hooks Git et installe Drupal (voir [Installation](#installation)).
- **VS Code configuré à l'ouverture du dossier** : les réglages versionnés dans `.vscode/` s'appliquent d'eux-mêmes (standards Drupal, formatage à l'enregistrement, débogage Xdebug). VS Code propose d'installer les extensions recommandées : il suffit d'accepter. phpcs, phpcbf et twigcs y tournent dans DDEV grâce aux wrappers de `.vscode/bin` : les écarts sont soulignés pendant la frappe et corrigés à l'enregistrement (voir [Configuration de VS Code](#configuration-de-vs-code)).
- **Hooks Git, activés par `make install`** (voir [Hooks Git](#hooks-git--pre-commit-et-commit-msg)) :
  - `pre-commit` : sur le code custom indexé, phpcbf corrige et réindexe, puis phpcs, phpmd, PHPStan et twigcs vérifient ; le commit est bloqué en cas d'erreur ;
  - `commit-msg` : impose le format [Conventional Commits](https://www.conventionalcommits.org/fr/v1.0.0/) (`feat(mon_module): …`).
- **Pipeline GitLab CI bloquant** : les mêmes contrôles, plus les tests PHPUnit, sur chaque merge request (voir [GitLab CI](#gitlab-ci--pipeline-qualité-bloquant)).

| Élément | Version |
|---|---|
| Drupal core | 11.x-dev (`drupal/recommended-project`) |
| PHP | 8.4 (conteneur DDEV) |
| Base de données | MariaDB 11.8 |
| Serveur web | nginx-fpm |
| Node.js | 24 (conteneur DDEV) |
| Drush | 13 |

## Branches

Le dépôt suit les branches de développement de Drupal, pour rester toujours à jour :

| Branche | Drupal core | PHP | Drush | Particularités |
|---|---|---|---|---|
| `11.x` | 11.x-dev | 8.4 | 13.x-dev | — |
| `12.x` | 12.x-dev | 8.5 | 14.x-dev | Olivero et Claro installés depuis contrib (retirés du cœur) |

Les deux branches partagent la même base : la `12.x` n'ajoute qu'un commit, qui passe le projet en Drupal 12.

Après un changement de branche, reconstruire l'environnement :

```bash
git switch 12.x
ddev restart   # applique la version de PHP de la branche
make install   # réinstalle les dépendances et le site
```

> `make install` réinstalle le site de zéro : une base passée en Drupal 12 ne peut pas revenir en Drupal 11.

## Sommaire

1. [Installation](#installation)
2. [Usage](#usage)
3. [Différences macOS / Linux](#différences-macos--linux)
4. [Structure du projet](#structure-du-projet)
5. [Configuration de VS Code](#configuration-de-vs-code)
6. [Le dossier `.vscode` en détail](#le-dossier-vscode-en-détail)
7. [Linters et outils qualité](#linters-et-outils-qualité)
8. [Contribuer à core et aux modules](#contribuer-à-core-et-aux-modules)
9. [Limites connues](#limites-connues)

---

## Installation

### Prérequis

- [DDEV](https://ddev.readthedocs.io/) ≥ 1.25
- Un moteur Docker :
  - **macOS** : [OrbStack](https://orbstack.dev/) (utilisé ici), Docker Desktop ou Colima
  - **Linux** : Docker Engine (paquet `docker-ce`), avec l'utilisateur dans le groupe `docker`
- [VS Code](https://code.visualstudio.com/)
- Git

PHP, Composer et Node **ne sont pas** nécessaires sur l'hôte : tout passe par DDEV.
Les particularités de chaque système sont détaillées dans [Différences macOS / Linux](#différences-macos--linux).

### Démarrer le projet

Après le clone, une seule commande installe tout :

```bash
git clone <url-du-depot> drupal-template-contrib
cd drupal-template-contrib
make install
```

`make install` enchaîne :

1. `ddev start` : démarre les conteneurs ;
2. `ddev composer install` : installe les dépendances ;
3. `git config core.hooksPath .githooks` : active les [hooks Git](#hooks-git--pre-commit-et-commit-msg) (`make hooks`) ;
4. `ddev drush site:install standard` : installe Drupal avec le compte `admin` / `admin` (`make drupal-reinstall`) ;
5. `ddev launch` : ouvre le site dans le navigateur, déjà connecté en administrateur.

`make install` se relance autant de fois que voulu : chaque exécution **supprime la base** et réinstalle Drupal de zéro.
Pour seulement réinstaller Drupal, sans redémarrer DDEV ni relancer Composer : `make drupal-reinstall`.

> Le profil `standard` de Drupal 11.x-dev utilise `/admin/welcome` comme page d'accueil, réservée aux utilisateurs connectés.
> C'est pourquoi `make install` ouvre le site avec un lien de connexion plutôt qu'en anonyme.

### Commandes make

| Commande | Rôle |
|---|---|
| `make` / `make help` | Liste les commandes disponibles |
| `make install` | Installe tout (ou réinstalle de zéro), puis ouvre le site connecté |
| `make start` | Démarre les conteneurs DDEV |
| `make deps` | Installe les dépendances Composer |
| `make hooks` | Active les hooks Git du projet (`.githooks`) |
| `make check-all` | Vérifie tout le code custom (phpcs, phpmd, PHPStan, twigcs), avant un push ou une MR |
| `make drupal-reinstall` | **Supprime la base** et réinstalle Drupal (`drush site:install` seul) |
| `make launch` | Ouvre le site dans le navigateur |
| `make login` | Ouvre le site connecté en administrateur |
| `make stop` | Arrête les conteneurs DDEV |

Le profil et le compte administrateur se changent en ligne de commande :

```bash
make install PROFILE=minimal ACCOUNT_NAME=moi ACCOUNT_PASS=secret
```

`make` est présent par défaut sur Linux. Sur macOS, il est fourni par les outils en ligne de commande Xcode (`xcode-select --install`).

Sans `make`, les mêmes étapes à la main :

```bash
ddev start
ddev composer install
ddev drush site:install standard --account-name=admin --account-pass=admin -y
ddev launch "$(ddev drush uli --no-browser)"
```

### Ouvrir dans VS Code

```bash
code .
```

À l'ouverture, VS Code propose d'installer les extensions recommandées : accepter.
Sinon : palette de commandes (Cmd+Shift+P) → **Extensions: Show Recommended Extensions**.

> **Important :** DDEV doit être démarré pour que les linters fonctionnent dans l'éditeur.

---

## Usage

Le dépôt sert à trois usages. Le code ne vit pas au même endroit dans chacun :

| Usage | Où vit le code | Dépôt Git | Livraison |
|---|---|---|---|
| Module custom | `web/modules/custom/<module>` | Ce dépôt, sur une branche dédiée | Commits sur la branche |
| Contribution à un module contrib | `web/modules/contrib/<module>` | Celui du module (`--prefer-source`) | Merge request sur drupal.org |
| Contribution à core | `web/core` | Aucun : core vient de Composer | Merge request sur drupal.org |

Les branches `11.x` et `12.x` restent le modèle : on part de l'une d'elles, sans jamais y fusionner de travail.

### Développer un module custom

1. Partir de la branche de la version de Drupal visée :

   ```bash
   git switch 11.x
   git switch -c module/<module>
   ```

2. Générer le squelette, puis activer le module :

   ```bash
   ddev drush generate module     # répondre aux questions, dossier web/modules/custom
   ddev drush en <module>
   ```

3. Développer et tester :

   ```bash
   ddev exec SIMPLETEST_DB=mysql://db:db@db/db SIMPLETEST_BASE_URL=http://web \
     vendor/bin/phpunit -c web/core web/modules/custom/<module>
   ```

4. Commiter : les [hooks Git](#hooks-git--pre-commit-et-commit-msg) vérifient le code (phpcbf, phpcs, phpmd, PHPStan, twigcs) et le message (Conventional Commits).

   ```bash
   git add web/modules/custom/<module>
   git commit -m "feat(<module>): ajoute le formulaire de configuration"
   ```

Avant de changer de branche, **désinstaller le module** (`ddev drush pmu <module>`) : Drupal plante si le code d'un module activé disparaît. Autre solution : `make install` après le changement de branche.

> Un module destiné à drupal.org a son propre dépôt dès le départ : créer le projet sur drupal.org, puis suivre [Contribuer à un module contrib](#contribuer-à-un-module-contrib).

### Travailler sur un module contrib

Le module est installé depuis Git (`ddev composer require drupal/<module> --prefer-source`). On travaille dans son dépôt, sur une branche de l'issue fork, puis on ouvre une merge request. Les étapes sont détaillées dans [Contribuer à un module contrib](#contribuer-à-un-module-contrib).

Les hooks de ce dépôt ne s'appliquent pas au dépôt du module : lancer les vérifications à la main, avec la configuration du module.

### Corriger Drupal core

`web/core` n'est pas un dépôt Git : une branche de ce dépôt ne peut pas contenir une modification de core, et Composer l'écrase à la mise à jour suivante. Pour un correctif ponctuel :

1. Prendre un instantané de core, dans un dépôt Git local et jetable :

   ```bash
   git -C web/core init -q
   git -C web/core add -A
   git -C web/core commit -qm "core de référence"
   ```

2. Modifier `web/core` et tester sur le site.

3. Exporter le diff avec les chemins du dépôt `drupal/drupal` (`core/…`), comme les merge requests :

   ```bash
   mkdir -p patches
   git -C web/core diff --src-prefix=a/core/ --dst-prefix=b/core/ \
     > patches/drupal-<issue>.patch
   ```

4. Reporter le diff dans l'issue fork de core, cloné à côté de ce projet, puis pousser :

   ```bash
   git clone --filter=blob:none https://git.drupalcode.org/project/drupal.git ../drupal
   cd ../drupal
   git remote add drupal-<issue> git@git.drupal.org:issue/drupal-<issue>.git
   git fetch drupal-<issue>
   git switch -c <issue>-<description> --track drupal-<issue>/<branche>
   git apply ../drupal-template-contrib/patches/drupal-<issue>.patch
   git commit -am "<description>"
   git push
   ```

5. Revenir à un core propre :

   ```bash
   rm -rf web/core/.git
   ddev composer reinstall drupal/core
   ```

Pour contribuer régulièrement à core, un environnement où core est un clone Git reste préférable (voir [Contribuer à Drupal core](#contribuer-à-drupal-core)).

### Patches : tester une merge request ou corriger un projet

drupal.org n'accepte plus de patches pour core, et les patches ne sont plus testés par la CI des modules : **les contributions passent par des merge requests**. Un patch sert à *appliquer* une correction à un projet qui dépend de core ou d'un module, en attendant sa publication, ou pour tester une merge request.

1. Installer [composer-patches](https://docs.cweagans.net/composer-patches/) (non inclus par défaut) :

   ```bash
   ddev composer require cweagans/composer-patches:^2
   ```

2. Télécharger le diff de la merge request dans le projet. Une merge request évolue : on garde une copie locale plutôt que son URL.

   ```bash
   mkdir -p patches
   curl -fsSL https://git.drupalcode.org/project/<projet>/-/merge_requests/<n>.diff \
     -o patches/<projet>-<issue>-mr<n>.diff
   ```

3. Le déclarer dans `composer.json`, avec le numéro de l'issue :

   ```json
   "extra": {
     "patches": {
       "drupal/<projet>": {
         "#<issue> : <description>": "patches/<projet>-<issue>-mr<n>.diff"
       }
     }
   }
   ```

4. Appliquer :

   ```bash
   ddev composer patches-relock     # met à jour patches.lock.json
   ddev composer patches-repatch    # réinstalle les paquets concernés et applique les patches
   ```

Les diffs de core utilisent les chemins `core/…` : composer-patches les applique au paquet `drupal/core` sans réglage supplémentaire.

---

## Différences macOS / Linux

Le projet fonctionne à l'identique sur les deux systèmes. Seuls les points ci-dessous diffèrent.

| Sujet | macOS | Linux |
|---|---|---|
| Moteur Docker | OrbStack, Docker Desktop ou Colima | Docker Engine natif |
| Accès à `docker` sans `sudo` | Automatique | Ajouter l'utilisateur au groupe `docker` (voir ci-dessous) |
| Partage des fichiers avec le conteneur | **Mutagen** (synchronisation, activé ici) | Montage direct (bind mount), instantané |
| Commande `code` dans le terminal | À installer depuis VS Code | Installée avec le paquet VS Code |
| Chemin du projet (`twigcs.executablePath`) | `/Users/<vous>/…` | `/home/<vous>/…` |
| Port Xdebug 9003 | Rien à faire | Peut être bloqué par le pare-feu |
| Raccourcis clavier | Cmd | Ctrl |

### Accès à Docker (Linux)

Les wrappers de `.vscode/bin` appellent `docker exec` directement. Sans accès à Docker pour l'utilisateur courant, ils échouent en silence dans VS Code :

```bash
sudo usermod -aG docker $USER
# puis se déconnecter / reconnecter (ou redémarrer la session)
docker ps   # doit fonctionner sans sudo
```

Sur macOS, OrbStack et Docker Desktop installent `docker` dans `/usr/local/bin`. Ce chemin est ajouté explicitement dans `ddev-run.sh`, car VS Code lancé depuis le Dock n'hérite pas toujours du `PATH` du terminal.

### Synchronisation des fichiers : Mutagen (macOS)

Sur macOS, DDEV utilise **Mutagen** (`Perf mode: mutagen` dans `ddev describe`) : les fichiers sont *copiés* entre l'hôte et le conteneur, avec un léger décalage.
Sur Linux, le dossier est monté directement dans le conteneur, sans délai.

Conséquences sur macOS :

- **phpcs / phpcbf dans VS Code** : pas d'impact, le contenu du fichier est envoyé directement à l'outil (entrée standard).
- **twigcs dans VS Code** : l'outil lit le fichier *dans le conteneur*. Juste après un enregistrement, il peut analyser l'ancienne version ; l'erreur se met à jour au passage suivant.
- **`vendor/` ou `node_modules/` modifiés dans le conteneur** (`ddev composer …`, `yarn install`) : ils mettent quelques secondes à apparaître côté hôte.
- **Si la synchronisation semble bloquée** :

  ```bash
  ddev mutagen status
  ddev utility mutagen-diagnose
  ddev mutagen reset && ddev restart
  ```

### Commande `code` (macOS)

Dans VS Code : Cmd+Shift+P → **Shell Command: Install 'code' command in PATH**.

### Chemin absolu de twigcs

L'extension twigcs exige un chemin absolu dans `settings.json`. Adapter `twigcs.executablePath` selon la machine :

```jsonc
// macOS
"twigcs.executablePath": "/Users/<vous>/Workstation/drupal-template-contrib/.vscode/bin/twigcs",
// Linux
"twigcs.executablePath": "/home/<vous>/Workstation/drupal-template-contrib/.vscode/bin/twigcs",
```

### Xdebug (Linux)

Si le débogueur ne s'arrête jamais sur les points d'arrêt, le pare-feu bloque sans doute le port 9003 :

```bash
sudo ufw allow 9003/tcp
ddev utility diagnose   # ou : ddev utility xdebug-diagnose
```

---

## Structure du projet

```
.
├── .ddev/                  Configuration DDEV (config.yaml)
├── .githooks/              Hooks Git versionnés (pre-commit, commit-msg)
├── .gitlab-ci.yml          Pipeline GitLab CI (qualité et tests du code custom)
├── .vscode/                Configuration VS Code partagée (voir plus bas)
├── .editorconfig           Règles d'indentation Drupal (scaffold, non versionné)
├── .gitignore              Dépendances Composer, fichiers scaffoldés, fichiers du site, caches
├── Makefile                Raccourcis d'installation et de pilotage DDEV (make help)
├── composer.json           Dépendances (core, drush, outils de dev)
├── phpcs.xml.dist          Règles phpcs du projet (code custom)
├── phpmd.xml.dist          Règles phpmd du projet (code custom)
├── phpstan.neon.dist       Configuration PHPStan du projet (code custom)
├── recipes/                Recipes Drupal
├── vendor/                 Dépendances Composer (+ vendor/bin : phpcs, phpstan…)
└── web/                    Racine web
    ├── core/               Drupal core
    ├── modules/
    │   ├── contrib/        Modules contrib (installés par Composer)
    │   └── custom/         Modules du projet (ex. app)
    ├── themes/
    │   ├── contrib/
    │   └── custom/
    └── sites/default/      settings.php, settings.ddev.php, files/
```

### Dépendances de développement

Installées en `require-dev` :

| Paquet | Fournit |
|---|---|
| `drupal/core-dev` | Coder (standards Drupal), phpcs/phpcbf, PHPStan + phpstan-drupal, PHPUnit |
| `friendsoftwig/twigcs` | Vérification des templates Twig |
| `phpmd/phpmd` | Complexité, code mort et conception (PHP Mess Detector) |

### Fichiers scaffoldés

Le plugin `drupal/core-composer-scaffold` copie depuis core les fichiers d'amorçage : `index.php`, `update.php`, `.htaccess`, `robots.txt`, `default.settings.php`, `.editorconfig`, etc.
Ils sont recréés à chaque `ddev composer install` / `update`, donc **ils ne sont pas versionnés**.

Configuration dans `composer.json` (`extra.drupal-scaffold`) :

- **`"gitignore": true`** : le plugin ajoute chaque fichier qu'il copie au `.gitignore` de son dossier (`web/.gitignore`, `web/sites/.gitignore`, `web/sites/default/.gitignore`). `.editorconfig` et `.gitattributes` sont listés à la main dans le `.gitignore` racine.
- **`file-mapping`** : les fichiers de documentation de core (`INSTALL.txt`, `README.md`, les `README.txt` des dossiers) et `example.gitignore` sont désactivés (`false`) et ne sont plus générés. `robots.txt` est conservé, car il sert au référencement.

Pour forcer la régénération :

```bash
ddev composer drupal:scaffold
```

> Le plugin n'ajoute au `.gitignore` que les fichiers qu'il écrit réellement : un fichier déjà présent et identique est ignoré, tout comme un fichier déjà suivi par Git.
> Pour qu'un fichier existant soit ajouté au `.gitignore`, le supprimer puis relancer la commande.

---

## Configuration de VS Code

La configuration suit la documentation officielle :
[Configuring Visual Studio Code — drupal.org](https://www.drupal.org/docs/develop/development-tools/editors-and-ides/configuring-visual-studio-code).

### Extensions recommandées

| Extension | Rôle | Configuration dans ce projet |
|---|---|---|
| **PHP Tools** (`devsense.phptools-vscode`) | Support PHP principal : complétion, navigation, diagnostics, refactoring. | Aucune configuration spécifique. Fait doublon avec Intelephense, à désactiver (voir [Limites connues](#limites-connues)). |
| **Composer** (`devsense.composer-php-vscode`) | Complétion et actions dans `composer.json`. | Aucune. |
| **PHP Sniffer & Beautifier** (`valeryanm.vscode-phpsab`) | Lance **phpcs** pour souligner les écarts aux standards Drupal, et **phpcbf** pour les corriger. | Pointe vers `.vscode/bin/phpcs` et `.vscode/bin/phpcbf` (exécutés dans DDEV). Analyse pendant la frappe (500 ms après l'arrêt). Défini comme formateur PHP avec correction automatique à l'enregistrement. Règles : `phpcs.xml.dist` le plus proche du fichier, sinon `Drupal,DrupalPractice`. |
| **PHPStan** (`sanderronde.phpstan-vscode`) | Analyse statique en temps réel. | Non branché sur DDEV pour l'instant : utiliser la ligne de commande (voir [Limites connues](#limites-connues)). |
| **PHP DocBlocker** (`neilbrayfield.php-docblocker`) | Génère les blocs `/** … */` en tapant `/**` puis Entrée. | Aucune. |
| **Drupal Smart Snippets** (`andrewdavidblum.drupal-smart-snippets`) | Snippets pour les hooks et les API Drupal (taper le nom du hook, ex. `hook_form_alter`). | Aucune. |
| **Twig Language 2** (`mblode.twig-language-2`) | Coloration, snippets et formatage Twig. | Emmet activé dans les fichiers Twig. |
| **Twigcs** (`cerzat43.twigcs`) | Signale les écarts de style dans les templates Twig. | Pointe vers `.vscode/bin/twigcs` (exécuté dans DDEV), en chemin absolu. |
| **ESLint** (`dbaeumer.vscode-eslint`) | Lint et formatage JavaScript selon la configuration de core. | Défini comme formateur JavaScript. Nécessite les dépendances Node de core (voir [ESLint et Stylelint](#eslint-et-stylelint-javascript-et-css)). |
| **EditorConfig** (`editorconfig.editorconfig`) | Applique `.editorconfig` fichier par fichier (2 espaces, LF, retour à la ligne final ; 4 espaces pour `composer.json`). | Aucune : lit `.editorconfig`. |
| **PHP Debug** (`xdebug.php-debug`) | Débogage pas à pas avec Xdebug. | Configuration dans `launch.json` et `tasks.json`. |
| **DDEV Manager** (`biati.ddev-manager`) | Démarrer, arrêter et piloter DDEV depuis VS Code. | Aucune. |
| **DBCode** (`dbcode.dbcode`) | Client de base de données dans VS Code. | À connecter manuellement avec les accès de `ddev describe`. |

L'extension **Intelephense** est marquée comme *non souhaitée* pour ce projet, car elle fait doublon avec PHP Tools.

### Comportement de l'éditeur

Réglages partagés via `.vscode/settings.json`, en cohérence avec les standards Drupal :

- **Fichiers PHP de Drupal** : `.module`, `.install`, `.theme`, `.inc`, `.profile`, `.engine`, `.test` et `.make` sont traités comme du PHP ; `.info` comme de l'INI.
- **Indentation** : 2 espaces (jamais de tabulations), sans détection automatique.
- **Longueur de ligne** : guide vertical à 80 caractères.
- **Fins de ligne et espaces** : LF, espaces de fin de ligne supprimés, une seule nouvelle ligne en fin de fichier.
- **Enregistrement automatique** : au changement de focus (`onFocusChange`). Le mode `afterDelay` empêcherait la correction automatique à l'enregistrement.
- **Exclusions** : `node_modules`, `web/sites/*/files` et `.ddev` sont exclus de la surveillance des fichiers et de la recherche.

---

## Le dossier `.vscode` en détail

```
.vscode/
├── extensions.json     Extensions recommandées / non souhaitées
├── settings.json       Réglages de l'éditeur pour ce projet
├── launch.json         Configuration de débogage Xdebug
├── tasks.json          Tâches DDEV (activer / désactiver Xdebug)
├── php-ddev.sh         Exécute « php » dans le conteneur DDEV
└── bin/
    ├── ddev-run.sh     Exécute un outil de vendor/bin dans DDEV
    ├── phpcs  ─┐
    ├── phpcbf  ├─→ liens symboliques vers ddev-run.sh
    └── twigcs ─┘
```

### `extensions.json`

- **`recommendations`** : les extensions listées plus haut. VS Code propose de les installer à l'ouverture du projet.
- **`unwantedRecommendations`** : Intelephense, qui fait doublon avec PHP Tools.

### `settings.json`

Les réglages de l'espace de travail : associations de fichiers, phpsab, formatage à l'enregistrement, indentation, twigcs, ESLint et exclusions.
Ils ne s'appliquent qu'à ce projet et prennent le dessus sur vos réglages utilisateur.

La validation PHP intégrée de VS Code (`php.validate`) est **désactivée** : il n'y a pas de PHP sur l'hôte, et PHP Tools couvre déjà la syntaxe.
Une ligne commentée permet de la réactiver via `php-ddev.sh`.

### `launch.json`

Configuration **« Listen for Xdebug (DDEV) »** :

- écoute sur le port **9003** ;
- fait correspondre `/var/www/html` (conteneur) au dossier du projet (hôte) ;
- lance la tâche `DDEV: Enable Xdebug` au démarrage et `DDEV: Disable Xdebug` à l'arrêt.

Xdebug n'est donc actif que pendant une session de débogage : le site n'est pas ralenti le reste du temps.

**Utilisation** : poser un point d'arrêt, appuyer sur F5, puis recharger la page dans le navigateur (ou lancer une commande `ddev drush …`).

### `tasks.json`

Deux tâches appelées par `launch.json` :

| Tâche | Commande |
|---|---|
| `DDEV: Enable Xdebug` | `ddev xdebug on` |
| `DDEV: Disable Xdebug` | `ddev xdebug off` |

Elles sont aussi lançables à la main : Cmd+Shift+P → **Tasks: Run Task**.

### `php-ddev.sh`

Wrapper qui fait exécuter les appels à `php` par le conteneur web DDEV.
Il lit le nom du projet dans `.ddev/config.yaml`, remplace le chemin de l'hôte par `/var/www/html`, puis lance `docker exec … php`.
Il n'est pas utilisé actuellement (voir `php.validate` ci-dessus).

### `bin/ddev-run.sh` et ses liens `phpcs`, `phpcbf`, `twigcs`

Les exécutables de `vendor/bin` commencent par `#!/usr/bin/env php` et échouent sur un hôte sans PHP.
`ddev-run.sh` les exécute dans le conteneur :

1. déduit l'outil à lancer du nom du lien (`phpcs` → `vendor/bin/phpcs`) ;
2. lit le nom du projet DDEV dans `.ddev/config.yaml` ;
3. remplace le chemin de l'hôte par `/var/www/html` dans les arguments ;
4. lance `docker exec -i` sur le conteneur `ddev-<projet>-web`, en transmettant l'entrée standard (phpsab envoie le contenu du fichier en cours d'édition).

`docker exec` est utilisé plutôt que `ddev exec` : il démarre plus vite, ce qui compte pour une analyse pendant la frappe.

**Ajouter un outil** (ex. `phpstan`) :

```bash
ln -s ddev-run.sh .vscode/bin/phpstan
```

Ces scripts sont aussi utilisables dans le terminal :

```bash
.vscode/bin/phpcs web/modules/custom/app
```

---

## Linters et outils qualité

Toutes les commandes se lancent depuis la racine du projet.

### phpcs / phpcbf — standards de code Drupal

Standards `Drupal` et `DrupalPractice` fournis par [Coder](https://www.drupal.org/project/coder).

**Dans VS Code** : les erreurs sont soulignées pendant la frappe et listées dans le panneau **Problèmes** (Cmd+Shift+M).
À l'enregistrement, phpcbf corrige automatiquement ce qui peut l'être (indentation, espaces, accolades, structure des commentaires).
Ce qui reste (commentaires à rédiger, nommage, etc.) est à corriger à la main.

**En ligne de commande :**

```bash
# Code custom (utilise phpcs.xml.dist à la racine)
ddev exec vendor/bin/phpcs
ddev exec vendor/bin/phpcbf

# Un module précis
ddev exec vendor/bin/phpcs web/modules/custom/app
```

`phpcs.xml.dist` à la racine :

- analyse `web/modules/custom` et `web/themes/custom` ;
- inclut les extensions Drupal (`module`, `install`, `theme`, `inc`, `yml`, `info`, `md`…) ;
- applique `Drupal` + `DrupalPractice`.

Pour une configuration locale sans la versionner, créer un `phpcs.xml` : phpcs le lit en priorité sur `phpcs.xml.dist`.

### twigcs — templates Twig

**Dans VS Code** : les erreurs apparaissent dans les fichiers `.twig`, après enregistrement (l'extension lit le fichier sur le disque).

**En ligne de commande :**

```bash
ddev exec vendor/bin/twigcs web/themes/custom
```

### PHPStan — analyse statique

Inclut [phpstan-drupal](https://github.com/mglaman/phpstan-drupal) et les règles de dépréciation, chargés automatiquement.

```bash
# Code custom (utilise phpstan.neon.dist à la racine)
ddev exec vendor/bin/phpstan analyse

# Détecter les dépréciations avant une montée de version
ddev exec vendor/bin/phpstan analyse --level=0 web/modules/contrib/<module>
```

`phpstan.neon.dist` à la racine :

- analyse `web/modules/custom` et `web/themes/custom` ;
- niveau 6 : types des paramètres, des retours et des propriétés exigés ;
- ignore les types de valeur des tableaux (`missingType.iterableValue`) : les render arrays et `$form` de Drupal n'en ont pas.

Pour une configuration locale sans la versionner, créer un `phpstan.neon` qui l'inclut (`includes: [phpstan.neon.dist]`).

> Tant que `web/modules/custom` et `web/themes/custom` ne contiennent aucun code, phpcs et PHPStan lancés sans chemin s'arrêtent sur « aucun fichier à analyser ».

### phpmd — complexité et code mort

phpmd ne fait pas partie de l'outillage de core. `phpmd.xml.dist` à la racine ne garde que les règles compatibles avec Drupal :

- `codesize` : complexité cyclomatique, NPath, taille des méthodes et des classes (variantes `Ncss`, qui ne comptent pas les docblocks) ;
- `unusedcode`, sauf `UnusedFormalParameter` : les hooks et les implémentations d'interfaces ont une signature imposée ;
- `design` : `exit`, `eval`, `goto`, couplage, profondeur d'héritage, blocs `catch` vides, fonctions de débogage (y compris `dpm()`, `kint()`… de Devel) ;
- exclus : `cleancode` (`StaticAccess` sur `\Drupal::`, `ElseExpression`), `naming` (snake_case du code procédural) et `controversial`, en conflit avec les conventions Drupal ;
- les dossiers `tests/` ne sont pas analysés.

```bash
ddev exec vendor/bin/phpmd web/modules/custom text phpmd.xml.dist \
  --suffixes php,module,inc,install,test,profile,theme
```

> phpmd 2.x affiche des avis de dépréciation sous PHP 8.4 : ils viennent de l'outil lui-même, pas du code analysé. Le hook pre-commit les masque.

Sur la branche `12.x`, phpmd est en `3.x-dev` : la version 2 ne prend pas en charge Symfony 8, requis par Drupal 12. La version 3 remplace les arguments positionnels par une sous-commande `analyze`, avec une option `--suffixes` par extension. Le hook pre-commit choisit la bonne syntaxe selon la version installée.

```bash
ddev exec vendor/bin/phpmd analyze web/modules/custom --ruleset phpmd.xml.dist \
  --suffixes=php --suffixes=module --suffixes=inc --suffixes=install \
  --suffixes=test --suffixes=profile --suffixes=theme
```

### Hooks Git — pre-commit et commit-msg

Les hooks sont versionnés dans `.githooks/` et activés par `make hooks` (inclus dans `make install`), qui règle `core.hooksPath` pour ce dépôt.

**pre-commit** : sur les fichiers indexés de `web/modules/custom` et `web/themes/custom`, dans le conteneur DDEV :

1. **phpcbf** corrige ce qui peut l'être et réindexe les fichiers corrigés ;
2. **phpcs** vérifie le reste (`phpcs.xml.dist`) ; un avertissement bloque aussi le commit ;
3. **phpmd** (`phpmd.xml.dist`) et **PHPStan** (`phpstan.neon.dist`) analysent les fichiers PHP ;
4. **twigcs** vérifie les templates `.twig`.

Le commit est bloqué si un outil signale un problème. Si un fichier n'est indexé qu'en partie (`git add -p`), le hook refuse de le vérifier et de le réindexer, pour ne pas inclure du travail non choisi : `git stash push --keep-index`, commit, puis `git stash pop`.

**commit-msg** : impose [Conventional Commits](https://www.conventionalcommits.org/fr/v1.0.0/).

```
<type>(<portée>)?: <description>

feat(mon_module): ajoute un bloc de recherche
fix!: supprime l'ancienne API
```

- types : `build`, `chore`, `ci`, `docs`, `feat`, `fix`, `perf`, `refactor`, `revert`, `style`, `test` ;
- titre de 100 caractères au plus, suivi d'une ligne vide s'il y a un corps ;
- les messages générés par Git (`Merge`, `Revert`, `fixup!`, `squash!`) sont acceptés.

Limites :

- DDEV doit tourner, sinon le pre-commit bloque en le signalant ;
- les dépôts des modules contrib installés en `--prefer-source` ont leurs propres hooks : ceux-ci ne s'y appliquent pas.

**Vérification complète : `make check-all`**

Le pre-commit ne regarde que les fichiers du commit. Or une modification peut casser un fichier non modifié : une signature de méthode changée casse ses appels ailleurs, et seul PHPStan sur l'ensemble du code le détecte.
Avant un push ou une merge request, lancer :

```bash
make check-all
```

Les mêmes outils (phpcs, phpmd, PHPStan, twigcs) vérifient alors tout le code custom de `web/modules/custom` et `web/themes/custom`, fichiers suivis et nouveaux. Rien n'est corrigé ni indexé.

### GitLab CI — pipeline qualité bloquant

Le hook pre-commit donne un retour rapide sur le poste, mais il peut être contourné. `.gitlab-ci.yml` refait les mêmes contrôles côté serveur, avec les mêmes fichiers de règles (`phpcs.xml.dist`, `phpstan.neon.dist`, `phpmd.xml.dist`), sur **tout** le code custom.

| Étape | Job | Rôle | Rapport dans la MR |
|---|---|---|---|
| build | `composer` | `composer validate`, puis `composer install` (cache basé sur `composer.lock`) | — |
| quality | `commits` | Chaque commit de la MR respecte Conventional Commits (hook `commit-msg`) | — |
| quality | `phpcs` | Standards Drupal | Tests (JUnit) |
| quality | `phpmd` | Complexité, code mort | Code Quality |
| quality | `phpstan` | Analyse statique, niveau 6 | Code Quality |
| quality | `twigcs` | Templates Twig | Code Quality |
| test | `phpunit` | Tests Unit et Kernel des modules custom, avec MariaDB 11.8 | Tests (JUnit) |

- **Tous les jobs sont bloquants** : aucun `allow_failure`.
- **Un job sans fichier à analyser est sauté** (`rules: exists`) : tant que `web/modules/custom` est vide, seul `composer` tourne.
- **Images** : celles de la CI de drupal.org (`drupalci-environments`), qui contiennent PHP, Composer, Git et les extensions de Drupal. La variable `PHP_VERSION` vaut `8.4` sur `11.x` et `8.5` sur `12.x`.
- **Déclenchement** : pipelines de merge request, et pipelines de branche quand aucune MR n'est ouverte (pas de doublon).
- **Tests Functional** non exécutés : ils demandent un serveur web et un navigateur.

**Rendre le pipeline bloquant dans GitLab.** Un pipeline en échec n'empêche rien à lui seul. Dans les réglages du projet GitLab :

1. *Settings → Repository → Protected branches* : protéger `11.x` et `12.x`, avec *Allowed to merge* à *Maintainers* et *Allowed to push and merge* à *No one* ;
2. *Settings → Merge requests* : cocher *Pipelines must succeed*.

Toute modification passe alors par une merge request, qui ne peut être fusionnée que si le pipeline est vert.

**Drupal 12 : tests de core installés depuis Git.** Depuis l'issue [#3067979](https://www.drupal.org/node/3067979), les archives de `drupal/core` 12 ne contiennent plus le dossier `tests/` (classes `UnitTestCase`, `KernelTestBase`, bootstrap de PHPUnit). Sur la branche `12.x`, `composer.json` installe donc `drupal/core` depuis Git (`preferred-install`), et le reste en archive. Cela vaut pour la CI comme pour le poste.

**Tester le pipeline en local** avec [gitlab-ci-local](https://github.com/firecow/gitlab-ci-local), dans un conteneur (rien à installer sur l'hôte) :

```bash
docker run --rm -v /var/run/docker.sock:/var/run/docker.sock \
  -v "$PWD:$PWD" -w "$PWD" docker:cli sh -c '
    apk add -q --no-cache nodejs npm git bash rsync
    git config --global --add safe.directory "$PWD"
    npx -y gitlab-ci-local --artifacts-to-source=false'
```

- Seuls les fichiers suivis par Git (ou indexés) sont copiés dans les jobs.
- `--artifacts-to-source=false` évite que `vendor/` et `web/` du job écrasent ceux du projet.
- Pour simuler une merge request : `--variable CI_PIPELINE_SOURCE=merge_request_event --variable CI_MERGE_REQUEST_DIFF_BASE_SHA=<commit de base>`.
- Les résultats et le cache vont dans `.gitlab-ci-local/`, ignoré par Git.

### PHPUnit — tests

```bash
ddev exec \
  SIMPLETEST_DB=mysql://db:db@db/db \
  SIMPLETEST_BASE_URL=http://web \
  vendor/bin/phpunit -c web/core web/modules/custom/app
```

Tests unitaires et kernel : fonctionnent tels quels.
Tests fonctionnels JavaScript : nécessitent un navigateur (add-on DDEV `ddev/ddev-selenium-standalone-chrome`).

### ESLint et Stylelint — JavaScript et CSS

Les configurations sont celles de core (`web/core/.eslintrc.json`, `.stylelintrc.json`, `.prettierrc.json`). Installer d'abord les dépendances Node de core :

```bash
ddev exec -d /var/www/html/web/core yarn install
```

Puis :

```bash
ddev exec -d /var/www/html/web/core yarn lint:core-js     # ESLint sur core
ddev exec -d /var/www/html/web/core yarn lint:css         # Stylelint sur core
ddev exec -d /var/www/html/web/core yarn spellcheck:core  # cspell sur core

# Un module avec la configuration de core
ddev exec -d /var/www/html/web/core \
  yarn eslint --no-eslintrc -c .eslintrc.passing.json ../modules/contrib/<module>
```

### Récapitulatif

| Outil | Cible | Dans VS Code | Commande |
|---|---|---|---|
| phpcs | PHP, YAML | Pendant la frappe | `ddev exec vendor/bin/phpcs <chemin>` |
| phpcbf | PHP | À l'enregistrement | `ddev exec vendor/bin/phpcbf <chemin>` |
| twigcs | Twig | À l'enregistrement | `ddev exec vendor/bin/twigcs <chemin>` |
| PHPStan | PHP | — | `ddev exec vendor/bin/phpstan analyse <chemin>` |
| phpmd | PHP | — | `ddev exec vendor/bin/phpmd <chemin> text phpmd.xml.dist` |
| PHPUnit | Tests | — | voir plus haut |
| ESLint | JS, YAML | Si `web/core/node_modules` existe | `yarn lint:core-js` (dans `web/core`) |
| Stylelint | CSS | — | `yarn lint:css` (dans `web/core`) |

phpcbf, phpcs, phpmd, PHPStan et twigcs tournent aussi à chaque commit sur les fichiers indexés, et sur tout le code custom avec `make check-all` (voir [Hooks Git](#hooks-git--pre-commit-et-commit-msg)).

---

## Contribuer à core et aux modules

### Règle générale

Chaque projet a ses propres règles. **Toujours utiliser la configuration du projet contribué**, pas celle de ce dépôt :

| Cible | Configuration phpcs | Configuration PHPStan |
|---|---|---|
| Code custom | `phpcs.xml.dist` (racine) | options en ligne de commande |
| Drupal core | `web/core/phpcs.xml.dist` | `web/core/phpstan.neon.dist` |
| Module contrib | `phpcs.xml.dist` du module s'il existe, sinon `Drupal,DrupalPractice` | `phpstan.neon(.dist)` du module s'il existe |

Dans VS Code, phpsab cherche automatiquement le fichier de règles le plus proche du fichier édité. Un fichier de core est donc vérifié avec les règles de core.

### Contribuer à un module contrib

1. Installer le module depuis Git plutôt que depuis une archive :

   ```bash
   ddev composer require drupal/<module> --prefer-source
   ```

   `web/modules/contrib/<module>` est alors un dépôt Git.

2. Sur la page de l'issue drupal.org, créer l'**issue fork**, puis ajouter le remote :

   ```bash
   cd web/modules/contrib/<module>
   git remote add <module>-<issue> git@git.drupal.org:issue/<module>-<issue>.git
   git fetch <module>-<issue>
   git checkout -b <issue>-<description> --track <module>-<issue>/<branche>
   ```

3. Vérifier avant de pousser :

   ```bash
   ddev exec vendor/bin/phpcs --standard=Drupal,DrupalPractice \
     --extensions=php,module,inc,install,test,profile,theme,info,yml \
     web/modules/contrib/<module>
   ddev exec vendor/bin/phpstan analyse --level=0 web/modules/contrib/<module>
   ddev exec SIMPLETEST_DB=mysql://db:db@db/db SIMPLETEST_BASE_URL=http://web \
     vendor/bin/phpunit -c web/core web/modules/contrib/<module>
   ```

4. Pousser la branche et ouvrir la merge request depuis l'issue.

### Contribuer à Drupal core

Dans ce projet, `web/core` vient de Composer : **ce n'est pas un dépôt Git**. Deux options :

- **Correctif ponctuel** : modifier `web/core`, puis produire un diff à partir d'un clone de core.
- **Contribution régulière** (recommandé) : utiliser un environnement dédié, où core est un clone Git :
  - [ddev/ddev-drupal-contrib](https://github.com/ddev/ddev-drupal-contrib) pour les modules ;
  - [joachim-n/drupal-core-development-project](https://github.com/joachim-n/drupal-core-development-project) pour core.

Vérifications avec la configuration de core :

```bash
# phpcs avec les règles de core
ddev exec -d /var/www/html/web/core ../../vendor/bin/phpcs <chemin/relatif/à/core>

# PHPStan avec la configuration et la baseline de core
ddev exec -d /var/www/html/web/core \
  ../../vendor/bin/phpstan analyse -c phpstan.neon.dist <chemin>

# Tests
ddev exec SIMPLETEST_DB=mysql://db:db@db/db SIMPLETEST_BASE_URL=http://web \
  vendor/bin/phpunit -c web/core web/core/modules/<module>/tests/src/Unit

# JS, CSS, orthographe
ddev exec -d /var/www/html/web/core yarn lint:core-js-passing
ddev exec -d /var/www/html/web/core yarn lint:css
ddev exec -d /var/www/html/web/core yarn spellcheck:core
```

Ressources :

- [Contributor guide](https://www.drupal.org/community/contributor-guide)
- [Coding standards](https://www.drupal.org/docs/develop/standards)
- [Issue forks et merge requests](https://www.drupal.org/docs/develop/git/using-gitlab-to-contribute-to-drupal)

---

## Limites connues

- **DDEV doit tourner.** Sans conteneur, phpcs, phpcbf et twigcs échouent en silence dans VS Code.
- **Extension PHPStan non branchée.** Elle exige PHP sur l'hôte. Solution envisagée : une commande personnalisée via `.vscode/bin/phpstan`. En attendant, utiliser la ligne de commande.
- **Chemin absolu pour twigcs.** L'extension n'accepte pas de chemin relatif : `twigcs.executablePath` contient le chemin de la machine d'origine (macOS) et doit être adapté sur un autre poste (voir [Différences macOS / Linux](#chemin-absolu-de-twigcs)).
- **Windows non pris en charge.** Les wrappers sont des scripts Bash reliés par des liens symboliques : utiliser WSL2.
- **Doublon Intelephense / PHP Tools.** Si Intelephense est installé, le désactiver pour cet espace de travail (clic droit sur l'extension → *Désactiver (espace de travail)*), sinon les diagnostics apparaissent en double.
- **ESLint dans l'éditeur** : ne fonctionne qu'après `yarn install` dans `web/core`, et seulement pour les fichiers couverts par une configuration ESLint.