# 5. Le CRUD généré

## Ce que c'est

**CRUD** désigne les quatre opérations de base sur une donnée : _Create_, _Read_, _Update_, _Delete_, soit créer, lire, modifier et supprimer. Presque toutes les applications en ont, pour presque chaque entité.

`make:crud` génère d'un coup tout ce qu'il faut pour une entité : un contrôleur avec cinq actions, un formulaire, six templates et un test. Ce projet l'a lancé sur `Note`, puis a modifié le résultat.

## Pourquoi ça existe

Écrire un CRUD à la main, c'est toujours le même code : une liste, une page de détail, un formulaire de création, le même pour la modification, un bouton de suppression. `make:crud` l'écrit en quelques secondes, selon les conventions de Symfony.

Le code généré est un **point de départ**, pas un produit fini. Il est en anglais, affiche toutes les colonnes, met les dates dans le formulaire et ne dit rien à l'utilisateur après une action. La moitié de cette fiche explique ce qui a été changé, et pourquoi.

## Où le voir dans ce projet

- [src/Controller/NoteController.php](../../src/Controller/NoteController.php) : les cinq actions.
- [src/Form/NoteType.php](../../src/Form/NoteType.php) : le formulaire.
- [templates/note/index.html.twig](../../templates/note/index.html.twig), [show.html.twig](../../templates/note/show.html.twig), [new.html.twig](../../templates/note/new.html.twig), [edit.html.twig](../../templates/note/edit.html.twig) : les quatre pages.
- [templates/note/_form.html.twig](../../templates/note/_form.html.twig) et [_delete_form.html.twig](../../templates/note/_delete_form.html.twig) : les deux morceaux partagés.
- [templates/base.html.twig](../../templates/base.html.twig) : l'affichage des messages flash.
- [config/routes.yaml](../../config/routes.yaml) : la redirection de `/`.
- [config/packages/framework.yaml](../../config/packages/framework.yaml) : la session.
- [tests/Controller/NoteControllerTest.php](../../tests/Controller/NoteControllerTest.php) : chaque action, testée.

## Comment lire ce code

### Ce que `make:crud` a produit

| Fichier | Contenu généré |
|---|---|
| `src/Controller/NoteController.php` | Cinq actions : `index`, `new`, `show`, `edit`, `delete` |
| `src/Form/NoteType.php` | Un formulaire avec un champ par propriété de `Note`, sauf `id` |
| `templates/note/index.html.twig` | Un tableau de toutes les notes, toutes colonnes |
| `templates/note/show.html.twig` | Le détail d'une note |
| `templates/note/new.html.twig` | La page de création |
| `templates/note/edit.html.twig` | La page de modification |
| `templates/note/_form.html.twig` | Le formulaire, partagé par `new` et `edit` |
| `templates/note/_delete_form.html.twig` | Le bouton de suppression, partagé par `show` et `edit` |
| `tests/Controller/NoteControllerTest.php` | Un test par action |

Les fichiers qui commencent par `_` sont des **morceaux** de template, inclus par d'autres avec `include()`. Le `_` est une convention, pas une règle de Twig.

### Les cinq routes

Le contrôleur porte `#[Route('/note')]` sur la classe : ce préfixe s'ajoute à toutes ses routes.

```bash
php bin/console debug:router
```

```text
  app_note_index    GET        /note
  app_note_new      GET|POST   /note/new
  app_note_show     GET        /note/{id}
  app_note_edit     GET|POST   /note/{id}/edit
  app_note_delete   POST       /note/{id}
  app_home          ANY        /
```

| Route | Action | `GET` | `POST` |
|---|---|---|---|
| `app_note_index` | `index()` | Affiche la liste | 405 |
| `app_note_new` | `new()` | Affiche le formulaire vide | Traite le formulaire envoyé |
| `app_note_show` | `show()` | Affiche une note | Voir `app_note_delete` |
| `app_note_edit` | `edit()` | Affiche le formulaire rempli | Traite le formulaire envoyé |
| `app_note_delete` | `delete()` | Voir `app_note_show` | Supprime la note |

`app_note_show` et `app_note_delete` ont le **même chemin**, `/note/{id}`. C'est la méthode HTTP qui les départage : `GET` affiche, `POST` supprime. Un lien, qui fait toujours un `GET`, ne peut donc jamais supprimer une note. `testDeleteWithGetIsNotAllowed()` le vérifie : un `GET /note/{id}` affiche la note et la laisse en base. Une autre méthode, comme `PUT /note/3`, ne correspond à aucune des deux et reçoit une 405, comme vu au [premier niveau](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/03-routage.md).

`new()` et `edit()` acceptent `GET` et `POST` sur la même URL : le même code affiche le formulaire, puis le traite quand il revient.

### Le formulaire, en aperçu

Le composant **Form** est installé parce que `make:crud` l'exige. Ce niveau n'en utilise que le strict nécessaire ; il sera étudié pour lui-même au niveau suivant, `Debutant-Nv4`.

[src/Form/NoteType.php](../../src/Form/NoteType.php) décrit les champs :

```php
$builder
    ->add('title', null, ['label' => 'Titre'])
    ->add('content', TextareaType::class, ['label' => 'Contenu'])
;
```

```php
$resolver->setDefaults([
    'data_class' => Note::class,
]);
```

`data_class` relie le formulaire à l'entité : chaque champ lit et écrit la propriété du même nom. Pour `title`, le type est `null` : Symfony le **devine** d'après Doctrine et le Validator. Il choisit un champ texte, et ajoute même `required` et `maxlength="120"` au HTML.

Le cycle, dans `new()` :

```php
$form = $this->createForm(NoteType::class, $note);
$form->handleRequest($request);

if ($form->isSubmitted() && $form->isValid()) {
```

1. `createForm()` construit le formulaire autour de la note vide.
2. `handleRequest()` regarde la requête. En `GET`, il ne fait rien. En `POST`, il lit `note[title]` et `note[content]`, puis appelle `setTitle()` et `setContent()`.
3. `isSubmitted()` dit si le formulaire a été envoyé.
4. `isValid()` lance la validation, voir [la fiche 6](06-validation-et-csrf.md).

Côté template, [templates/note/_form.html.twig](../../templates/note/_form.html.twig) tient en quatre lignes : `form_start()` ouvre la balise `<form>`, `form_widget()` écrit tous les champs avec leurs libellés et leurs erreurs, y compris le champ caché `note[_token]` du jeton CSRF, et `form_end()` ferme la balise. Si un champ avait été oublié, `form_end()` l'écrirait avant de fermer.

Le niveau précédent avait choisi un formulaire `GET` écrit à la main, sans ce composant : [sa fiche](https://github.com/baptistedev-fullstack/symfony-debutant-nv2/blob/main/docs/formations/05-formulaire-get-et-parametres.md) explique pourquoi. Ici, le formulaire modifie des données : il passe en `POST`, et Form apporte la liaison à l'entité, la validation et le jeton CSRF.

### Post/Redirect/Get, avec un 303

Après un enregistrement réussi, le contrôleur n'affiche pas de page. Il **redirige** :

```php
return $this->redirectToRoute('app_note_index', [], Response::HTTP_SEE_OTHER);
```

C'est le motif **Post/Redirect/Get** :

1. Le navigateur envoie le formulaire en `POST`.
2. Le serveur enregistre, puis répond par une redirection vers `/note`.
3. Le navigateur suit la redirection avec un `GET`, et affiche la liste.

Sans lui, la page affichée après l'enregistrement serait la réponse d'un `POST`. Recharger cette page renverrait le formulaire, et créerait une seconde note identique. Avec la redirection, la dernière requête est un `GET` : recharger ne fait que réafficher la liste.

`Response::HTTP_SEE_OTHER` vaut **303**. Ce code dit explicitement au navigateur : « va voir cette autre adresse, avec un `GET` ». Le 302, plus ancien, ne précise pas la méthode à utiliser, et ses premières implémentations l'ont interprété de façons différentes. Le 303 lève l'ambiguïté. `testCreateNote()` vérifie le code exact :

```php
self::assertResponseStatusCodeSame(303);
self::assertResponseRedirects('/note');
```

Quand le formulaire est invalide, il n'y a pas de redirection : la page est réaffichée avec les erreurs et un statut 422. La [fiche 6](06-validation-et-csrf.md) l'explique.

### Les messages flash et la session

Après la redirection, la liste s'affiche. Comment sait-elle qu'une note vient d'être créée ? Par un **message flash** :

```php
$this->addFlash('success', 'Note créée.');
```

Un message flash est rangé dans la **session**, une petite mémoire que le serveur garde pour chaque visiteur, retrouvée grâce à un cookie. Il survit donc à la redirection. Il a une particularité : **il disparaît dès qu'il a été lu**. Il s'affiche une fois, puis plus jamais.

[templates/base.html.twig](../../templates/base.html.twig) les lit sur toutes les pages :

```twig
{% for message in app.flashes('success') %}
    <p class="flash flash-success" role="status">{{ message }}</p>
{% endfor %}
{% for message in app.flashes('error') %}
    <p class="flash flash-error" role="alert">{{ message }}</p>
{% endfor %}
```

`app.flashes('success')` renvoie les messages de ce type, et les retire de la session. Les deux types ont chacun leur rôle d'accessibilité : `role="status"` pour une information, `role="alert"` pour une erreur, qu'un lecteur d'écran annonce tout de suite. Les tests s'en servent comme sélecteur : `assertSelectorTextContains('[role="status"]', 'Note créée.')`.

La session est activée dans [config/packages/framework.yaml](../../config/packages/framework.yaml) par `session: true`. Comme le dit le commentaire du fichier, elle ne démarre que si on y lit ou écrit quelque chose : un visiteur qui ne fait que lire la liste n'en a pas. En test, `storage_factory_id: session.storage.factory.mock_file` la range dans des fichiers, sans démarrer de vraie session PHP.

### La redirection de `/` sans code

La page d'accueil, `/`, n'a pas de contrôleur dans `src/`. Elle est déclarée dans [config/routes.yaml](../../config/routes.yaml) :

```yaml
app_home:
    path: /
    controller: Symfony\Bundle\FrameworkBundle\Controller\RedirectController
    defaults:
        route: app_note_index
        permanent: false
```

`RedirectController` est un contrôleur fourni par Symfony, qui ne fait que rediriger. Les `defaults` lui donnent ses arguments : la route cible et le type de redirection. Écrire une classe PHP pour une seule ligne de `redirectToRoute()` n'apporterait rien.

`permanent: false` donne un **302**, une redirection temporaire. Un 301, permanent, serait gardé en mémoire par les navigateurs : si l'accueil devenait un jour une vraie page, ceux qui l'ont déjà visitée continueraient d'être envoyés sur `/note`. Le 302 ne s'engage à rien. Ici, pas de 303 : la requête d'origine est déjà un `GET`.

`testHomeRedirectsToTheNotes()` vérifie que `/` redirige vers `/note`.

### Ce qui a été modifié après la génération

| Endroit | Généré | Modifié | Pourquoi |
|---|---|---|---|
| `index()` | `findAll()` | `findAllNewestFirst()` | Un ordre garanti, les plus récentes d'abord, voir [la fiche 4](04-entity-manager-et-repository.md) |
| `show()`, `edit()`, `delete()` | `/{id}` sans exigence | `requirements: ['id' => self::ID_PATTERN]` | Une 404 au lieu d'une 500 sur PostgreSQL, voir [la fiche 4](04-entity-manager-et-repository.md) |
| `new()`, `edit()`, `delete()` | Aucun message | `addFlash()` après chaque action | Dire à l'utilisateur ce qui s'est passé |
| `delete()` | Rien si le jeton est faux | Un message d'erreur | Une suppression refusée ne doit pas passer inaperçue, voir [la fiche 6](06-validation-et-csrf.md) |
| `NoteType` | Quatre champs, dont `createdAt` et `updatedAt` | `title` et `content` seulement | Les dates sont remplies par les rappels de l'entité, pas par l'utilisateur |
| `NoteType` | Libellés déduits des noms anglais | `Titre` et `Contenu`, et `TextareaType` pour le contenu | Une interface en français, et une zone de texte sur plusieurs lignes |
| Templates | En anglais, liste en tableau de toutes les colonnes | En français, une liste de titres avec leur date | Lisible, et sans l'identifiant technique |
| `show.html.twig` | Le contenu brut | `note.content\|nl2br` et les dates au format `d/m/Y H:i` | Garder les retours à la ligne, voir [la fiche 6](06-validation-et-csrf.md) |
| `_delete_form.html.twig` | `Are you sure you want to delete this item?` | `Supprimer cette note ?` | Une interface en français |
| `base.html.twig` | Le squelette de la recette | Les messages flash, la feuille de style | Afficher les messages sur toutes les pages |
| `config/routes.yaml` | Rien | La route `app_home` | Une page d'accueil qui mène quelque part |
| Le test | Un test par action, en anglais | Réécrit : validation, échappement, identifiants, jetons | Couvrir les cas dangereux, pas seulement le cas qui marche |

Le test généré vidait la table en supprimant chaque note avec l'`EntityManager`. Le test réécrit utilise `ResetDatabaseTrait`, qui recrée le schéma entier : [la fiche 7](07-tests-avec-base-de-donnees.md) explique pourquoi.

## Commandes officielles utilisées

```bash
php bin/console make:crud --help
symfony console make:crud Note
```

Le premier lancement a échoué, avec ce message :

```text
Missing packages: to use the make:crud command, run:

composer require form security-csrf
```

`make:crud` vérifie qu'il a tout ce qu'il lui faut avant de générer quoi que ce soit : le routeur, Form, le Validator, Twig, Doctrine et la protection CSRF. Form et la protection CSRF manquaient. Après l'installation, la commande demande le nom du contrôleur, `NoteController` par défaut, et si elle doit générer les tests. Les réponses ont été données sur l'entrée standard.

```bash
composer require form security-csrf
```

Installe les deux composants. La recette de `security-csrf` crée [config/packages/csrf.yaml](../../config/packages/csrf.yaml), expliqué dans [la fiche 6](06-validation-et-csrf.md).

```bash
php bin/console debug:router
php bin/console debug:router app_note_delete
```

La liste des routes, puis le détail d'une seule, avec sa méthode et son exigence.

## À retenir

- `make:crud` génère un contrôleur, un formulaire, six templates et un test : un point de départ, à adapter.
- Deux routes peuvent partager un chemin si leurs méthodes HTTP diffèrent.
- Après un `POST` réussi, on redirige avec un 303 : recharger la page ne renvoie pas le formulaire.
- Un message flash vit dans la session et disparaît une fois lu.
- Une redirection simple se déclare dans `config/routes.yaml`, sans écrire de contrôleur.
- Le composant Form relie les champs à l'entité ; il sera étudié en `Debutant-Nv4`.
