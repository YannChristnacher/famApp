# Feature: F11 — Consultation des bibliothèques et garages des autres membres (lecture seule)

## Overview
Le but de famApp est que chaque membre montre aux autres ses livres et ses outils. Tout membre connecté (admins compris) voit désormais les étagères, livres et outils de tous les autres, **en lecture seule** : une page « La famille » liste les membres, la page d'un membre montre sa bibliothèque et son garage (onglets, filtre par étagère), et les fiches livre et outil sont consultables par tous. Seul le propriétaire modifie : les règles d'écriture de F05, F06, F09 et F10 sont inchangées (404 pour tout autre, admins compris).

## Out of scope
- Recherche globale (F12), page de contrôle admin (F13).
- Prêt, réservation, demande d'emprunt, commentaires.
- Filtre par marque sur la page d'un membre (seul le filtre par étagère est demandé).
- Pagination (familles de quelques membres, quelques centaines d'objets).

## Acceptance criteria
- [x] AC1: Given un invité, when il ouvre `family.index` ou `family.members.show`, then il est redirigé vers la connexion.
- [x] AC2: Given plusieurs membres avec des livres et des outils, when un membre connecté ouvre « La famille » (`/family`), then il voit tous les membres activés (lui compris, admins compris ; pas les comptes dont l'invitation n'est pas utilisée) triés par nom puis id, chacun avec `id`, `name`, `books_count`, `tools_count`, `own` (vrai pour lui) ; aucun email ni autre champ ; le nombre de requêtes ne dépend pas du nombre de membres.
- [x] AC3: Given un autre membre, when on ouvre sa page (`/family/{member}`), then on reçoit `member` (`id`, `name`), `own` (faux), ses étagères de bibliothèque et de garage (par position), ses livres (titre puis id) et ses outils (nom puis id), avec les mêmes champs que « Mes livres » / « Mes outils » ; le nombre de requêtes ne dépend pas du nombre de livres ni d'outils ; un membre inconnu ou un compte non activé répond 404 (il redevient visible une fois son mot de passe défini).
- [x] AC4: Given la page d'un membre, when on filtre par `?bookshelf=<id>` et/ou `?shelf=<id>`, then seuls ses livres (resp. outils) de cette étagère sont listés ; une étagère d'un autre membre (même celle du visiteur), une étagère de l'autre module, un id inconnu ou non entier répond 404 (même règle que « Mes livres » / « Mes outils »). `?tab=garage` ouvre l'onglet garage, toute autre valeur l'onglet bibliothèque.
- [x] AC5: Given le livre ou l'outil d'un autre membre, when un membre ou un admin ouvre sa fiche (`show`), then 200 avec la fiche, `owner` (`id`, `name`) et `editable` faux ; pour le propriétaire, `editable` est vrai.
- [x] AC6: Given le livre, l'outil ou l'étagère d'un autre membre, when un membre ou un admin appelle `edit`, `update`, `destroy` ou `move`, then 404 et rien ne change (inchangé depuis F05/F06/F09/F10, toujours testé route par route).
- [x] AC7: Given les données envoyées au front par les nouvelles pages et les fiches, then elles ne contiennent ni `google_books_data`, ni `user_id`, ni `cover_path`, ni chemin de photo, ni email, ni `brand_id`, ni horodatage.
- [x] AC8: Given les Policies, then `view` de `BookPolicy`, `ToolPolicy`, `BookshelfPolicy` et `ToolShelfPolicy` est autorisé à tout utilisateur connecté (admin compris), alors que `update`, `delete` (et `move` pour les étagères) restent réservés au propriétaire avec un refus 404.
- [x] AC9: Given les routes `family.index` et `family.members.show`, then elles sont exposées à Ziggy dans le groupe `auth` seulement (absentes pour un invité, jamais dans `admin`).
- [x] AC10 (Vitest): menu : entrée « La famille » (`i-lucide-users`) visible pour tout membre connecté, active sur `/family` et ses sous-pages ; les tests de comptage du menu sont mis à jour.
- [x] AC11 (Vitest): page « La famille » : un élément par membre avec nom, nombres de livres et d'outils (pluriels français), lien vers sa page ; mention « Vous » sur sa propre ligne ; état vide.
- [x] AC12 (Vitest): page d'un membre : onglets « Bibliothèque » / « Garage » (`UTabs`), cartes livre et outil réutilisées, filtres par étagère qui pointent vers la page du membre et gardent l'onglet ; aucune action d'écriture (ajouter, gérer les étagères, modifier, supprimer) chez un autre ; sur sa propre page, liens « Gérer mes livres » / « Gérer mes outils ».
- [x] AC13 (Vitest): fiches livre et outil : boutons « Modifier » / « Supprimer » seulement si `editable` ; pour un autre membre, fil d'Ariane La famille › propriétaire › titre, mention du propriétaire, étagères qui pointent vers la page du membre filtrée.

## Technical approach
1. **Policies** (`app/Policies/Library/{BookPolicy,BookshelfPolicy}.php`, `app/Policies/Garage/{ToolPolicy,ToolShelfPolicy}.php`) : `view(User, X)` renvoie `Response::allow()` (tout utilisateur connecté : la signature exige un `User`, un invité est refusé par le Gate). `update`/`delete`/`move` inchangées. Les commentaires de classe sont mis à jour.
2. **Fiches** : `BookController::show` et `ToolController::show` gardent `Gate::authorize('view', …)` ; ajoutent `owner` (`MemberResource` : `id`, `name`) et `editable` (`Gate::allows('update', …)`). `edit` reste derrière `update` (404). Le propriétaire est chargé avec la fiche (`load('user')`).
3. **Module Core, « La famille »** :
   - `app/Services/Core/FamilyService.php` : `members()` = `User::query()->withCount(['books', 'tools'])->orderBy('name')->orderBy('id')->get()` (une requête, pas de N+1).
   - `app/Http/Controllers/Core/FamilyController.php` (invocable, `GET /family`, `family.index`) → page `Core/Family/Index`, `FamilyMemberResource` (`id`, `name`, `books_count`, `tools_count`, `own`).
   - `app/Http/Controllers/Core/MemberController.php` (invocable, `GET /family/{member}`, `family.members.show`, liaison implicite sur `User`) → page `Core/Family/Show` : `member` (`MemberResource`), `own`, `tab`, `bookshelves`, `books`, `toolShelves`, `tools`, `filters` (`bookshelf`, `shelf`). Les listes réutilisent `BookshelfService::listFor`, `BookService::listFor`, `ToolShelfService::listFor`, `ToolService::listFor($member, $shelf, null)` et les Resources existantes (`BookResource`, `BookshelfResource`, `ToolResource`, `ToolShelfResource`), qui n'exposent déjà que l'utile.
   - `app/Http/Requests/Core/ShowMemberRequest.php` : `bookshelfFilter(User $member)` et `shelfFilter(User $member)` (même règle que `ListBooksRequest`/`ListToolsRequest`, bornée aux étagères du membre affiché), `tab()`.
4. **Ziggy** : `family.index`, `family.members.show` dans `$authenticatedRoutes` ; test `tests/Feature/Core/FamilyRouteListTest.php`.
5. **Front** :
   - `navigation/modules/family.ts` (« La famille », `i-lucide-users`, `family.index`, `requiredRole: 'user'`) ajouté au registre après Garage.
   - `types/family.ts` (`Member`, `FamilyMember`).
   - `pages/Core/Family/Index.vue` : `UPageList` de `UUser` (nom, description « n livres · n outils » via `support/family/counts.ts`, lien vers la page du membre), `UBadge` « Vous ».
   - `pages/Core/Family/Show.vue` : `UTabs` (`v-model` initialisé par `tab`), dans chaque onglet filtres par étagère (`UButton`, comme les pages « Mes … ») et grille de `BookCard` / `ToolCard` ; états vides adaptés ; si `own`, boutons « Gérer mes livres » / « Gérer mes outils ».
   - `pages/Library/Books/Show.vue`, `pages/Garage/Tools/Show.vue` : props `owner`, `editable` ; actions et modale de suppression seulement si `editable` ; fil d'Ariane et liens d'étagère selon le propriétaire.
6. **Tests** : Pest `tests/Feature/Core/{FamilyIndex,MemberPage,FamilyRouteList}Test.php`, `tests/Feature/Core/ReadOnlyAccessTest.php` (fiches lisibles, toutes les écritures 404 y compris `edit` pour livres, outils, étagères des deux modules), `tests/Unit/{Library,Garage}/*PolicyTest.php` complétés ; Vitest `pages/Core/Family/{Index,Show}.test.ts`, `DashboardLayout.family.test.ts`, fiches mises à jour.

### Tests existants adaptés (justification)
- `BookAccessTest` et `ToolAccessTest` (« hides the books/tools of another user on every route ») incluaient `show` : la lecture d'un autre membre est désormais voulue (cadrage F11). `show` est retiré de ce jeu de données et un test positif « un autre membre ou un admin lit la fiche, `editable` faux » le remplace ; `edit`, `update` et `destroy` restent couverts pour un membre et un admin.
- `BookPolicyTest` et `ToolPolicyTest` testaient `view` comme réservé au propriétaire : `view` sort de ce jeu de données et a son propre test « autorisé à tous » ; `update` et `delete` restent testés (404, admin compris).
- Tests de menu (`registry.test.ts`, `DashboardLayout.roles.test.ts`) : comptes d'entrées ajustés pour la nouvelle entrée.

### Hypothèses
- « La famille » liste les comptes **activés** (`password_set_at` non nul, scope `User::activated()`), admins compris (un admin est un membre), triés par nom puis id ; un compte non activé (invitation pas encore utilisée ou expirée) n'y figure pas et sa page répond 404 (décision de l'orchestrateur à la relecture).
- Le code de la consultation vit dans le module `Core` (il agrège Library et Garage) ; l'entrée de menu a son propre fichier `navigation/modules/family.ts`.
- Un filtre d'étagère qui n'appartient pas au membre affiché répond 404 (cohérent avec « Mes livres » / « Mes outils » et avec la décision F05 « 404 plutôt que 403 »).
- L'onglet n'est pas une route : `?tab=garage` le choisit au chargement, les liens de filtre le conservent ; changer d'onglet ne recharge pas la page.
- Sur sa propre page de membre, on voit la même vue en lecture seule plus des liens vers « Mes livres » / « Mes outils » (pas d'édition sur place).

## Status
- Branch: feature/F11-consultation-famille
- Tests written: yes (tests/Feature/Core/{FamilyIndex,MemberPage,FamilyRouteList,ReadOnlyFamily}Test.php, tests/Unit/{Library,Garage}/*PolicyTest.php, tests/Feature/{Library/BookAccess,Garage/ToolAccess}Test.php, resources/js/pages/Core/Family/{Index,Show}.test.ts, resources/js/pages/{Library/Books,Garage/Tools}/Show.test.ts, resources/js/support/family/counts.test.ts, resources/js/layouts/DashboardLayout.family.test.ts, registry.test.ts, DashboardLayout.roles.test.ts)
- Tests passing: yes (composer test, composer lint, npm run test, npm run lint, npm run build en local)

## Relecture (2026-11-18)
Relecteur indépendant : aucun point bloquant. Corrigé à la demande de l'orchestrateur : les comptes non activés (`password_set_at` nul) n'apparaissent plus dans « La famille » et leur page répond 404 (scope réutilisable `User::activated()`, `ShowMemberRequest::authorize()` en `denyAsNotFound`) ; tests : liste sans l'invité, 404 sur sa page pour un membre et un admin, réapparition une fois le mot de passe défini. Tests de filtre rendus indépendants des identifiants (MySQL ne remet pas les auto-incréments à zéro entre les tests).
Reporté (noté dans `doc/MEMORY.md`) : les deux onglets de la page membre sont toujours calculés ; charger l'onglet inactif à la demande (`Inertia::optional`) si le volume grandit.
