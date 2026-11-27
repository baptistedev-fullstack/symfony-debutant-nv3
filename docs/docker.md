# Docker

## L'essentiel

**Docker** fait tourner une application dans un **conteneur**, un environnement isolé qui contient tout ce qu'il lui faut. Ce niveau en lance deux : `app`, avec PHP et le serveur web, et `database`, avec PostgreSQL. Vous n'avez donc à installer ni la bonne version de PHP ni PostgreSQL, et tout tourne pareil sur toutes les machines.

| Terme | Sens |
|---|---|
| Image | Un modèle figé, construit à partir d'un `Dockerfile` ou téléchargé |
| Conteneur | Une image en cours d'exécution |
| Service | Un conteneur décrit dans `compose.yaml`, avec son nom |
| Couche | Une étape de construction, gardée en cache |
| Volume | Un espace de stockage qui survit au conteneur |
| Bind mount | Un dossier de votre machine rendu visible dans le conteneur |
| Healthcheck | Une commande qui dit si le service est prêt |

## Ce qui a changé depuis Debutant-Nv2

| Fichier | Changement | Pourquoi |
|---|---|---|
| [Dockerfile](../Dockerfile) | `pdo_pgsql` ajouté à `install-php-extensions` | PHP a besoin de ce pilote pour parler à PostgreSQL |
| [compose.yaml](../compose.yaml) | Un second service, `database`, venu de la recette Flex de Doctrine | La base de données |
| [compose.yaml](../compose.yaml) | `environment` et `depends_on` sur le service `app` | Dire à l'application où est la base, et attendre qu'elle soit prête |
| [compose.override.yaml](../compose.override.yaml) | Nouveau fichier, venu de la même recette | Publier le port de la base, sur votre machine seulement |
| [.dockerignore](../.dockerignore) | Inchangé | |

Le mode worker reste désactivé, pour les mêmes raisons qu'au niveau précédent : voir [plus bas](#pourquoi-le-mode-worker-est-désactivé).

## Usage

```bash
docker compose up -d --build --wait
docker compose exec app php bin/console doctrine:migrations:migrate
```

La première ligne construit l'image si besoin et démarre les deux services en arrière-plan. `-d` rend la main, `--build` reconstruit l'image, `--wait` attend que les services soient prêts. La seconde crée la table `note` dans PostgreSQL ; elle demande une confirmation, à laquelle on répond `yes`. Le site répond ensuite sur <http://localhost:8080>, qui redirige vers la liste des notes.

```bash
docker compose ps
docker compose logs app
docker compose logs database
docker compose exec app php bin/phpunit
docker compose exec database psql -U app -d app
docker compose down
docker compose down -v
```

Dans l'ordre : voir les conteneurs, lire les journaux de chaque service, lancer les tests dans le conteneur `app`, ouvrir `psql` dans le conteneur `database`, tout arrêter, tout arrêter en supprimant aussi les volumes, **base de données comprise**.

## Une image de développement uniquement

**Cette image sert au développement local. Elle ne doit être ni exposée sur Internet ni publiée.**

- Elle tourne en `APP_ENV=dev`, la valeur par défaut de [.env](../.env), que ni le `Dockerfile` ni `compose.yaml` ne changent.
- Elle contient les dépendances de développement : `composer install` n'a pas l'option `--no-dev`.
- Le débogage est actif : une erreur affiche des pages détaillées, avec du code et des informations internes.
- Le mot de passe de la base, `!ChangeMe!`, est écrit en clair dans un fichier public. C'est une valeur d'exemple de la recette, connue de tous.

Tout cela aide à apprendre, et serait dangereux sur un serveur public. La production, avec son image dédiée, arrive à Junior++Nv5.

## Pourquoi FrankenPHP

**FrankenPHP** est un serveur d'applications PHP construit sur le serveur web **Caddy**. Il tient en un seul conteneur, là où une installation classique en demande deux, avec Nginx et PHP-FPM. Il n'y a donc pas de serveur séparé à configurer, ce qui fait gagner du temps en formation et en test.

Sa configuration par défaut suffit : l'image contient `/etc/frankenphp/Caddyfile`, qui prend `public/` pour racine et envoie à PHP les requêtes qui ne correspondent pas à un fichier. C'est exactement le [contrôleur frontal](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/01-http-et-requete.md).

## Le `Dockerfile`, ligne par ligne

Fichier : [Dockerfile](../Dockerfile). Il ne décrit que l'image du service `app` : celle de PostgreSQL est téléchargée telle quelle.

```dockerfile
FROM dunglas/frankenphp:1.13.5-php8.5.12-trixie
```

L'image de départ. Le tag précise FrankenPHP 1.13.5, PHP 8.5.12 et Debian 13 `trixie`. Une version exacte évite qu'une reconstruction change PHP sans prévenir. Elle garantit aussi le minimum de 8.5.12, voir [versions.md](versions.md).

```dockerfile
RUN install-php-extensions @composer intl zip pdo_pgsql
```

`install-php-extensions` est un outil fourni par l'image.

- `@composer` installe Composer.
- `intl` apporte les fonctions d'internationalisation.
- `zip` permet à Composer de décompresser les paquets.
- `pdo_pgsql` est **la nouveauté de ce niveau** : le pilote qui permet à PHP de se connecter à PostgreSQL. Sans lui, Doctrine échouerait avec un message `could not find driver`.

Deux extensions sont déjà intégrées à l'image et n'ont pas besoin d'être demandées : OPcache, qui garde le code PHP compilé en mémoire, et `pdo_sqlite`, le pilote SQLite utilisé par les tests. `php -m` dans le conteneur liste les deux.

```dockerfile
ENV SERVER_NAME=:80
```

L'adresse sur laquelle Caddy écoute. Sans cette ligne, le Caddyfile utilise `localhost` et Caddy gère le HTTPS. Avec `:80`, il écoute en HTTP simple sur le port 80, pour n'importe quel nom de domaine. C'est ce qu'il faut en local.

```dockerfile
WORKDIR /app
```

Le dossier de travail de l'image : les commandes suivantes s'y exécutent. Le Caddyfile cherche `public/` à partir de lui.

```dockerfile
COPY . /app
```

Copie le projet dans l'image, sauf ce qu'exclut [.dockerignore](../.dockerignore).

```dockerfile
RUN composer install --no-interaction --prefer-dist
```

Installe les dépendances de `composer.lock`. `--no-interaction` interdit toute question : personne n'est là pour répondre. `--prefer-dist` télécharge des archives plutôt que des dépôts Git.

Cette étape ne touche pas la base : Composer lance `cache:clear`, qui n'ouvre aucune connexion. L'image se construit donc sans que PostgreSQL tourne.

### Le prix de `COPY` avant `composer install`

Docker met chaque couche en cache. Il réutilise une couche tant que ce qu'elle a reçu n'a pas changé, et dès qu'une couche change, toutes les suivantes sont refaites.

`COPY . /app` reçoit tout le projet. Le moindre fichier modifié invalide donc cette couche, et `composer install`, placé juste après, réinstalle toutes les dépendances à chaque construction. Le cache des couches ne sert à rien pour cette étape.

On le garde parce que cela rend le `Dockerfile` très simple à ce niveau. La construction classique copie d'abord `composer.json` et `composer.lock`, installe, puis copie le reste. Ce sera à sa place avec l'image de production.

## `compose.yaml`, ligne par ligne

Fichier : [compose.yaml](../compose.yaml). **Docker Compose** décrit dans un fichier comment lancer les conteneurs. Ce fichier a deux auteurs : le service `app` est écrit pour ce projet, et tout ce qui se trouve entre les commentaires `###> doctrine/doctrine-bundle ###` et `###< doctrine/doctrine-bundle ###` a été ajouté par la recette Flex de Doctrine, lors du `composer require symfony/orm-pack`. Flex se sert de ces commentaires pour retrouver et mettre à jour son bloc : ne les supprimez pas.

### Le service `app`

```yaml
services:
  app:
    build: .
```

Le service de l'application, construit à partir du `Dockerfile` du dossier courant.

```yaml
    ports:
      - "127.0.0.1:8080:80"
```

Publie un port du conteneur sur votre machine, au format `adresse:port_machine:port_conteneur`.

- `80` : le port où Caddy écoute dans le conteneur.
- `8080` : le port à utiliser sur votre machine.
- `127.0.0.1` : l'adresse locale. Seule votre machine peut joindre le site.

Écrire simplement `8080:80` ouvrirait le port sur toutes les interfaces réseau, donc à toute personne sur le même réseau. Pour une image avec le débogage actif, ce serait une mauvaise idée.

```yaml
    environment:
      DATABASE_URL: "postgresql://${POSTGRES_USER:-app}:${POSTGRES_PASSWORD:-!ChangeMe!}@database:5432/${POSTGRES_DB:-app}?serverVersion=${POSTGRES_VERSION:-16}&charset=utf8"
```

Définit une **vraie variable d'environnement** dans le conteneur `app`. Elle remplace la valeur de `DATABASE_URL` écrite dans [.env](../.env), parce qu'une vraie variable l'emporte toujours sur les fichiers `.env`.

Deux différences avec `.env` :

- **L'hôte est `database`, pas `127.0.0.1`.** Dans un conteneur, `127.0.0.1` désigne le conteneur lui-même, où PostgreSQL ne tourne pas. Compose crée un réseau commun aux deux services, où chaque service est joignable par son nom : `database` désigne donc le conteneur de la base.
- **Le port est `5432`**, celui où PostgreSQL écoute dans son conteneur. Le port publié sur votre machine ne sert pas ici : les deux conteneurs se parlent directement.

La syntaxe `${POSTGRES_USER:-app}` veut dire « la variable `POSTGRES_USER` de votre shell si elle existe, sinon `app` ». Ce sont les mêmes valeurs par défaut que dans le service `database` : si vous en changez une avant `docker compose up`, les deux services restent d'accord. La [fiche sur la connexion](formations/02-connexion-et-postgresql.md) découpe cette URL morceau par morceau.

Cette variable a une conséquence inattendue sur les tests lancés dans le conteneur. [La fiche 7](formations/07-tests-avec-base-de-donnees.md) la raconte : c'est la meilleure leçon de ce niveau.

```yaml
    depends_on:
      database:
        condition: service_healthy
```

Démarre `app` seulement quand `database` est **en bonne santé**, au sens de son `healthcheck` décrit plus bas. Sans `condition`, Compose attendrait seulement que le conteneur de la base soit lancé, pas que PostgreSQL accepte les connexions, et une migration lancée trop tôt échouerait.

```yaml
    volumes:
      - .:/app
```

Le **bind mount** : le dossier du projet apparaît dans le conteneur à la place de `/app`. Quand vous modifiez un fichier, le conteneur le voit tout de suite, sans reconstruction.

Attention à une conséquence : **ce montage rend visible tout le dossier, y compris ce que `.dockerignore` exclut**. Un fichier `.env.local` sur votre machine n'est pas dans l'image, mais il est lu par le conteneur en cours d'exécution. De même, `docs/` et `var/` y sont visibles. `.dockerignore` protège l'image, pas le conteneur lancé avec ce montage.

Autre risque connu de ce montage : sur un hôte Linux, le conteneur s'exécute en `root`, donc `var/` et `.phpunit.cache` peuvent appartenir à `root` sur votre machine et demander un `sudo chown` pour être supprimés. Le fichier de la base de test, `var/test.db`, est dans le même cas.

```yaml
      - /app/vendor
```

Un **volume anonyme**, sans nom, placé sur `/app/vendor`. Il existe à cause de la ligne précédente : le bind mount masque l'image entière, y compris le `vendor/` installé pendant la construction. Le `vendor/` utilisé serait alors celui de votre machine, peut-être absent ou installé pour un autre système. Ce volume remet par-dessus une copie du `vendor/` de l'image, créée à son premier démarrage.

**Le piège** : ce volume survit aux redémarrages et aux reconstructions. Après un changement de `composer.lock`, l'image a de nouvelles dépendances, mais le conteneur utilise encore l'ancien `vendor/`, devenu périmé. La solution :

```bash
docker compose down -v
docker compose up -d --build --wait
```

`-v` supprime les volumes, et le prochain démarrage recopie le `vendor/` à jour. À ce niveau, `-v` supprime aussi la base : il faut relancer la migration.

```yaml
      - caddy_data:/data
      - caddy_config:/config
```

Deux **volumes nommés**, déclarés en bas du fichier, pour les données et la configuration de Caddy. L'image place ces données dans `/data` et `/config`, à l'aide des variables `XDG_DATA_HOME` et `XDG_CONFIG_HOME`. C'est l'usage prévu par FrankenPHP : ces données survivent à la recréation du conteneur.

### Le service `database`, venu de la recette

```yaml
  database:
    image: postgres:${POSTGRES_VERSION:-16}-alpine
```

L'image officielle de PostgreSQL, version 16, dans sa variante `alpine`, bâtie sur Alpine Linux et beaucoup plus petite que la variante Debian. Il n'y a pas de `build` : l'image est téléchargée telle quelle. Le numéro `16` doit rester égal au `serverVersion` des URL, voir [versions.md](versions.md#postgresql--un-numéro-majeur-seulement).

```yaml
    environment:
      POSTGRES_DB: ${POSTGRES_DB:-app}
      # You should definitely change the password in production
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD:-!ChangeMe!}
      POSTGRES_USER: ${POSTGRES_USER:-app}
```

Au **premier** démarrage, sur un volume vide, l'image PostgreSQL lit ces trois variables pour créer la base `app` et l'utilisateur `app` avec son mot de passe. Aux démarrages suivants, elle les ignore : la base existe déjà dans le volume. Changer le mot de passe ici n'a donc d'effet qu'après un `docker compose down -v`.

Le commentaire de la recette, en anglais, dit qu'il faut changer ce mot de passe en production. `!ChangeMe!` est une valeur publique, présente dans toutes les applications créées avec cette recette. Elle ne protège rien : ce qui protège la base ici, c'est que son port n'est ouvert qu'à votre machine, comme le montre `compose.override.yaml` plus bas.

```yaml
    healthcheck:
      test: ["CMD", "pg_isready", "-d", "${POSTGRES_DB:-app}", "-U", "${POSTGRES_USER:-app}"]
      timeout: 5s
      retries: 5
      start_period: 60s
```

Le **healthcheck** : Docker lance régulièrement `pg_isready` dans le conteneur, un petit outil de PostgreSQL qui répond « prêt » quand le serveur accepte les connexions.

| Ligne | Sens |
|---|---|
| `test` | La commande à lancer. `CMD` veut dire : l'exécuter directement, sans shell |
| `timeout: 5s` | Un essai qui dure plus de 5 secondes compte comme un échec |
| `retries: 5` | Après 5 échecs de suite, le service est déclaré en mauvaise santé (`unhealthy`) |
| `start_period: 60s` | Pendant la première minute, les échecs ne comptent pas : PostgreSQL a le temps de s'initialiser |

L'intervalle entre deux essais n'est pas précisé : Docker applique sa valeur par défaut.

C'est ce healthcheck qu'attendent `depends_on: condition: service_healthy` et `docker compose up --wait`.

```yaml
    volumes:
      - database_data:/var/lib/postgresql/data:rw
      # You may use a bind-mounted host directory instead, so that it is harder to accidentally remove the volume and lose all your data!
      # - ./docker/db/data:/var/lib/postgresql/data:rw
```

Le volume nommé `database_data` reçoit les fichiers de PostgreSQL, rangés dans `/var/lib/postgresql/data`. `rw` veut dire lecture et écriture. Grâce à lui, vos notes survivent à `docker compose down` et à la recréation du conteneur. Seul `docker compose down -v` les efface.

Le commentaire propose une autre solution : un bind mount vers un dossier de votre machine, plus difficile à supprimer par erreur. Ce projet garde le volume nommé de la recette : les données d'un carnet d'exercice ne valent pas d'encombrer le dépôt d'un dossier de base.

### Les volumes nommés

```yaml
volumes:
###> doctrine/doctrine-bundle ###
  database_data:
###< doctrine/doctrine-bundle ###
  caddy_data:
  caddy_config:
```

La déclaration des trois volumes nommés. Docker les crée au premier démarrage, avec un nom préfixé par celui du projet, par exemple `debutant-nv3_database_data`. `docker volume ls` les liste.

## `compose.override.yaml`, ligne par ligne

Fichier : [compose.override.yaml](../compose.override.yaml). Docker Compose lit **automatiquement** ce fichier après `compose.yaml` et fusionne les deux. On y range ce qui ne sert qu'en développement. Il a été créé par la même recette Flex.

```yaml
services:
###> doctrine/doctrine-bundle ###
  database:
    ports:
      - "127.0.0.1::5432"
###< doctrine/doctrine-bundle ###
```

Publie le port `5432` de PostgreSQL sur votre machine. Le format est le même que pour `app`, mais **le port de la machine est vide** : entre les deux `:`, il n'y a rien. Docker choisit alors un port libre au hasard, différent à chaque création du conteneur. Il évite ainsi un conflit avec un PostgreSQL déjà installé sur votre machine, qui occuperait le port 5432.

La recette écrit `"5432"` seul, ce qui publierait le port sur toutes les interfaces réseau. Ce projet l'a modifié en `"127.0.0.1::5432"` : seule votre machine peut joindre la base. Avec un mot de passe public, c'est indispensable.

Pour connaître le port choisi :

```bash
docker compose port database 5432
```

```text
127.0.0.1:49794
```

Le nombre change d'un démarrage à l'autre. La Symfony CLI le lit pour vous, ce qui explique pourquoi on lance `symfony console` et non `php bin/console` sur votre machine : voir [la fiche sur la connexion](formations/02-connexion-et-postgresql.md).

Le service `app` n'a pas besoin de ce port : il joint la base par le réseau de Compose, sous le nom `database`.

## `.dockerignore`, ligne par ligne

Fichier : [.dockerignore](../.dockerignore). Il liste ce que `COPY . /app` ne doit pas emporter. Il est identique à celui du niveau précédent.

| Ligne | Pourquoi l'exclure |
|---|---|
| `.git` | L'historique est inutile dans l'image |
| `.github` | La CI ne sert pas à l'exécution |
| `.env.local` | Des réglages privés de votre machine |
| `.env.local.php` | La version compilée de ces réglages |
| `.env.*.local` | Des réglages privés par environnement |
| `config/secrets/**/*.decrypt.private.php` | Les clés privées du coffre-fort de Symfony |
| `var` | Le cache, les journaux et la base de test de votre machine |
| `vendor` | Il est recréé par `composer install` dans l'image |
| `docs` | La documentation ne sert pas à faire tourner le site |
| `.DS_Store` | Un fichier technique de macOS |

Une image peut être partagée et chacune de ses couches peut être lue : un secret qui y entre en sort difficilement. Les tests de la documentation passent quand même dans le conteneur, parce que le bind mount y rend `docs/` visible.

## Pourquoi le mode worker est désactivé

FrankenPHP a deux façons de faire tourner PHP :

- **Mode classique** : à chaque requête, l'application démarre et s'arrête. Rien ne reste d'une requête à l'autre.
- **Mode worker** : l'application démarre une seule fois, reste en mémoire et traite les requêtes à la suite. C'est beaucoup plus rapide.

Ce projet utilise le mode classique : dans le Caddyfile de l'image, la ligne `worker` est commentée et aucune variable n'active ce mode.

La raison principale est l'**état partagé**. En mode worker, les objets restent en mémoire entre deux requêtes. Ce niveau en donne un exemple concret : l'`EntityManager` de Doctrine garde en mémoire les entités qu'il a chargées, comme l'explique [la fiche 4](formations/04-entity-manager-et-repository.md). Il faut le vider entre deux requêtes, et la connexion à la base peut se fermer pendant que le worker attend. Symfony et Doctrine savent gérer cela, mais c'est trop tôt à ce niveau, où l'on découvre tout juste la base de données.

Il y a une seconde raison : un code qui reste en mémoire doit être rechargé ou redémarré pour qu'un changement soit pris en compte, alors qu'en mode classique, il suffit de recharger la page.

## Pourquoi la page peut répondre vide au démarrage

`--wait` attend le healthcheck de `database`. Le service `app`, lui, n'a pas de healthcheck : `--wait` le considère prêt dès que son conteneur tourne, pas quand FrankenPHP écoute. Juste après le démarrage, un premier appel peut donc donner ceci :

```text
curl: (52) Empty reply from server
```

Ce n'est pas un bug du site. Docker publie déjà le port, donc la connexion est acceptée, puis elle se ferme sans réponse, car FrankenPHP n'écoute pas encore dans le conteneur. Quelques instants plus tard, tout fonctionne.

C'est pour cela que la [CI](ci-cd.md) lance `curl` avec `--retry-all-errors`. L'option `--retry` seule ne réessaie que certaines erreurs dites transitoires, comme un délai dépassé ou un code 503. Une réponse vide n'en fait pas partie : `curl` s'arrêterait au premier essai et la CI échouerait au hasard. `--retry-all-errors` réessaie quelle que soit l'erreur.

La première vraie réponse peut aussi être un peu lente : Symfony compile alors son cache `dev`.

## Pannes fréquentes

| Symptôme | Cause probable | Solution |
|---|---|---|
| `Empty reply from server` juste après le démarrage | Le serveur démarre encore | Réessayer après quelques secondes |
| `port is already allocated` | Le port 8080 est déjà pris | Arrêter l'autre programme |
| `relation "note" does not exist` sur la page | La migration n'a pas été jouée | `docker compose exec app php bin/console doctrine:migrations:migrate` |
| `Connection refused` sur le port 5432 avec `php bin/console` | Le port de la base sur votre machine n'est pas 5432 | Utiliser `symfony console`, voir [la fiche 2](formations/02-connexion-et-postgresql.md) |
| `dependency failed to start: container ... is unhealthy` | PostgreSQL n'a pas démarré | `docker compose logs database` |
| Le mot de passe changé dans `compose.yaml` est refusé | Le volume contient encore l'ancienne base | `docker compose down -v`, puis redémarrer et migrer |
| Classe introuvable après un changement de `composer.lock` | Le volume `/app/vendor` est périmé | `docker compose down -v`, puis `docker compose up -d --build --wait` |
| Un réglage inattendu apparaît | Un fichier `.env.local` est lu grâce au bind mount | Vérifier ce fichier sur votre machine |
