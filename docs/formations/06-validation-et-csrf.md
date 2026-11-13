# 6. Validation, CSRF et échappement

## Ce que c'est

Trois protections différentes entourent chaque saisie :

| Protection | Question à laquelle elle répond | Où dans ce projet |
|---|---|---|
| **Validation** | La donnée est-elle acceptable ? | Les attributs `#[Assert\...]` de `Note` |
| **Jeton CSRF** | La requête vient-elle vraiment du site ? | Le formulaire et le bouton de suppression |
| **Échappement** | Le texte affiché peut-il être pris pour du code ? | Twig, sur chaque `{{ }}` |

La première protège la base, la deuxième protège l'utilisateur contre des actions faites à son insu, la troisième protège les visiteurs contre du code injecté dans une page.

## Pourquoi ça existe

Tout ce qui arrive du navigateur peut être faux, par erreur ou par malveillance. Les attributs HTML comme `required` ou `maxlength` aident la personne qui remplit le formulaire, mais ils se contournent sans effort : il suffit d'envoyer la requête sans passer par la page. Les tests de ce projet le font d'ailleurs à chaque fois. La seule vérification qui compte est donc celle du serveur.

## Où le voir dans ce projet

- [src/Entity/Note.php](../../src/Entity/Note.php) : les quatre contraintes et leurs messages.
- [src/Controller/NoteController.php](../../src/Controller/NoteController.php) : `isValid()` et `isCsrfTokenValid()`.
- [config/packages/csrf.yaml](../../config/packages/csrf.yaml) : la protection CSRF du formulaire.
- [config/packages/validator.yaml](../../config/packages/validator.yaml) : la configuration du Validator, laissée telle que la recette l'a écrite.
- [templates/note/_delete_form.html.twig](../../templates/note/_delete_form.html.twig) : le jeton de suppression.
- [templates/note/show.html.twig](../../templates/note/show.html.twig) et [templates/note/index.html.twig](../../templates/note/index.html.twig) : l'échappement et `nl2br`.
- [tests/Entity/NoteValidationTest.php](../../tests/Entity/NoteValidationTest.php) : les contraintes testées seules.
- [tests/Controller/NoteControllerTest.php](../../tests/Controller/NoteControllerTest.php) : le 422, le jeton faux et l'échappement, vus du navigateur.

## Comment lire ce code

### Les quatre contraintes

Dans [src/Entity/Note.php](../../src/Entity/Note.php) :

```php
#[ORM\Column(length: 120)]
#[Assert\NotBlank(normalizer: 'trim', message: 'Le titre est obligatoire.')]
#[Assert\Length(max: 120, maxMessage: 'Le titre ne doit pas dépasser {{ limit }} caractères.')]
private ?string $title = null;

#[ORM\Column(type: Types::TEXT)]
#[Assert\NotBlank(normalizer: 'trim', message: 'Le contenu est obligatoire.')]
#[Assert\Length(max: 10000, maxMessage: 'Le contenu ne doit pas dépasser {{ limit }} caractères.')]
private ?string $content = null;
```

| Contrainte | Refuse | Message |
|---|---|---|
| `NotBlank` sur `title` | Un titre vide ou fait d'espaces | `Le titre est obligatoire.` |
| `Length(max: 120)` sur `title` | Un titre de plus de 120 caractères | `Le titre ne doit pas dépasser 120 caractères.` |
| `NotBlank` sur `content` | Un contenu vide ou fait d'espaces | `Le contenu est obligatoire.` |
| `Length(max: 10000)` sur `content` | Un contenu de plus de 10 000 caractères | `Le contenu ne doit pas dépasser 10000 caractères.` |

`{{ limit }}` n'est pas du Twig : c'est un emplacement que le Validator remplace par la valeur de `max`. Les messages par défaut sont en anglais ; chaque contrainte reçoit donc le sien, en français.

Les contraintes vivent sur l'**entité**, pas sur le formulaire. Elles s'appliquent quel que soit le chemin par lequel une note arrive : formulaire, test, et plus tard une commande ou une API.

`Length` compte des **caractères**, pas des octets : `é` compte pour un. C'est la même unité que le `VARCHAR(120)` de PostgreSQL.

### Pourquoi limiter ce que la base limite déjà

La colonne `title` est un `VARCHAR(120)`. Pourquoi répéter 120 dans une contrainte ? Parce que la base et la validation ne répondent pas de la même façon :

- **Sans `Length`**, un titre de 121 caractères irait jusqu'à PostgreSQL, qui refuserait l'`INSERT` avec une erreur `value too long`. L'utilisateur verrait une erreur 500.
- **Avec `Length`**, le formulaire est réaffiché, avec un message clair, et rien n'est envoyé à la base.

SQLite, lui, ne fait pas respecter la longueur d'un `VARCHAR` : il accepterait les 121 caractères. Les tests, qui tournent sur SQLite, ne verraient donc pas l'erreur de PostgreSQL. La contrainte rend le comportement identique sur les deux moteurs.

Le contenu est un `TEXT`, sans limite côté base. La limite de 10 000 caractères est un choix de l'application, pour qu'une note reste une note.

### `normalizer: 'trim'`

Un titre fait de trois espaces, `"   "`, n'est pas vide pour PHP : c'est une chaîne de trois caractères. Sans normaliseur, `NotBlank` l'accepterait, et la liste afficherait une note sans titre visible.

`normalizer: 'trim'` dit à `NotBlank` d'appliquer la fonction `trim()` à la valeur **avant de la vérifier**. `trim("   ")` donne `""`, qui est vide : la saisie est refusée. Le cas est testé dans `invalidSubmissions()` sous le nom `titre fait d'espaces`.

Le normaliseur ne modifie pas la valeur enregistrée. Un titre `"  Courses  "` est accepté et stocké tel quel, avec ses espaces. `Length`, qui n'a pas de normaliseur, compte aussi ces espaces.

### Le 422

Quand `isValid()` répond non, le contrôleur ne redirige pas. Il réaffiche le formulaire :

```php
return $this->render('note/new.html.twig', [
    'note' => $note,
    'form' => $form,
]);
```

Aucune ligne de ce code ne parle de 422. C'est `render()`, dans `AbstractController`, qui regarde les paramètres : s'il y trouve un formulaire envoyé et invalide, il donne à la réponse le statut **422 Unprocessable Content**, « contenu impossible à traiter ».

Pourquoi pas 200 ? Un 200 dirait « tout s'est bien passé ». Le 422 dit précisément ce qui arrive : la requête est bien formée, mais son contenu est refusé. Un test, un outil ou une bibliothèque JavaScript peuvent le savoir sans lire la page. `testInvalidNoteIsNotSaved()` vérifie le statut, le message affiché, et qu'aucune note n'a été enregistrée :

```php
self::assertResponseStatusCodeSame(422);
self::assertSelectorTextContains('main', $message);
self::assertCount(0, $this->manager->getRepository(Note::class)->findAll());
```

Les messages s'affichent à côté des champs, grâce à `form_widget()` dans [templates/note/_form.html.twig](../../templates/note/_form.html.twig).

[tests/Entity/NoteValidationTest.php](../../tests/Entity/NoteValidationTest.php) vérifie les mêmes cas sans navigateur, en demandant le service `validator` et en lui passant une `Note`. Il vérifie aussi les limites exactes : un titre de 120 caractères et un contenu de 10 000 sont acceptés.

### Ce qu'est une attaque CSRF

**CSRF** veut dire _Cross-Site Request Forgery_ : falsification de requête intersite. Le scénario :

1. Vous êtes connecté à un site, qui vous reconnaît grâce à un cookie.
2. Vous visitez une autre page, piégée. Elle contient un formulaire caché qui pointe vers le premier site, par exemple `POST /note/3`, et l'envoie tout seul.
3. Votre navigateur joint le cookie du premier site à cette requête. Pour le serveur, c'est vous qui avez demandé la suppression.

La défense : exiger dans chaque requête qui modifie des données une preuve que la requête vient bien du site. Ce projet utilise deux formes de cette preuve.

### Le jeton du formulaire : sans état

Le formulaire de note contient un champ caché, ajouté par le composant Form :

```html
<input type="hidden" id="note__token" name="note[_token]" data-controller="csrf-protection" value="csrf-token" />
```

La valeur, `csrf-token`, est la même pour tout le monde : ce n'est pas un secret. Elle vient de [config/packages/csrf.yaml](../../config/packages/csrf.yaml), créé par la recette de `security-csrf` :

```yaml
framework:
    form:
        csrf_protection:
            token_id: submit

    csrf_protection:
        stateless_token_ids:
            - submit
            - authenticate
            - logout
```

Les formulaires utilisent le jeton nommé `submit`, et ce jeton est **sans état** (_stateless_) : rien n'est gardé en session. À la réception, Symfony vérifie autre chose : les en-têtes que le navigateur ajoute lui-même et qu'une page piégée ne peut pas falsifier, `Sec-Fetch-Site`, `Origin` ou `Referer`. Ils doivent désigner le site lui-même.

L'essai a été fait en envoyant le même formulaire valide à `/note/new` avec différents en-têtes :

| En-têtes de la requête | Résultat |
|---|---|
| `Origin: http://localhost`, l'adresse du site | 303, la note est créée |
| `Sec-Fetch-Site: same-origin` | 303, la note est créée |
| `Origin: https://evil.example` | 422 |
| Aucun de ces en-têtes | 422 |

Dans les cas refusés, le message affiché est `The CSRF token is invalid. Please try to resubmit the form.` Il est en anglais : ce projet n'installe pas de traductions. C'est une limite connue de ce niveau. Une personne honnête ne le voit qu'en envoyant le formulaire depuis une autre adresse que celle du site, ou avec un navigateur qui n'envoie aucun de ces en-têtes.

L'attribut `data-controller="csrf-protection"` sert à un petit script JavaScript que la recette propose dans les projets qui utilisent Stimulus. Ce projet n'a pas de JavaScript : l'attribut est sans effet, et la vérification par les en-têtes suffit.

### Le jeton de suppression : avec état

Le bouton de suppression n'est pas un formulaire du composant Form. [templates/note/_delete_form.html.twig](../../templates/note/_delete_form.html.twig) écrit son jeton à la main :

```twig
<input type="hidden" name="_token" value="{{ csrf_token('delete' ~ note.id) }}">
```

L'identifiant du jeton est `delete` suivi du numéro de la note, par exemple `delete3`. Il ne figure pas dans `stateless_token_ids` : c'est donc un jeton **classique**, une longue valeur aléatoire gardée dans la session du visiteur. Une page piégée ne peut pas la deviner.

Le contrôleur la vérifie :

```php
if ($this->isCsrfTokenValid('delete'.$note->getId(), $request->getPayload()->getString('_token'))) {
    $entityManager->remove($note);
    $entityManager->flush();
    $this->addFlash('success', 'Note supprimée.');
} else {
    $this->addFlash('error', 'La note n\'a pas été supprimée : le formulaire a expiré, réessayez.');
}
```

Le numéro dans l'identifiant lie le jeton à **une** note : le jeton de la note 3 ne permet pas de supprimer la note 4.

Le code généré par `make:crud` ne faisait rien quand le jeton était faux : il redirigeait vers la liste, sans un mot, et la note restait là. La branche `else` a été ajoutée pour que l'échec se voie. Le message parle de formulaire expiré, parce que c'est la cause la plus probable pour une personne honnête : une session terminée entre l'affichage de la page et le clic. `testDeleteWithAnInvalidTokenKeepsTheNote()` envoie le jeton `faux`, et vérifie le message et la note toujours en base.

### L'échappement de Twig

Une note peut contenir n'importe quel texte, y compris du HTML : `<script>alert(1)</script>`. Si ce texte était recopié tel quel dans la page, le navigateur exécuterait le script. C'est une faille **XSS** (_cross-site scripting_).

Twig **échappe** automatiquement tout ce qu'il affiche avec `{{ }}`, comme vu au [premier niveau](https://github.com/baptistedev-fullstack/symfony-debutant-nv1/blob/main/docs/formations/05-twig.md) : `<` devient `&lt;`, `>` devient `&gt;`. Le navigateur affiche alors les caractères, sans les interpréter. Le titre de la liste et celui du détail en profitent sans rien écrire de spécial :

```twig
<a href="{{ path('app_note_show', {'id': note.id}) }}">{{ note.title }}</a>
```

`testHtmlIsEscaped()` crée une note dont le titre et le contenu sont `<script>alert(1)</script>`, puis vérifie que la balise `<script>` n'apparaît nulle part dans le HTML de la liste ni du détail.

### `nl2br` sans perdre l'échappement

Un contenu sur plusieurs lignes contient des retours à la ligne, que HTML ignore : tout s'afficherait sur une ligne. Le filtre `nl2br` ajoute une balise `<br />` avant chaque retour à la ligne. Dans [templates/note/show.html.twig](../../templates/note/show.html.twig) :

```twig
<p>{{ note.content|nl2br }}</p>
```

Ajouter du HTML à un texte échappé pose une question : le `<br />` va-t-il lui aussi être échappé, ou le texte de l'utilisateur ne l'est-il plus ? Twig règle cela dans la définition du filtre : `nl2br` **échappe d'abord** le texte reçu, puis ajoute les `<br />`, et déclare son résultat sûr. Le `<script>` de l'utilisateur devient `&lt;script&gt;`, et seuls les `<br />` restent du vrai HTML.

L'ordre compte. Le filtre `raw`, qui désactive l'échappement, serait ici une faille : `note.content|raw` recopierait le script tel quel. Ce projet n'utilise jamais `raw`.

`testMultilineContentKeepsItsLines()` vérifie la présence du `<br />`, et `testHtmlIsEscaped()` que l'échappement reste actif sur cette même page.

## Commandes officielles utilisées

```bash
composer require symfony/validator
composer require form security-csrf
```

Installent le Validator, puis Form et la protection CSRF. Les recettes créent [config/packages/validator.yaml](../../config/packages/validator.yaml) et [config/packages/csrf.yaml](../../config/packages/csrf.yaml).

```bash
php bin/console debug:validator 'App\Entity\Note'
```

Liste les contraintes de chaque propriété de `Note`, avec leurs options. Les guillemets sont nécessaires à cause du `\`.

```bash
php bin/console debug:config framework csrf_protection
```

Montre la configuration CSRF retenue, dont la liste des jetons sans état.

## À retenir

- Le navigateur aide, le serveur décide : seule la validation côté serveur protège.
- Les contraintes vivent sur l'entité et s'appliquent quel que soit le chemin d'arrivée.
- `normalizer: 'trim'` refuse un texte fait d'espaces, sans modifier la valeur enregistrée.
- Un formulaire invalide est réaffiché avec un 422, posé par `render()`.
- Le jeton CSRF du formulaire est sans état et vérifie l'origine ; celui de la suppression est aléatoire et gardé en session.
- Twig échappe tout ; `nl2br` échappe d'abord, puis ajoute les `<br />`.
