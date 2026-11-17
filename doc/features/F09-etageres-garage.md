# Feature: F09 — Garage : CRUD des étagères

## Overview
Ouvrir le module Garage, pendant exact de F05 (étagères de la bibliothèque) : chaque membre a un **garage**, qui n'est pas une table mais l'ensemble de ses **étagères à outils**, qu'il nomme librement (« Électroportatif », « Manuel »…). La page « Mon garage » liste ses étagères dans son ordre et permet de les créer, renommer, supprimer (avec confirmation) et réordonner (boutons monter/descendre). Mêmes règles et mêmes garanties que F05 : unicité normalisée, verrou sur le propriétaire, positions compactes sous index unique, 404 pour un autre membre. Les outils viendront en F10 (un outil pourra être sur plusieurs étagères).

## Out of scope
- Outils, marques, photos, accessoires, rattachement aux étagères (F10).
- Consultation des garages des autres membres (F11).
- Recherche globale (F12) : la source de recherche du Garage portera sur les outils, qui n'existent pas encore.
- Glisser-déposer (comme F05 : monter/descendre).
- Parcours Playwright (l'« ajout outil » arrive avec F10).

## Acceptance criteria
- [x] AC1: Given un invité, when il appelle `garage.index`, `garage.shelves.index`, `garage.shelves.store`, `garage.shelves.update`, `garage.shelves.destroy` ou `garage.shelves.move`, then il est redirigé vers `login` et rien ne change.
- [x] AC2: Given un utilisateur connecté (user ou admin), when il ouvre `garage.index` (`/garage`), then il est redirigé vers `garage.shelves.index` (`/garage/shelves`), qui rend `Garage/Shelves/Index` avec `shelves` = ses seules étagères (`id`, `name`, `position`) triées par position ; aucune étagère d'un autre membre ni aucune étagère de bibliothèque n'apparaît.
- [x] AC3: Given des étagères existantes, when l'utilisateur poste un nom entouré d'espaces sur `garage.shelves.store`, then l'étagère est créée pour lui avec le nom trimé, en dernière position (1 pour la première), et il est redirigé vers la liste avec le toast « L'étagère « <nom> » a été créée. ».
- [x] AC4: Given la création ou le renommage, when le nom est absent, vide après trim, n'est pas une chaîne, dépasse 100 caractères ou existe déjà parmi ses étagères du garage une fois normalisé (casse, majuscules accentuées et espaces multiples ignorés), then une erreur française est renvoyée sur `name` (« Vous avez déjà une étagère nommée « <nom> ». » pour un doublon) et rien ne change ; given « Café » puis « Cafe », then les deux sont acceptés (les accents comptent) ; given le même nom chez un autre membre ou sur une étagère de sa bibliothèque, then il est accepté ; given le renommage d'une étagère avec son propre nom en changeant la casse, then il est accepté.
- [x] AC5: Given la base, when deux étagères d'un même membre ont le même nom normalisé ou la même position (sans passer par la validation), then l'index unique les refuse ; un doublon de nom attrapé par l'index (course) devient une erreur de validation française sur `name`. Sous MySQL, la colonne `normalized_name` est en `utf8mb4_bin` (vérifié en CI).
- [x] AC6: Given une de ses étagères, when il poste un nouveau nom sur `garage.shelves.update`, then le nom (trimé) est enregistré, la position ne change pas, toast « L'étagère a été renommée en « <nom> ». ».
- [x] AC7: Given trois étagères (positions 1, 2, 3), when il supprime l'une d'elles (première, milieu, dernière), then elle disparaît et les autres sont renumérotées 1..n dans le même ordre ; les étagères des autres membres ne bougent pas ; toast « L'étagère « <nom> » a été supprimée. ».
- [x] AC8: Given trois étagères, when il demande `garage.shelves.move` avec `direction` = `up` ou `down`, then l'étagère s'échange avec sa voisine ; `up` sur la première ou `down` sur la dernière ne change rien ; une `direction` absente, inconnue ou non scalaire renvoie une erreur de validation et ne change rien ; des positions non compactes sont recompactées ; un modèle périmé est déplacé d'après les positions actuelles.
- [x] AC9: Given le service `ToolShelfService`, when il crée (y compris la toute première étagère), renomme, déplace ou supprime, then sa première requête est le verrou de la ligne `users` du propriétaire (`for update` vérifié sous MySQL).
- [x] AC10: Given une étagère d'un autre membre, when un user ou un admin appelle `update`, `destroy` ou `move` dessus, then la réponse est 404 et rien ne change ; une étagère inexistante répond 404 ; la Policy `ToolShelfPolicy` n'autorise `update`, `delete` et `move` qu'au propriétaire (testée unitairement, statut 404).
- [x] AC11: Given la liste Ziggy, when un utilisateur connecté (user ou admin) charge une page, then `garage.index` et les routes `garage.shelves.*` sont exposées (groupe `auth` seulement, donc `only`) ; when un invité charge une page, then aucune ne l'est.
- [x] AC12 (Vitest): Given le registre de navigation, then il contient l'entrée « Garage » (`i-lucide-warehouse`, `garage.index`, `requiredRole: 'user'`) après « Bibliothèque » ; given le `DashboardLayout`, then elle est visible pour un user et un admin, active sur `/garage/shelves`, jamais visible pour un invité ; les tests qui comptent les entrées sont mis à jour.
- [x] AC13 (Vitest): Given la page `Garage/Shelves/Index`, then titre « Mon garage », fil d'Ariane Garage › Étagères, étagères dans l'ordre reçu ; sans étagère, « Votre garage est vide. » et une invitation à créer la première étagère.
- [x] AC14 (Vitest): Given la page, when on soumet le formulaire de création, then un `POST` vers `/garage/shelves` avec `name`, champ vidé après succès, erreur serveur affichée sous le champ.
- [x] AC15 (Vitest): Given la page, when on clique « Renommer », then une `UModal` préremplie s'ouvre et envoie un `PATCH` vers `/garage/shelves/<id>` ; les erreurs s'affichent dans la modale. When on clique « Supprimer », then une `UModal` de confirmation nommant l'étagère s'ouvre ; « Annuler » ne fait rien ; « Supprimer » envoie un `DELETE` vers `/garage/shelves/<id>`.
- [x] AC16 (Vitest): Given la page, then « Monter » de la première et « Descendre » de la dernière sont désactivés (les deux pour une étagère seule) ; un clic actif envoie un `PATCH` vers `/garage/shelves/<id>/move` avec la direction, et les déplacements sont bloqués jusqu'à la fin de la requête.
- [x] AC17: Given les tests de F05, when on lance la suite, then ils passent sans modification (les parties extraites gardent le même comportement).
- [ ] AC18: Given le code, when on lance `composer test`, `composer lint`, `npm run test`, `npm run lint`, `npm run build`, then tout passe, et la CI est verte.

## Technical approach
1. **Données** : migration `create_tool_shelves_table` : `id`, `user_id` (FK `cascadeOnDelete`), `name` string(100), `normalized_name` string(100) (`utf8mb4_bin` sous MySQL seulement), `position` unsigned int, timestamps ; uniques `(user_id, normalized_name)` et `(user_id, position)`. Modèle `App\Models\Garage\ToolShelf` (`#[Fillable(['name'])]`, `#[UseFactory]`, `#[UsePolicy]`, mutateur de `name` qui remplit `normalized_name`, `isOwnedBy()`), `User::toolShelves()` (HasMany triée position puis id). Factory `Database\Factories\Garage\ToolShelfFactory` (position suivante du membre).
2. **Partage avec F05 (choix justifié)** : deux morceaux strictement identiques et génériques sont extraits dans `app/Support/`, sans état ni dépendance vers un module :
   - `App\Support\NormalizedName::of()` : trim, espaces multiples réduits, `mb_strtolower`, accents conservés. `Bookshelf::normalizeName()` et `ToolShelf::normalizeName()` y délèguent (API publique de F05 inchangée).
   - `App\Support\OrderedPositions` : `swapWithNeighbour(Collection, Model, int $offset)` (échange avec la voisine, rien aux bornes) et `save(Collection)` (renumérotation 1..n en deux temps sous index unique). `BookshelfService` et `ToolShelfService` l'utilisent.
   Le reste est dupliqué volontairement, car propre à chaque module (tables, messages, Policy, routes, textes) : service (verrou `writingFor` et traduction de l'exception d'unicité, qui nomme la colonne et le message du module), Form Requests, règle d'unicité, enum de direction, contrôleurs, ressource, page et modales Vue. Mutualiser ces derniers aurait couplé les deux modules par des paramètres (classe de modèle, noms de routes, textes) pour peu de lignes. Les tests de F05 ne sont pas modifiés et doivent rester verts (AC17) ; `NormalizedName` a son test unitaire.
3. **Routes** (groupe `auth`, préfixe `/garage`, noms `garage.`) :
   - `GET /garage` → `Garage\GarageController` (invocable) → redirection vers `garage.shelves.index` (F10 pourra la faire pointer vers « Mes outils », comme F06 l'a fait pour `/library`).
   - `Route::resource('shelves', ToolShelfController::class)->only(['index','store','update','destroy'])` (paramètre `{shelf}`).
   - `PATCH /garage/shelves/{shelf}/move` → `ToolShelfPositionController@update` (`garage.shelves.move`).
4. **Validation** : `ToolShelfNameRequest` (abstraite, trim, `required|string|max:100`, `UniqueToolShelfName`), `StoreToolShelfRequest`, `UpdateToolShelfRequest` (`Gate::inspect('update')` dans `authorize()`), `MoveToolShelfRequest` (`Rule::enum(ToolShelfMoveDirection::class)`, `Gate::inspect('move')`). `App\Rules\Garage\UniqueToolShelfName` compare `normalized_name`.
5. **Policy** `App\Policies\Garage\ToolShelfPolicy` : `update`, `delete`, `move` → propriétaire, sinon `Response::denyAsNotFound()`.
6. **Service** `App\Services\Garage\ToolShelfService` : `listFor`, `create`, `rename`, `delete`, `move` ; chaque écriture en transaction après verrou de la ligne `users` ; `UniqueConstraintViolationException` sur `normalized_name` → `ValidationException` « Vous avez déjà une étagère portant ce nom. ».
7. **Contrôleurs minces** : `ToolShelfController` (`index`, `store`, `update`, `destroy` avec `Gate::authorize('delete')`), toast puis liste.
8. **Ziggy** : `garage.index`, `garage.shelves.{index,store,update,destroy,move}` dans `$authenticatedRoutes`.
9. **Front** :
   - `navigation/modules/garage.ts` (« Garage », `i-lucide-warehouse`, `garage.index`, `requiredRole: 'user'`), ajouté au registre après la bibliothèque.
   - `types/garage.ts` (`ToolShelf`, `ToolShelfMoveDirection`).
   - `pages/Garage/Shelves/Index.vue` (calque de la page F05 : `UCard`, `UForm`, `UFormField`, `UInput`, `UButton`, `UEmpty`, `UIcon`), `components/Garage/{ToolShelfRenameModal,ToolShelfDeleteModal}.vue` (`UModal`).
   - Routes de test ajoutées dans `resources/js/test/setup.ts`.

### Préparation de F10 (sans l'implémenter)
- Table nommée `tool_shelves` et modèle `ToolShelf` : le pivot par défaut de Laravel entre `Tool` et `ToolShelf` sera `tool_tool_shelf` (`tool_id`, `tool_shelf_id`), FK en cascade des deux côtés comme `book_bookshelf` : supprimer une étagère ne retirera que les liens, sans code dans `ToolShelfService`.
- `ToolShelfResource` expose `id`, `name`, `position` ; un résumé `{id, name}` pourra être ajouté pour la fiche outil.
- La Policy suit le patron 404 ; la consultation des autres (F11) aura sa propre Policy.

Hypothèses :
- Mêmes règles que F05 (validées par l'utilisateur) : nom de 100 caractères max, trim des bords, espaces internes conservés à l'affichage, positions à partir de 1, ordre secondaire par `id`.
- Les noms d'étagères du garage et de la bibliothèque sont indépendants (même nom accepté dans les deux).
- Libellés : titre « Mon garage », bouton « Ajouter », champ « Nouvelle étagère » (exemple « Électroportatif, Manuel… »), état vide « Votre garage est vide. ».
- Icônes Lucide : `warehouse` (menu), `toolbox` (ligne d'étagère), `wrench` (état vide) ; embarquées au build.

## Status
- Branch: feature/F09-etageres-garage
- Tests written: yes (tests/Feature/Garage/*.php, tests/Unit/Garage/ToolShelfPolicyTest.php, tests/Unit/Core/NormalizedNameTest.php, resources/js/pages/Garage/Shelves/Index.test.ts, resources/js/layouts/DashboardLayout.garage.test.ts ; registry.test.ts et DashboardLayout.roles.test.ts mis à jour)
- Tests passing: yes en local (composer test, composer lint, npm run test, npm run lint, npm run build) ; AC18 en attente de la CI (MySQL : collation `utf8mb4_bin` et `for update`).

Notes d'implémentation :
- Tests de F05 non modifiés et verts après l'extraction (`git diff main -- tests/Feature/Library tests/Unit/Library` vide).
- `OrderedPositions::save()` garde l'index de chaque étagère dans la liste complète (pas l'index parmi celles qui bougent) : c'est sa place finale, comme l'ancien `BookshelfService::savePositions`.
- `NormalizedName` conserve exactement le comportement de F05 (`\s` sans UCP : l'espace insécable n'est pas réduit), pour ne pas rendre obsolètes les `normalized_name` déjà stockés.
- Test d'existence 404 étendu à `update`, `destroy` et `move` (F05 ne testait que `update`).
- Test « nom identique à une étagère de la bibliothèque accepté » et page du garage sans les étagères de la bibliothèque (isolation des modules).
