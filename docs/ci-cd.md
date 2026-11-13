# CI/CD

## L'essentiel

L'**intégration continue**, ou CI, vérifie automatiquement chaque changement sur une machine propre. Elle attrape ce qui marche « seulement chez moi ». Le **déploiement continu**, ou CD, publie ensuite le résultat : ce niveau n'en a pas.

**GitHub Actions** exécute un **workflow** décrit dans [.github/workflows/ci.yml](../.github/workflows/ci.yml).

| Terme | Sens |
|---|---|
| Workflow | Le fichier complet, déclenché par un événement |
| Job | Un groupe d'étapes, sur sa propre machine |
| Étape | Une commande, ou une action |
| Action | Un morceau de CI réutilisable, publié par d'autres |
| Runner | La machine temporaire qui exécute un job |

## Ce qui a changé depuis Debutant-Nv2

| Changement | Debutant-Nv2 | Ici | Pourquoi |
|---|---|---|---|
| `persist-credentials` des deux `checkout` | Absent, donc `true` | `false` | Ne pas laisser le jeton GitHub sur le disque du runner, voir plus bas |
| Extensions PHP du job `quality` | `intl` | `intl, pdo_sqlite` | Les tests utilisent SQLite |
| Job `quality` | | Étape « Valider le mapping Doctrine » | Vérifier les attributs de l'entité sans base |
| Démarrage du job `docker` | `docker compose up -d --build` | `docker compose up -d --build --wait` | Attendre que PostgreSQL soit prêt |
| Job `docker` | | Étapes « Appliquer les migrations » et « Vérifier le schéma contre PostgreSQL » | Prouver la migration sur le vrai moteur |
| Contrôle HTTP | Une conversion et `1 km = 1000 m` | `/note` et `Carnet de notes` | La page de liste lit la base : elle prouve que l'application et PostgreSQL se parlent |

Rien n'a changé pour le mode worker, toujours absent : [docker.md](docker.md#pourquoi-le-mode-worker-est-désactivé) explique pourquoi.

## Les premières lignes

```yaml
name: CI
```

Le nom affiché dans l'onglet **Actions** de GitHub.

```yaml
on:
  push:
    branches: [main]
  pull_request:
```

Les déclencheurs : un `push` sur `main`, ou toute pull request. Une branche seule ne déclenche rien tant qu'elle n'a pas de pull request.

```yaml
permissions:
  contents: read
```

GitHub donne à chaque workflow un jeton, le `GITHUB_TOKEN`, pour agir sur le dépôt. Cette ligne le limite à la lecture du code, le seul droit nécessaire ici. C'est le **principe du moindre privilège** : si une action utilisée était compromise, elle ne pourrait ni pousser de code, ni modifier une release avec ce jeton.

## L'épinglage des actions par SHA

Chaque action est écrite ainsi :

```yaml
- uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1
```

Le long nombre est le **SHA complet** d'un commit de l'action, et le commentaire rappelle la version à laquelle il correspond.

Il serait plus court d'écrire `actions/checkout@v7`, mais un tag n'est qu'une étiquette : son propriétaire, ou un attaquant qui a pris le contrôle du dépôt de l'action, peut le déplacer vers un autre code. La CI exécuterait ce nouveau code sans que rien ne change dans ce dépôt.

Un SHA est une empreinte du contenu : il ne peut pas désigner un autre code. Ce qui a été vérifié est donc ce qui s'exécute, à chaque fois.

Le prix à payer : les mises à jour ne sont pas automatiques, et il faut changer le SHA avec son commentaire.

## `persist-credentials: false`

```yaml
- uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1
  with:
    persist-credentials: false
```

Par défaut, `actions/checkout` récupère le code **et** enregistre le `GITHUB_TOKEN` dans la configuration Git du dossier, `.git/config`, pour que les étapes suivantes puissent lancer `git push`. Le jeton reste alors sur le disque pendant tout le job : toute commande lancée ensuite peut le lire, y compris un script d'une dépendance installée par `composer install`.

Aucune étape de ce workflow n'a besoin de Git après le `checkout`. `persist-credentials: false` fait donc récupérer le code sans laisser le jeton derrière. Même limité à la lecture par `permissions`, un jeton qu'on ne laisse pas traîner ne peut pas fuiter.

Le job `docker` est concerné aussi : le bind mount `.:/app` rend tout le dossier visible dans le conteneur, `.git/` compris.

## Le job `quality`

Il vérifie le code directement sur un runner Ubuntu 24.04, sans Docker et **sans PostgreSQL**.

```yaml
quality:
  runs-on: ubuntu-24.04
  timeout-minutes: 10
```

`ubuntu-24.04` est une version nommée : contrairement à `ubuntu-latest`, elle ne change pas sans un changement dans ce dépôt. `timeout-minutes: 10` arrête le job au bout de 10 minutes. Sans cette ligne, GitHub attend jusqu'à 6 heures : une étape bloquée consommerait du temps de calcul pour rien. Les deux jobs ont ces deux réglages.

| Étape | Ce qu'elle fait | Si elle échoue |
|---|---|---|
| `actions/checkout` | Récupère le code du dépôt, sans garder le jeton | Problème de GitHub ou d'accès |
| Installer PHP | Installe PHP 8.5, les extensions `intl` et `pdo_sqlite`, et Composer | Version ou extension indisponible |
| Repérer le cache Composer | Trouve le dossier de cache de Composer | Composer n'est pas utilisable |
| Restaurer le cache Composer | Récupère les paquets déjà téléchargés | N'échoue pas en cas d'absence de cache |
| Valider composer.json | Lance `composer validate --strict` | `composer.json` invalide, ou `composer.lock` pas à jour |
| Installer les dépendances | Lance `composer install` | Paquet introuvable ou version de PHP trop ancienne |
| Linter la configuration YAML | Lance `lint:yaml config` | Erreur de syntaxe YAML dans `config/` |
| Linter les templates Twig | Lance `lint:twig templates` | Erreur de syntaxe dans un template |
| Linter le conteneur | Lance `lint:container` | Une valeur injectée dans un service n'a pas le bon type |
| Valider le mapping Doctrine | Lance `doctrine:schema:validate --skip-sync` | Un attribut `#[ORM\...]` incohérent |
| Lancer les tests | Lance `php bin/phpunit`, sur SQLite | Un test échoue |
| Auditer les dépendances | Lance `composer audit` | Une faille connue touche un paquet installé |

Un **linter** vérifie la forme d'un fichier sans l'exécuter. Il est rapide et donne l'endroit exact de l'erreur.

### Valider le mapping sans base

```yaml
- name: Valider le mapping Doctrine
  run: php bin/console doctrine:schema:validate --skip-sync
```

`doctrine:schema:validate` fait deux vérifications. La première, `Mapping`, contrôle que les attributs des entités sont cohérents entre eux : un type inconnu, une relation vers une classe absente. La seconde, `Database`, compare le mapping à la base réelle. `--skip-sync` saute la seconde :

```text
Mapping
-------
 [OK] The mapping files are correct.

Database
--------
 [SKIPPED] The database was not checked for synchronicity.
```

Cette commande tourne sans aucune base grâce à `serverVersion=16` dans `DATABASE_URL` : Doctrine connaît le moteur sans s'y connecter. Sans cette option, elle échouerait sur `Connection refused`, comme le montre [la fiche sur la connexion](formations/02-connexion-et-postgresql.md).

### Pourquoi `pdo_sqlite`

Les tests imposent SQLite, voir [la fiche sur les tests](formations/07-tests-avec-base-de-donnees.md). L'extension est souvent déjà présente sur les runners, mais la demander explicitement rend le besoin visible et évite une surprise si l'image du runner change.

### Le détail du cache

Télécharger les paquets à chaque exécution serait lent. Trois morceaux coopèrent :

```yaml
- name: Repérer le cache Composer
  id: composer-cache
  run: echo "dir=$(composer config cache-files-dir)" >> "$GITHUB_OUTPUT"
```

La commande demande à Composer où est son cache, et l'écrit dans `$GITHUB_OUTPUT`, le fichier spécial qui transmet une valeur aux étapes suivantes. L'`id` permet de la relire avec `steps.composer-cache.outputs.dir`.

```yaml
key: composer-${{ runner.os }}-${{ hashFiles('composer.lock') }}
restore-keys: composer-${{ runner.os }}-
```

La **clé** contient une empreinte de `composer.lock` : quand les dépendances changent, la clé change. `restore-keys` permet de partir d'un cache proche si la clé exacte n'existe pas.

### L'installation de PHP

`php-version: '8.5'` prend la dernière version disponible de cette branche. `extensions: intl, pdo_sqlite` et `tools: composer` ajoutent ce qui manque au runner. `coverage: none` désactive l'outil de couverture de code : PHP démarre plus vite. `composer install` échouerait avec une version de PHP inférieure à 8.5.12, le minimum fixé par [composer.json](../composer.json).

## Le job `docker`

Il vérifie que le site marche vraiment, dans les conteneurs décrits par [Dockerfile](../Dockerfile), [compose.yaml](../compose.yaml) et [compose.override.yaml](../compose.override.yaml), **avec PostgreSQL**. Les deux jobs tournent en parallèle, sur des machines séparées.

| Étape | Ce qu'elle fait | Si elle échoue |
|---|---|---|
| `actions/checkout` | Récupère le code du dépôt, sans garder le jeton | Problème de GitHub ou d'accès |
| Démarrer les conteneurs | Construit l'image, lance `app` et `database`, attend que la base soit prête | Erreur dans le `Dockerfile`, ou PostgreSQL ne démarre pas |
| Appliquer les migrations | Joue les migrations sur la base neuve | SQL de migration invalide pour PostgreSQL |
| Vérifier le schéma contre PostgreSQL | Lance `doctrine:schema:validate` puis `doctrine:migrations:up-to-date` | Une migration manque ou ne correspond pas à l'entité |
| Vérifier que la liste répond | Appelle `/note` et cherche `Carnet de notes` | Le serveur ne répond pas, ou la page plante |
| Lancer les tests dans le conteneur | Lance PHPUnit dans le conteneur `app`, sur SQLite | Différence entre l'image et le runner, ou tests sur la mauvaise base |
| Arrêter les conteneurs | Supprime conteneurs et volumes | Sans effet sur le code |

Les explications de Docker se trouvent dans [docker.md](docker.md).

### Le démarrage avec `--wait`

```bash
docker compose up -d --build --wait
```

`--wait` ne rend la main que lorsque les services sont prêts : pour `database`, quand son `healthcheck` répond. Sans lui, l'étape suivante lancerait la migration alors que PostgreSQL s'initialise encore, et échouerait au hasard. Le service `app` n'ayant pas de healthcheck, `--wait` le considère prêt dès que son conteneur tourne.

### La migration et le schéma

```bash
docker compose exec -T app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec -T app php bin/console doctrine:schema:validate
docker compose exec -T app php bin/console doctrine:migrations:up-to-date
```

C'est la seule vérification de la migration dans tout le projet. La base est neuve : `migrate` joue donc toute l'histoire du schéma, comme le ferait une nouvelle installation. `--no-interaction` répond oui à la confirmation.

Ensuite, `doctrine:schema:validate`, cette fois **sans** `--skip-sync`, compare la table créée par la migration au mapping de l'entité. Si quelqu'un modifiait `Note` sans générer de migration, la table et l'entité ne correspondraient plus, et cette commande échouerait. `doctrine:migrations:up-to-date` confirme enfin qu'aucune migration n'est restée en attente.

`exec` lance une commande dans le conteneur démarré. `-T` désactive le terminal interactif, qui n'existe pas en CI. Dans le conteneur, `DATABASE_URL` désigne le service `database` : ces commandes parlent bien à PostgreSQL.

### La vérification de la liste

```bash
body=$(curl --retry 10 --retry-delay 2 --retry-all-errors --max-time 10 -fsS 'http://localhost:8080/note')
grep -q 'Carnet de notes' <<<"$body"
```

`/note` interroge la table `note` à chaque affichage. Si la connexion à PostgreSQL ou la table manquait, la page répondrait par une erreur 500, et `-f` ferait échouer `curl`. Le texte cherché est le titre de la page, présent que la table soit vide ou non.

Les options de `curl` :

| Option | Rôle |
|---|---|
| `--retry 10` | Réessayer jusqu'à 10 fois |
| `--retry-delay 2` | Attendre 2 secondes entre deux essais |
| `--retry-all-errors` | Réessayer quelle que soit l'erreur |
| `--max-time 10` | Abandonner un essai qui dure plus de 10 secondes |
| `-f` | Échouer si le code HTTP indique une erreur, une 500 par exemple |
| `-s` | Masquer la barre de progression |
| `-S` | Garder quand même les messages d'erreur |

`--retry-all-errors` est nécessaire parce que le serveur peut renvoyer une réponse vide au démarrage. [docker.md](docker.md#pourquoi-la-page-peut-répondre-vide-au-démarrage) explique pourquoi.

### Pourquoi `body=$(curl ...)` et pas `curl ... | grep`

La forme la plus courante serait `curl ... | grep -q 'Carnet de notes'`. Elle pose deux problèmes.

1. **Le statut d'un pipeline est celui de la dernière commande.** GitHub lance ces lignes avec `bash -e`, sans l'option `pipefail`. Un échec de `curl` ne ferait pas échouer l'étape par lui-même : seul le résultat de `grep` compterait, et le vrai message d'erreur serait noyé.
2. **`grep -q` s'arrête au premier résultat trouvé** et ferme le tuyau. Si `curl` est encore en train d'écrire, il échoue. Avec `pipefail`, un site qui fonctionne donnerait donc parfois un échec.

La forme choisie sépare les deux temps :

- `body=$(...)` attend la réponse complète et la range dans une variable. Le statut de cette ligne est celui de `curl` : s'il échoue, `bash -e` arrête l'étape sur l'erreur réelle.
- `<<<"$body"` donne le contenu de la variable à `grep` en entrée. Les guillemets gardent le texte intact.

### Les tests dans le conteneur

```bash
docker compose exec -T app php bin/phpunit
```

Les mêmes tests que dans `quality`, mais dans l'image, avec PHP sans `php.ini` et une vraie variable `DATABASE_URL` vers PostgreSQL. C'est exactement la situation où les tests se sont un jour retrouvés sur PostgreSQL. `DatabaseConfigTest` échouerait si cela se reproduisait : [la fiche sur les tests](formations/07-tests-avec-base-de-donnees.md) raconte l'histoire.

```yaml
- name: Arrêter les conteneurs
  if: always()
  run: docker compose down -v
```

Après une étape en échec, les suivantes sont normalement ignorées. `if: always()` fait exécuter celle-ci quoi qu'il arrive, pour que le nettoyage ait lieu.

## Pourquoi `quality` et `docker` n'utilisent pas la même base

| | Job `quality` | Job `docker` |
|---|---|---|
| Base pour les tests | SQLite | SQLite |
| Base pour l'application | Aucune | PostgreSQL 16 |
| Ce qu'il prouve | Le code, la configuration et les tests, vite | La migration, le schéma et la page, sur le vrai moteur |

Les deux jobs lancent les tests sur SQLite : c'est ce que veut [phpunit.dist.xml](../phpunit.dist.xml), partout. Ils diffèrent par ce qui tourne **autour** des tests.

`quality` n'a pas de PostgreSQL. Il reste rapide et simple, et vérifie tout ce qui peut l'être sans base, mapping compris grâce à `--skip-sync`.

`docker` démarre PostgreSQL parce que c'est le seul moyen de vérifier ce que SQLite ne peut pas prouver : que la migration, écrite en SQL PostgreSQL, s'applique, et que la table obtenue correspond à l'entité. Il vérifie aussi que l'application, dans son conteneur, parle bien à la base, par la page `/note`.

Un service PostgreSQL dans `quality`, pour faire tourner les tests eux-mêmes sur PostgreSQL, arrive au palier Junior.

## Lire un échec

1. Sur GitHub, ouvrez l'onglet **Actions**, ou le lien **Details** de la pull request.
2. Ouvrez le job en rouge, `quality` ou `docker`.
3. Dépliez l'étape en rouge : c'est la première qui a échoué. Les suivantes sont ignorées ou sans intérêt.
4. Lisez la fin de son journal, puis remontez jusqu'à la première erreur.
5. Reproduisez la commande sur votre machine.

| Étape en échec | Commande à relancer en local |
|---|---|
| Valider composer.json | `composer validate --strict` |
| Linter la configuration YAML | `php bin/console lint:yaml config` |
| Linter les templates Twig | `php bin/console lint:twig templates` |
| Linter le conteneur | `php bin/console lint:container` |
| Valider le mapping Doctrine | `php bin/console doctrine:schema:validate --skip-sync` |
| Lancer les tests | `php bin/phpunit` |
| Auditer les dépendances | `composer audit` |
| Démarrer les conteneurs | `docker compose up -d --build --wait` |
| Appliquer les migrations | `docker compose exec app php bin/console doctrine:migrations:migrate` |
| Vérifier le schéma contre PostgreSQL | `docker compose exec app php bin/console doctrine:schema:validate` |
| Vérifier que la liste répond | `curl -i http://localhost:8080/note` |
| Lancer les tests dans le conteneur | `docker compose exec app php bin/phpunit` |

Si `Lancer les tests` passe mais que le test du conteneur échoue, ou l'inverse, la différence vient de l'environnement : le volume `/app/vendor` périmé, ou une variable d'environnement du conteneur.

## Ce qui viendra plus tard

Ce niveau vérifie le code, sans rien publier. Une base de données dans le job `quality` et la couverture de code arrivent au palier Junior, et la publication d'une image au palier Junior++.
