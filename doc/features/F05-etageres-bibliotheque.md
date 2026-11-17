# Feature: F05 — Bibliothèque : CRUD des étagères

## Overview
Ouvrir le module Library : chaque membre a une **bibliothèque**, qui n'est pas une table mais l'ensemble de ses **étagères**, qu'il nomme librement (« Fantasy », « Coup de cœur »…). La page « Ma bibliothèque » liste ses étagères dans son ordre et permet de les créer, renommer, supprimer (avec confirmation) et réordonner (boutons monter/descendre). Les livres viendront en F06.

## Out of scope
- Livres et rattachement aux étagères (F06).
- Consultation des étagères des autres membres (F11) : la page ne montre que les étagères de l'utilisateur connecté.
- Recherche globale (F12).
- Glisser-déposer : aucune dépendance présente ne le permet simplement (pas de nouvelle dépendance), les boutons monter/descendre suffisent.
- Parcours Playwright (réservé à connexion, ajout livre, ajout outil).

## Acceptance criteria
- [ ] AC1: Given un invité, when il appelle `library.index`, `library.bookshelves.index`, `library.bookshelves.store`, `library.bookshelves.update`, `library.bookshelves.destroy` ou `library.bookshelves.move`, then il est redirigé vers `login` et rien ne change.
- [ ] AC2: Given un utilisateur connecté (user ou admin), when il ouvre `library.index` (`/library`), then il est redirigé vers `library.bookshelves.index` (`/library/bookshelves`), qui rend `Library/Bookshelves/Index` avec `bookshelves` = ses seules étagères (`id`, `name`, `position`) triées par position ; aucune étagère d'un autre utilisateur n'apparaît.
- [ ] AC3: Given des étagères existantes, when l'utilisateur poste un nom entouré d'espaces sur `library.bookshelves.store`, then l'étagère est créée pour lui avec le nom trimé, en dernière position (1 pour la première), et il est redirigé vers la liste avec le toast « L'étagère « <nom> » a été créée. ».
- [ ] AC4: Given le formulaire de création ou de renommage, when le nom est absent, vide après trim, dépasse 100 caractères ou existe déjà parmi ses étagères quelle que soit la casse et les espaces de bord, then une erreur française est renvoyée sur `name` et rien ne change ; given le même nom chez un autre utilisateur, then la création est acceptée ; given le renommage d'une étagère avec son propre nom en changeant la casse, then il est accepté.
- [ ] AC5: Given une de ses étagères, when il poste un nouveau nom sur `library.bookshelves.update`, then le nom (trimé) est enregistré, la position ne change pas, toast « L'étagère a été renommée en « <nom> ». ».
- [ ] AC6: Given trois étagères (positions 1, 2, 3), when il supprime celle du milieu, then elle disparaît et les autres ont les positions 1 et 2 dans le même ordre ; toast « L'étagère « <nom> » a été supprimée. ».
- [ ] AC7: Given trois étagères, when il demande `library.bookshelves.move` avec `direction` = `up` sur la deuxième, then elle passe en position 1 et la première en 2 ; `down` sur la deuxième l'échange avec la troisième ; `up` sur la première ou `down` sur la dernière ne change rien ; une `direction` absente ou hors `up`/`down` renvoie une erreur de validation et ne change rien. Les positions restent compactes (1..n).
- [ ] AC8: Given une étagère d'un autre utilisateur, when il appelle `update`, `destroy` ou `move` dessus, then la réponse est 404 et rien ne change (Policy `BookshelfPolicy`, refus en « introuvable » pour ne pas révéler l'existence) ; la Policy n'autorise `update`, `delete` et `move` qu'au propriétaire (testée unitairement).
- [ ] AC9: Given la liste Ziggy, when un utilisateur connecté charge une page, then `library.index` et les routes `library.bookshelves.*` sont exposées (groupe `auth`) ; when un invité charge une page, then aucune ne l'est.
- [ ] AC10 (Vitest): Given le registre de navigation, then il contient l'entrée « Bibliothèque » (`i-lucide-library-big`, `library.index`, `requiredRole: 'user'`, rôle que les admins ont aussi) ; given le `DashboardLayout`, then elle est visible pour un user et un admin, et active sur `/library/bookshelves`.
- [ ] AC11 (Vitest): Given la page avec des étagères, then elles sont listées dans l'ordre reçu ; sans étagère, then le texte « Votre bibliothèque est vide. » et une invitation à créer la première étagère s'affichent.
- [ ] AC12 (Vitest): Given la page, when on saisit un nom et soumet le formulaire de création, then un `POST` vers `/library/bookshelves` est envoyé avec `name` ; une erreur serveur s'affiche sous le champ ; le champ est vidé après succès.
- [ ] AC13 (Vitest): Given la page, when on clique « Renommer » sur une étagère, then une `UModal` s'ouvre avec le nom prérempli ; à la soumission, un `PATCH` vers `/library/bookshelves/<id>` est envoyé ; les erreurs s'affichent dans la modale.
- [ ] AC14 (Vitest): Given la page, when on clique « Supprimer », then une `UModal` de confirmation nommant l'étagère s'ouvre ; « Annuler » ne fait rien ; « Supprimer » envoie un `DELETE` vers `/library/bookshelves/<id>`.
- [ ] AC15 (Vitest): Given la page, then le bouton « Monter » de la première étagère et « Descendre » de la dernière sont désactivés ; un clic sur un bouton actif envoie un `PATCH` vers `/library/bookshelves/<id>/move` avec la direction.
- [ ] AC16: Given le code, when on lance `composer test`, `composer lint`, `npm run test`, `npm run lint`, `npm run build`, then tout passe, et la CI est verte.

## Technical approach
1. **Données** : migration `create_bookshelves_table` (`id`, `user_id` FK `cascadeOnDelete`, `name` string(100), `position` unsigned int, timestamps, index unique `user_id`+`name`, index `user_id`+`position`). Modèle `App\Models\Library\Bookshelf` (`#[Fillable(['name'])]`, `#[UsePolicy]`, `#[UseFactory]`), relation `user()`, `User::bookshelves()` (HasMany triée par position). Factory `Database\Factories\Library\BookshelfFactory` (position séquentielle par utilisateur).
2. **Routes** (groupe `auth`, préfixe `/library`, noms `library.`) :
   - `GET /library` → `Library\LibraryController` (invocable) → redirection vers `library.bookshelves.index` : point d'entrée du module (menu), F06 pourra en faire une vue d'ensemble ou ajouter `/library/books`.
   - `GET /library/bookshelves` → `BookshelfController@index`
   - `POST /library/bookshelves` → `store` ; `PATCH /library/bookshelves/{bookshelf}` → `update` ; `DELETE /library/bookshelves/{bookshelf}` → `destroy`
   - `PATCH /library/bookshelves/{bookshelf}/move` → `BookshelfPositionController@update` (`library.bookshelves.move`)
3. **Validation** : `StoreBookshelfRequest` / `UpdateBookshelfRequest` (trim du nom en `prepareForValidation`, `required|string|max:100`, règle `App\Rules\Library\UniqueBookshelfName` : comparaison insensible à la casse avec `mb_strtolower` sur les noms de l'utilisateur, en ignorant l'étagère renommée — collection Eloquent, pas de SQL brut, identique en SQLite et MySQL) ; `MoveBookshelfRequest` (`direction` requis, `Rule::enum(BookshelfMoveDirection::class)`). Les requêtes de modification autorisent via la Policy (`authorize()`), donc 404 avant toute validation. Attribut `direction` traduit dans `lang/fr/validation.php`.
4. **Policy** `App\Policies\Library\BookshelfPolicy` : `update`, `delete`, `move` → propriétaire, sinon `Response::denyAsNotFound()`.
5. **Service** `App\Services\Library\BookshelfService` : `listFor(User)`, `create(User, name)` (transaction, verrou sur les étagères de l'utilisateur, position = max + 1), `rename`, `delete` (transaction, recompactage), `move(Bookshelf, direction)` (transaction, échange avec la voisine puis recompactage 1..n). Une violation de l'index unique (course) devient une `ValidationException` sur `name`.
6. **Contrôleurs minces** : appel du service, flash `toast` (mécanisme de F04), redirection vers la liste.
7. **Ziggy** : routes ajoutées au groupe `auth` (donc à la liste par défaut `only`).
8. **Front** :
   - `navigation/modules/library.ts` (entrée « Bibliothèque », `i-lucide-library-big`, `requiredRole: 'user'` : tout membre connecté, admins compris, jamais un invité) ajouté au registre.
   - `types/library.ts` (`Bookshelf`, `BookshelfMoveDirection`).
   - `pages/Library/Bookshelves/Index.vue` : `DashboardLayout` (titre « Ma bibliothèque », fil d'Ariane Bibliothèque › Étagères), formulaire de création (`UForm`, `UFormField`, `UInput`, `UButton`), liste des étagères (`UCard`), état vide (`UEmpty` si disponible, sinon `UAlert`), boutons monter/descendre/renommer/supprimer (`UButton` + `UTooltip`).
   - `components/Library/BookshelfRenameModal.vue` et `components/Library/BookshelfDeleteModal.vue` : `UModal` contrôlées (`v-model:open`).

Hypothèses :
- Longueur maximale du nom : 100 caractères. Seuls les espaces de bord sont retirés ; les espaces internes sont conservés tels quels.
- Positions à partir de 1, compactes ; ordre secondaire par `id` pour être déterministe si des positions venaient à être égales (le recompactage les corrige au prochain changement).
- Réordonnancement par un pas (monter/descendre) : un mouvement impossible (première vers le haut, dernière vers le bas) est un no-op silencieux, les boutons étant désactivés côté interface.
- Refus d'accès aux étagères d'autrui en 404 (pas 403) pour ne pas révéler leur existence, sur toutes les routes qui prennent une étagère.
- L'entrée de menu pointe vers `/library` (redirection) pour rester active sur toutes les sous-pages du module (`/library/bookshelves`, plus tard `/library/books`).
- Supprimer un compte supprime ses étagères (cascade) ; aucune suppression de compte n'existe aujourd'hui.

## Status
- Branch: feature/F05-etageres-bibliotheque
- Tests written: no
- Tests passing: no
