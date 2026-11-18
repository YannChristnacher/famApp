# Feature: F12 — Recherche globale

## Overview
La barre de recherche du header (bouton `UDashboardSearchButton` + palette `UDashboardSearch`, posés vides en F01) cherche dans toute l'application. Chaque module fournit sa **source de recherche** (contrat de Core) enregistrée dans un **registre** ; un **seul endpoint JSON** interroge toutes les sources et renvoie des résultats groupés (« Livres », « Outils », « Membres »). Les résultats couvrent tous les membres (lecture ouverte depuis F11) ; choisir un résultat ouvre sa fiche (ou la page du membre) par une visite Inertia. Ajouter un module plus tard = écrire sa source et l'enregistrer.

## Out of scope
- Recherche plein texte avancée (Scout, moteur externe, index FULLTEXT), fautes de frappe, pondération fine.
- Filtres avancés (par membre, par étagère, par marque), page de résultats dédiée, pagination des résultats.
- Recherche des étagères (« éventuellement » dans le cadrage : non retenu, voir hypothèses).
- Insensibilité aux accents sous SQLite (moteur des tests locaux seulement, voir hypothèses).

## Acceptance criteria
- [ ] AC1: Given un invité, when il appelle `GET /search?q=…` en JSON, then 401 (redirection vers la connexion hors JSON) et aucune recherche n'est faite.
- [ ] AC2: Given un membre connecté, when `q` est absent, fait moins de 2 caractères (après trim) ou plus de 100, then 422 JSON avec une erreur française sur `q`.
- [ ] AC3: Given un membre connecté, when il dépasse 60 recherches par minute, then 429 JSON `{message}` en français ; un autre membre n'est pas bloqué.
- [ ] AC4: Given des livres de plusieurs membres, when on cherche un fragment du titre, de l'auteur ou de l'ISBN (ISBN saisi avec ou sans tirets/espaces), then les livres correspondants de **tous** les membres sont renvoyés dans le groupe `books` (« Livres », icône `i-lucide-book`) avec `type` `book`, `id`, `label` (titre), `description` (« auteur · chez <propriétaire> », « chez vous » pour soi), `url` (fiche `library.books.show`, relative), `image` (URL de couverture ou `null`).
- [ ] AC5: Given des outils, when on cherche un fragment du nom, de la marque ou d'un accessoire, then le groupe `tools` (« Outils », `i-lucide-wrench`) les renvoie avec `label` (nom), `description` (« marque · chez <propriétaire> », ou « chez <propriétaire> » sans marque), `url` (`garage.tools.show`), `image` (vignette ou `null`).
- [ ] AC6: Given des membres, when on cherche un fragment de nom, then le groupe `members` (« Membres », `i-lucide-user`) renvoie les membres **activés** seulement (un compte invité non activé n'apparaît jamais), avec `label` (nom), `description` (« n livres · n outils », précédé de « Vous · » pour soi), `url` (`family.members.show`), `image` `null`.
- [ ] AC7: Given un terme contenant `%`, `_`, `!` ou `\`, when on cherche, then ces caractères sont pris littéralement (« 100% » ne trouve pas « 1000 pièces », « a_b » ne trouve pas « axb »).
- [ ] AC8: Given un terme en casse différente (« TOLKIEN », « bosch »), when on cherche, then les correspondances sont trouvées sur SQLite (local) comme sur MySQL (CI) ; sous MySQL, les accents sont aussi ignorés (« eleve » trouve « Élève »), test exécuté sous MySQL seulement.
- [ ] AC9: Given plus de 5 correspondances dans un groupe, then le groupe en renvoie 5 au plus ; les résultats dont le libellé **commence par** le terme passent avant ceux qui le **contiennent** (ou ne correspondent que par un autre champ), puis par libellé puis id.
- [ ] AC10: Given la réponse, then elle a exactement la forme `{groups: [{key, label, icon, results: [{type, id, label, description, url, image}]}]}`, seuls les groupes non vides y figurent, dans l'ordre d'enregistrement (Livres, Outils, Membres), et elle ne contient ni email, ni `google_books_data`, ni `user_id`, ni chemin de fichier, ni horodatage.
- [ ] AC11: Given beaucoup de correspondances (livres, outils avec marque, vignette et accessoires, membres), then le nombre de requêtes SQL ne dépend pas du nombre de résultats (pas de N+1).
- [ ] AC12: Given une source factice enregistrée dans le registre (`SearchRegistry::register`), when on appelle l'endpoint, then son groupe apparaît dans la réponse après ceux des modules.
- [ ] AC13: Given la route `search`, then elle est exposée à Ziggy dans le groupe `auth` seulement.
- [ ] AC14 (Vitest, composable): la saisie déclenche une seule requête après l'anti-rebond (250 ms) ; une saisie de moins de 2 caractères n'envoie rien et vide les résultats ; une nouvelle saisie annule la requête en cours (`AbortController`) et une réponse obsolète est ignorée ; `loading` vaut vrai pendant la requête ; erreurs : message du serveur (429), session expirée (401/419), réseau, réponse inattendue.
- [ ] AC15 (Vitest, palette): la palette affiche un groupe par module avec l'intitulé français reçu (« Livres », « Outils », « Membres »), sans filtrage local (`ignoreFilter`) ; état de chargement (`loading`), « Tapez au moins 2 caractères… », « Aucun résultat pour « … » », message d'erreur ; choisir un résultat fait `router.visit(url)` ; le raccourci par défaut de Nuxt UI (`meta_k`) est conservé et le groupe « Thème » reste désactivé.

## Technical approach
1. **Contrat (Core)** `app/Services/Core/Search/` :
   - `SearchSource` (interface) : `key(): string`, `label(): string`, `icon(): string`, `search(SearchTerm $term, User $searcher, int $limit): Collection<int, SearchResult>` (déjà triée et bornée).
   - `SearchTerm` (readonly) : terme trimé ; `whereStartsWith()` / `whereContains()` ajoutent une condition `LIKE … ESCAPE '!'` (les jokers `%`, `_` et le caractère d'échappement `!` sont échappés) ; `ranked(Builder, colonneLibellé, conditionsSecondaires, limite)` : requête 1 = libellé qui commence par le terme (tri libellé, id) ; requête 2, seulement s'il reste de la place = autres correspondances hors ids déjà trouvés. Deux requêtes bornées par source, chargement anticipé conservé.
   - `SearchResult` et `SearchGroup` (DTO readonly).
   - `SearchRegistry` (singleton) : `register(class-string<SearchSource>)`, `sources()` résolues par le conteneur dans l'ordre d'enregistrement.
   - `GlobalSearchService::search(string, User): list<SearchGroup>` : interroge chaque source avec `RESULTS_PER_GROUP` (5), ne garde que les groupes non vides.
2. **Sources** : `app/Services/Library/BookSearchSource.php` (titre, auteur, ISBN normalisé par `Isbn::normalize()` quand le terme ressemble à un ISBN ; `with('user:id,name')`), `app/Services/Garage/ToolSearchSource.php` (nom, marque via `whereHas('brand')`, accessoires via `whereHas('accessories')` ; `with(['user:id,name','brand','thumbnail'])`), `app/Services/Core/MemberSearchSource.php` (`User::activated()`, nom, `withCount(['books','tools'])`).
3. **Registre** : `app/Providers/SearchServiceProvider.php` (ajouté à `bootstrap/providers.php`) enregistre les trois sources.
4. **Endpoint** : `GET /search` (`search`, groupe `auth`, `throttle:search`), `app/Http/Controllers/Core/SearchController.php` (invocable), `app/Http/Requests/Core/SearchRequest.php` (`q` requis, chaîne, 2 à 100 caractères), `app/Http/Resources/Core/SearchGroupResource.php` + `SearchResultResource.php`. Limiteur `search` dans `RateLimitServiceProvider` (par membre, 60/min, JSON 429). `lang/fr/validation.php` : attribut `q` = « recherche ». Ziggy : `search` dans `$authenticatedRoutes`.
5. **Front** :
   - `types/search.ts` ; `composables/useGlobalSearch.ts` (`watch` du terme, anti-rebond `setTimeout` 250 ms, `AbortController`, `fetch` same-origin, messages français).
   - `components/Core/GlobalSearch.vue` : `UDashboardSearch` avec `v-model:search-term`, `v-model:open`, `:groups` (un groupe par module, `ignoreFilter: true`, items `{label, description, avatar|icon, onSelect: router.visit(url)}`), `:loading`, `:color-mode="false"`, placeholder français, slot `#empty` (trop court, chargement, erreur, aucun résultat). Raccourci par défaut conservé.
   - `layouts/DashboardLayout.vue` remplace la palette vide par `<GlobalSearch />`.
6. **Tests** : Pest `tests/Feature/Core/{SearchEndpoint,SearchRouteList,SearchRegistry}Test.php`, `tests/Feature/Library/BookSearchSourceTest.php`, `tests/Feature/Garage/ToolSearchSourceTest.php`, `tests/Feature/Core/MemberSearchSourceTest.php`, `tests/Unit/Core/SearchTermTest.php` ; Vitest `composables/useGlobalSearch.test.ts`, `components/Core/GlobalSearch.test.ts`, `DashboardLayout.test.ts` adapté (l'état vide « arrive bientôt » devient l'invite « Tapez au moins 2 caractères… »).

### Hypothèses
- Endpoint `GET /search?q=` dans Core ; 5 résultats par groupe, 2 à 100 caractères, 60 recherches par minute et par membre (anti-rebond de 250 ms côté front : quelques requêtes par mot tapé).
- Pertinence simple : rang 0 = le libellé (titre, nom d'outil, nom du membre) commence par le terme ; rang 1 = toute autre correspondance ; puis libellé et id. Pas de score.
- `LIKE … ESCAPE '!'` est écrit par `whereRaw` avec paramètres liés dans `SearchTerm` uniquement : `whereLike()` de Laravel n'a pas de clause `ESCAPE` et SQLite n'a pas de caractère d'échappement par défaut ; `!` est choisi plutôt que `\` pour éviter les différences d'échappement des antislashs entre moteurs. Colonne passée par `Grammar::wrap()`, jamais de saisie dans le SQL.
- Casse et accents : MySQL (`utf8mb4_unicode_ci`, production et CI) ignore casse **et** accents ; SQLite (tests locaux) ignore la casse ASCII seulement (« É » ≠ « é », « e » ≠ « é »). Comportement de référence = MySQL ; les tests de casse utilisent de l'ASCII, le test d'accents ne tourne que sous MySQL.
- ISBN : le terme est normalisé comme l'ISBN stocké (tirets et espaces retirés, `x` final en majuscule) et comparé par « contient » s'il ne contient alors que des chiffres (et un `X` final).
- Les étagères ne sont pas des résultats : elles se trouvent depuis la page du membre ; à ajouter plus tard par une source dédiée si besoin.
- URLs relatives (`route(..., false)`) calculées par le serveur : le front ne connaît pas les routes des modules, il visite l'URL reçue. Les intitulés et icônes des groupes viennent aussi du serveur (un nouveau module n'a rien à écrire côté front).

## Status
- Branch: feature/F12-recherche-globale
- Tests written: no
- Tests passing: no
