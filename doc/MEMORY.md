# Mémoire partagée famApp
> À lire avant d'explorer le code. Mise à jour par l'agent scribe à chaque feature. Dernière mise à jour : F12.

## État du projet

- F00 socle (Laravel 13, Inertia, Vue + TS, Nuxt UI v4, Docker, CI) : terminée.
- F01 layout dashboard (sidebar à registre de modules, header, palette vide) : terminée.
- F02 authentification (Fortify, rôles admin/user, seeder admin, Playwright login) : terminée.
- F03 demande de compte publique (job, notification admin, limiteur) : terminée.
- F04 admin : liste des demandes, création du compte, invitation signée 7 jours, toasts flash : terminée.
- F05 bibliothèque : CRUD et ordre des étagères : terminée.
- F06 à F08 bibliothèque (livres en saisie manuelle + couverture + étagères multiples, préremplissage Google Books par ISBN, scan EAN-13 à la caméra) : relues et validées (PR #7 à #9).
- F09 garage : CRUD et ordre des étagères à outils, extraction de `NormalizedName` et `OrderedPositions` (PR #10) : relue et validée.
- F10 garage : CRUD des outils (marques partagées, photos multiples, accessoires), extraction du pipeline d'image dans `ResizedJpegStorage` (PR #11) : relue et validée ; le statut final dans le backlog passe à `terminée` au merge (F06 à F09 le sont déjà ou le seront au merge de leur PR).
- F11 consultation : « La famille » et page d'un membre (bibliothèque et garage des autres en lecture seule), fiches livre/outil lisibles par tous, `view` des Policies ouvert (PR #12) : relue et validée ; le statut final dans le backlog passe à `terminée` au merge.
- F12 recherche globale : palette de l'en-tête (`UDashboardSearch`) alimentée par un endpoint `GET /search` qui agrège une source par module (livres, outils, membres) (PR #13) : relue et validée sans correction ; le statut final dans le backlog passe à `terminée` au merge.
- Prochaine : F13 (page de contrôle admin, dépend de F12).
- Suite du backlog : `doc/BACKLOG.md` (source de vérité des statuts).

## Carte du code par module

### Core
- `app/Http/Controllers/Core/HomeController.php` : page `Core/Home`.
- `app/Services/Core/FamilyService.php` : `members()` = comptes `User::activated()` (admins compris) avec `withCount(['books','tools'])`, tri nom puis id, une seule requête.
- `app/Http/Controllers/Core/FamilyController.php` (invocable, `GET /family`, `family.index`) → `Core/Family/Index` ; `MemberController.php` (invocable, `GET /family/{member}`, `family.members.show`, liaison implicite sur `User`) → `Core/Family/Show` avec `member`, `own`, `tab`, `bookshelves`, `books`, `toolShelves`, `tools`, `filters` `{bookshelf, shelf}` ; réutilise les services `listFor` et les Resources de Library et Garage.
- `app/Http/Requests/Core/ShowMemberRequest.php` : `authorize()` = connecté et membre activé, sinon `denyAsNotFound` ; `member()`, `bookshelfFilter()`, `shelfFilter()` (étagère du membre affiché, sinon 404 ; non entier = 404), `tab()` (`garage` sinon `library`).
- `app/Http/Resources/Core/MemberResource.php` (`id`, `name`, rien d'autre) ; `FamilyMemberResource` (+ `books_count`, `tools_count`, `own`).
- `app/Models/User.php` : scope `activated()` (`password_set_at` non nul ; attribut `#[Scope]`), à utiliser pour tout ce qui liste des membres.
- Front famille : `pages/Core/Family/{Index,Show}.vue` (Show : `UTabs` bibliothèque/garage, `?tab=garage` choisit l'onglet, filtres par étagère qui gardent l'onglet, `BookCard`/`ToolCard` réutilisées, liens « Gérer mes livres/outils » si `own`), `navigation/modules/family.ts` (« La famille », `i-lucide-users`, `family.index`, `requiredRole: 'user'`, après Garage), `types/family.ts`, `support/family/counts.ts` (`countLabel`, `inventoryLabel`, pluriel français : 0 et 1 au singulier).
- Recherche globale (F12) : contrat `app/Services/Core/Search/SearchSource.php` (`key`, `label`, `icon`, `search(SearchTerm, User, limit)`), `SearchTerm` (terme trimé ; `whereStartsWith`/`whereContains` = `like ? escape '!'`, seul `whereRaw` du projet ; `ranked()` = 2 requêtes bornées : libellé qui commence par, puis autres correspondances), DTO `SearchResult` (`type`, `id`, `label`, `description`, `url` relative, `image`) et `SearchGroup`, `SearchRegistry` (singleton, `register(class)`, `sources()`), `app/Services/Core/GlobalSearchService.php` (`RESULTS_PER_GROUP` 5, groupes vides omis), sources `Library/BookSearchSource` (titre, auteur, ISBN normalisé), `Garage/ToolSearchSource` (nom, marque, accessoires), `Core/MemberSearchSource` (membres activés, nom, nombres) ; `app/Providers/SearchServiceProvider.php` (ordre des groupes). Endpoint `GET /search?q=` (`search`, `throttle:search` 60/min par membre, JSON 429), `Core/SearchController`, `Core/SearchRequest` (2 à 100 caractères), `Core/SearchGroupResource`. Front : `composables/useGlobalSearch.ts` (anti-rebond 250 ms, `AbortController`, messages français), `components/Core/GlobalSearch.vue` (`UDashboardSearch`, groupes `ignoreFilter`, `router.visit(url)`), `types/search.ts`, monté par `DashboardLayout`. Tests : `tests/Feature/Core/{SearchEndpoint,SearchRegistry,SearchRouteList,MemberSearchSource}Test.php`, `tests/Feature/Library/BookSearchSourceTest.php`, `tests/Feature/Garage/ToolSearchSourceTest.php`, `tests/Unit/Core/SearchTermTest.php`, Vitest `useGlobalSearch.test.ts`, `GlobalSearch.test.ts`.
- `app/Http/Middleware/HandleInertiaRequests.php` : props partagées `auth.user` (`id`, `name`, `email`, `role`, rien d'autre) et `status`.
- `app/Providers/RateLimitServiceProvider.php` : tous les limiteurs (`login`, `password-reset-link`, `password-reset`, `account-request`, `google-books`, `search`) et leur réponse commune ; `google-books` (10/min) et `search` (60/min) sont par membre (`user:<id>`), réponse JSON 429.
- `app/Support/ClientNetwork.php` : clé de limiteur (IPv4 telle quelle, IPv6 groupée par /64, IPv4 encapsulée ramenée à l'IPv4).
- `app/Support/NormalizedName.php` : `of(string)`, forme de comparaison d'un nom libre (trim, espaces multiples réduits, `mb_strtolower`, accents conservés ; `\s` sans UCP, l'espace insécable n'est pas réduit : ne pas changer, cela rendrait périmés les `normalized_name` stockés). Partagé Library et Garage.
- `app/Support/OrderedPositions.php` : `swapWithNeighbour(Collection, Model, offset)` (échange avec la voisine, rien aux bornes) et `save(Collection)` (renumérotation 1..n en deux temps sous index unique). Partagé Library et Garage ; l'appelant lit et écrit sous le verrou du propriétaire.
- `app/Services/Core/ResizedJpegStorage.php` : pipeline d'image partagé (livres et outils) : `store(UploadedFile, dossier, largeurMax)` (GD, JPEG 85 sur fond blanc, sans EXIF, `<dossier>/<40 car.>.jpg`, disque `public` = constante `DISK`), `delete(?string)`, `url(?string)` ; lève `app/Exceptions/Core/UnreadableImageException.php` si GD ne décode pas (l'appelant la traduit en erreur de validation).
- `app/Support/ImageUploadRules.php` : `rules()` (JPEG/PNG/WebP lus dans le contenu, 10 Mo, 6000 px de côté par `Rule::dimensions`), constantes `MAX_KILOBYTES`, `MAX_PIXELS_PER_SIDE` ; utilisé par `BookRequest` (`cover`) et `ToolRequest` (`photos.*.file`).
- `app/Support/ZiggyGroup.php` : groupes Ziggy imprimés selon l'utilisateur (`['guest']`, `['auth']`, `['auth','admin']`).
- `config/ziggy.php` : liste d'autorisation des routes du front (groupes `guest`, `auth`, `admin` + `only`).
- `config/famapp.php` : `admin.*` (`ADMIN_NAME|EMAIL|PASSWORD`), `invitation_expire_days` (7).
- `config/services.php` → `google_books` : `key` (`GOOGLE_BOOKS_API_KEY`, optionnelle), `base_url`, `timeout` (5), `cache_minutes` (30), `save_calls_per_minute` (20), `cover_hosts`, `cover_max_kilobytes` (2048), `cover_timeout` (5).
- `bootstrap/app.php` : alias middleware `guest` (remplacé) et `invitation`, `authenticateSessions()`, `UnauthenticatedResponse`.
- Front : `resources/js/app.ts`, `appRoot.ts` (`UApp` + locale `fr`, partagé avec les tests), `layouts/DashboardLayout.vue`, `layouts/AuthLayout.vue`.
- `navigation/registry.ts` (`navigationRegistry`, `filterEntriesByRoles`, `toNavigationMenuItems`, `isActiveUrl`), `navigation/types.ts`, `navigation/modules/{core,library,garage,admin}.ts`.
- `composables/useAuth.ts` (`useAuth()`, `rolesOf()` : un admin a `['admin','user']`), `composables/useFlashToasts.ts` (monté par `DashboardLayout`).
- Types : `resources/js/types/{auth,flash,admin,library,garage}.ts` ; `global.d.ts` typé `Ziggy`, `sharedPageProps`, `flashDataType`.
- `resources/views/vendor/{notifications,mail}/...` : gabarits d'email en français (tous les emails).
- Tests : `tests/Feature/Core/{FamilyIndex,MemberPage,FamilyRouteList,ReadOnlyFamily}Test.php` (`ReadOnlyFamilyTest` : fiches lisibles, toute écriture 404 pour livres, outils et étagères des deux modules), Vitest `pages/Core/Family/{Index,Show}.test.ts`, `DashboardLayout.family.test.ts`, `support/family/counts.test.ts` ; `tests/Feature/Core/{HomeTest,LocaleTest}.php`, `tests/Unit/Core/{ClientNetwork,NormalizedName}Test.php` ; les tests du pipeline d'image sont ceux de `BookCoverServiceTest` (inchangés) et de `ToolPhotoServiceTest`.

### Auth
- `app/Enums/Role.php` (`Admin`, `User`, `assignableValues()`), `app/Models/User.php` (`isAdmin()`, `hasSetPassword()`, `bookshelves()`, rôle non assignable en masse).
- `users` : `role` (défaut `user`), `password_set_at` (nul = invitation utilisable).
- `app/Providers/FortifyServiceProvider.php` : `Fortify::ignoreRoutes()`, réponses personnalisées. Routes d'auth déclarées dans `routes/web.php`.
- `app/Http/Controllers/Auth/{AuthPageController,AccountRequestController,InvitationController}.php`.
- `app/Http/Responses/Auth/*` : `LoginResponse`, `LogoutResponse` (`Inertia::location`), `PasswordResetLinkRequestedResponse` (réponse neutre), `InvalidPasswordResetResponse`, `UnauthenticatedResponse`.
- `app/Http/Middleware/RedirectAuthenticatedUsers.php` (alias `guest`), `EnsureInvitationIsUsable.php` (alias `invitation`).
- `app/Actions/Fortify/ResetUserPassword.php` (remplit `password_set_at`), `app/Services/Auth/{AdminAccountService,AccountRequestService,InvitationService}.php`.
- `app/Jobs/Auth/SubmitAccountRequest.php` : toute la décision de la demande publique (vérifie, enregistre, notifie).
- `app/Notifications/Auth/{ResetPassword,AccountRequested,SetPassword}Notification.php` : toutes `ShouldQueue`, textes français en dur.
- `app/Models/Auth/AccountRequest.php` (statut `AccountRequestStatus`, scope `pending`, `user()`), factory avec `approved()`, `rejected()` ; `UserFactory` avec `admin()`, `invited()`.
- `database/seeders/AdminUserSeeder.php` : échoue explicitement sans `ADMIN_EMAIL`/`ADMIN_PASSWORD`.
- Pages : `pages/Auth/{Login,ForgotPassword,ResetPassword,RequestAccount,SetPassword,InvitationInvalid}.vue`, `components/Auth/UserMenu.vue`.
- Tests : `tests/Feature/Auth/*`, `tests/Unit/Auth/*`, `tests/e2e/login.spec.ts`, `resources/js/pages/Auth/*.test.ts`.

### Admin
- Routes `admin.*` : groupe `auth` + `can:admin`, préfixe `/admin` ; aucune Policy, seulement le Gate `admin` (`AppServiceProvider`).
- `app/Http/Controllers/Admin/{AccountRequestController,AccountRequestAccountController}.php`.
- `app/Services/Admin/AccountRequestReviewService.php` : `pendingFirst()`, `createAccount()`, `reject()` (verrou + vérification « en attente »).
- `app/Exceptions/Admin/AccountRequestAlreadyProcessedException.php` : se rend en redirection + toast d'erreur.
- `app/Http/Requests/Admin/StoreAccountFromRequestRequest.php` (vérifie aussi le Gate), `app/Http/Resources/Admin/AccountRequestResource.php`.
- Pages : `pages/Admin/AccountRequests/{Index,CreateAccount}.vue`, `components/Admin/AccountRequestsTable.vue`.
- Tests : `tests/Feature/Admin/*` (dont `AdminRouteListTest`, `AccountRequestAccessTest`), `resources/js/pages/Admin/**`, `DashboardLayout.admin.test.ts`.

### Library
- `bookshelves` : `id`, `user_id` (FK cascade), `name` (100, trimé), `normalized_name` (100, `utf8mb4_bin` sous MySQL), `position` (1..n compact), unique `user_id`+`normalized_name`, unique `user_id`+`position`. Pas de table « bibliothèque » : la bibliothèque d'un membre = ses étagères.
- `books` : `id`, `user_id` (FK cascade), `title` (255), `author` (255, un seul champ texte), `isbn` (13, nul, normalisé, **non unique**), `cover_path` (nul, chemin sur le disque `public`), `google_books_data` (JSON nul : réponse brute Google du volume retenu, écrite par le serveur seulement, jamais exposée au front), index `user_id`+`title`. Migration `database/migrations/2026_11_17_100000_create_books_table.php`.
- `book_bookshelf` : `book_id`, `bookshelf_id` (FK cascade des deux côtés), clé primaire composite. Supprimer une étagère ou un livre ne retire que les liens, jamais l'autre côté (aucun code dans `BookshelfService`).
- `app/Models/Library/Bookshelf.php` (`#[Fillable(['name'])]`, `#[UsePolicy]`, `isOwnedBy()`, `normalizeName()` délègue à `NormalizedName::of()` ; le mutateur de `name` remplit `normalized_name`), `books()` (par titre), `User::bookshelves()` (triée position puis id), `User::books()`.
- `app/Models/Library/Book.php` (`#[Fillable(['title','author','isbn'])]` : `cover_path` et `user_id` affectés explicitement, `#[UsePolicy]`, `#[UseFactory]`, `bookshelves()` triées par position, `isOwnedBy()`), `database/factories/Library/BookFactory.php` (état `withCover()`).
- `app/Http/Controllers/Library/LibraryController.php` : `/library` redirige vers `library.books.index` (« Mes livres ») ; `BookshelfController`, `BookshelfPositionController` inchangés.
- `app/Http/Controllers/Library/BookController.php` : resource complète `library.books.*` (`index`, `create`, `store`, `show`, `edit`, `update`, `destroy`) ; toast puis fiche après création/modification, liste après suppression. `show` (lisible par tout membre) ajoute `owner` (`MemberResource`) et `editable` (`Gate::allows('update')`).
- `app/Http/Requests/Library/BookRequest.php` (abstraite : champs partagés, trim, normalisation ISBN, `bookAttributes()`, `bookshelfIds()`, `cover()`), `StoreBookRequest`, `UpdateBookRequest` (`Gate::inspect('update')` dans `authorize()`, `remove_cover`, `removesCover()`), `ListBooksRequest` (`bookshelfFilter()` : étagère du membre, sinon 404 ; paramètre non entier = 404).
- `app/Http/Requests/Library/{BookshelfNameRequest,StoreBookshelfRequest,UpdateBookshelfRequest,MoveBookshelfRequest}.php` ; `app/Rules/Library/UniqueBookshelfName.php` (compare `normalized_name`) ; `app/Enums/Library/BookshelfMoveDirection.php`.
- `app/Rules/Library/Isbn.php` : `Isbn::normalize()` (retire tirets/espaces, majuscules) + validation ISBN-10 (modulo 11) et ISBN-13 (modulo 10), message `Isbn::MESSAGE`.
- `app/Policies/Library/{BookshelfPolicy,BookPolicy}.php` : `view` (étagère et livre) = `Response::allow()` pour tout connecté, admins compris (F11) ; `update`, `delete`, `move` (étagère) = propriétaire seulement, sinon `Response::denyAsNotFound()`.
- `app/Services/Library/BookshelfService.php` : `listFor`, `create`, `rename`, `delete`, `move` ; chaque écriture verrouille d'abord la ligne `users` du propriétaire (`writingFor`), renumérotation par `OrderedPositions` (`swapWithNeighbour`, `save`).
- `app/Services/Library/BookService.php` : `listFor(User, ?Bookshelf)` (`with('bookshelves')`, tri titre puis id), `create`, `update`, `delete` ; `withNewCover()` nettoie le fichier neuf si la transaction échoue.
- `app/Services/Library/BookCoverService.php` : façade de `ResizedJpegStorage` pour les couvertures : `store(UploadedFile)` (traduit `UnreadableImageException` en erreur `cover`), `delete(?string)`, `url(?string)` ; constantes `DISK`, `DIRECTORY` (`covers`), `MAX_WIDTH` (800).
- `app/Http/Resources/Library/{BookshelfResource,BookResource}.php` (`BookResource` : `id`, `title`, `author`, `isbn`, `cover_url`, `bookshelves` `{id,name}`) ; factory `BookshelfFactory`.
- Front : `pages/Library/Books/{Index,Create,Edit,Show}.vue` (Show : props `owner` et `editable`, « Modifier »/« Supprimer » seulement si `editable`, fil d'Ariane La famille › propriétaire pour un autre ; Index : grille de cartes + filtres par étagère), `pages/Library/Bookshelves/Index.vue` (bouton « Mes livres », lien « Voir les livres » par étagère), `components/Library/{BookCard,BookCover,BookForm,BookDeleteModal,BookshelfRenameModal,BookshelfDeleteModal}.vue`, `types/library.ts` (`Bookshelf`, `BookshelfSummary`, `Book`), `navigation/modules/library.ts`.
- `lang/fr/validation.php` : noms de champs des livres (`attributes`).
- Recherche (F12) : `app/Services/Library/BookSearchSource.php` (groupe `books`, icône `i-lucide-book` ; titre, auteur, ISBN saisi avec ou sans tirets ; libellé = titre, description = auteur · « chez vous » / « chez <nom> », image = couverture, URL `library.books.show`).
- Tests : `tests/Feature/Library/*` (`Book{Access,Index,Pages}Test`, `{Store,Update,Destroy}BookTest`, `BookCoverServiceTest`, `BookshelfDeletionKeepsBooksTest`, `BookshelfNameUniquenessTest`, `BookshelfServiceLockTest`, `LibraryRouteListTest`), `tests/Unit/Library/{BookshelfPolicyTest,BookPolicyTest,IsbnTest}.php`, Vitest `pages/Library/Books/{Index,Show}.test.ts`, `components/Library/BookForm.test.ts`, `pages/Library/Bookshelves/Index.test.ts`, `DashboardLayout.library.test.ts`, Playwright `tests/e2e/book.spec.ts`.

#### Library : Google Books (F07)
- `google_books_requests` (journal, une ligne par appel sortant) : `id`, `user_id` (FK nulle, `nullOnDelete`), `type` (`GoogleBooksRequestType` : `isbn` recherche, `volume` confirmation à l'enregistrement, `cover` image), `query` (255 : ISBN, identifiant du volume, ou hôte+chemin de l'image sans query string), `status`, `http_status` (nul sans réponse), `duration_ms`, horodatage ; index sur `created_at` seulement. Migration `2026_11_17_*_create_google_books_requests_table.php`, modèle `app/Models/Library/GoogleBooksRequest.php`.
- Statuts `app/Enums/Library/GoogleBooksRequestStatus.php` : `found`, `not_found` (aussi 404 HTTP), `rate_limited` (429 Google ou budget d'enregistrement épuisé), `client_error`, `server_error`, `timeout` (aussi toute `ConnectionException`), `invalid_response` (JSON invalide, redirection, fichier non image), `storage_failed` (écriture de la couverture impossible), `refused` (URL ou taille refusée avant tout appel, sans code HTTP). `fromFailedHttpStatus()` traduit un code HTTP.
- `app/Services/Library/GoogleBooksService.php` : `findByIsbn(isbn, User): ?GoogleBooksVolume` (premier volume de `q=isbn:`, le met en cache) ; `trustedVolume(id, User)` (cache sinon `volumes/<id>`, `ValidationException` sur `google_volume_id` si introuvable, indisponible ou budget épuisé). Cache serveur `google-books:volume:<id>` = réponse brute, `cache_minutes`. Tout appel passe par `journaled()` (envoi, statut, journal).
- `GoogleBooksVolume` (lecture seule : `id`, `title`, `author`, `coverUrl`, `raw`), `GoogleBooksUnavailableException` (statut + message français, porte le 503), `GoogleBooksJournal::record()`.
- `GoogleBooksCoverPolicy` : `allows(url)` (https, hôte exact de `cover_hosts`, ni identifiants ni port) et `largestCover(imageLinks)` (relève `http://` en `https://` seulement pour un hôte autorisé).
- `GoogleBooksCoverService::store(volume, User): ?string` : télécharge puis délègue à `BookCoverService::store()` ; `null` sur tout échec (journalisé).
- `GoogleBooksSaveLimiter::attempt(User)` : budget par membre des appels faits pendant un enregistrement (`save_calls_per_minute`), clé `google-books-save:<id>` ; distinct du limiteur de route `google-books`. Un volume en cache ne consomme rien.
- `BookCoverInput` (`file`, `remove`, `fromGoogle`) : entrée « couverture » de `BookService::create|update`, qui renvoie un `SavedBook` (`book`, `googleCoverMissing` pour le toast). `BookService` résout le volume de confiance avant toute écriture.
- `BookRequest` : `google_volume_id` (regex `^[A-Za-z0-9_-]{1,64}$`) et `use_google_cover` ; `googleVolumeId()`, `coverInput()`. Un `google_books_data` posté est ignoré (pas dans `Fillable`).
- `app/Http/Controllers/Library/GoogleBooksLookupController.php` (invocable, `GET /library/google-books`, route `library.google-books.lookup`, `throttle:google-books`), `LookupGoogleBooksRequest` (ISBN normalisé + règle `Isbn`), `GoogleBooksLookupResource` (exactement `volume_id`, `title`, `author`, `cover_url`). Réponses : 404 aucun livre, 503 quota ou panne, 422 ISBN, 429 limiteur.
- Front : `composables/useGoogleBooksLookup.ts` (`fetch` same-origin, messages français selon le statut), `components/Library/BookForm.vue` (bouton « Rechercher », encart « Trouvé sur Google Books » fermable, encart de conflit « Remplacer » / « Garder ma saisie », aperçu avec « Ne pas utiliser cette couverture »), type `GoogleBooksLookup` dans `types/library.ts`.
- Tests : `tests/Feature/Library/{GoogleBooksService,GoogleBooksCoverService,GoogleBooksLookup,SaveBookFromGoogleBooks}Test.php`, `useGoogleBooksLookup.test.ts`, `BookForm.googleBooks.test.ts` ; helpers `googleVolume()`, `googleSearch()`, `jpegBytes()` dans `tests/Pest.php`.

#### Library : scan du code-barres (F08, front seulement)
- `resources/js/support/library/bookBarcode.ts` : `bookIsbnFromBarcode(raw)` renvoie l'ISBN si EAN-13 de livre (13 chiffres, préfixe 978/979, clé EAN-13 juste, espaces retirés), sinon `null`.
- `resources/js/support/library/barcodeDetector.ts` : `createBookBarcodeDetector()` (`BarcodeDetector` natif s'il gère `ean_13`, sinon polyfill `barcode-detector/ponyfill` + `zxing_reader.wasm` importés à la demande, `locateFile` vers notre build), `isDetectorUnavailable()` (`NotSupportedError`), types `BookBarcodeDetector`, `DetectedBarcode`.
- `resources/js/components/Library/IsbnScannerModal.vue` : `UModal` (`v-model:open`, événement `detected(isbn)`) ; `getUserMedia` caméra arrière, `<video muted playsinline>`, lecture toutes les 200 ms, vibration, arrêt des pistes (lecture, fermeture, démontage), messages français par `DOMException.name`, repli « Prendre une photo du code-barres » (`UFileUpload`, décodage dans le navigateur).
- `BookForm.vue` : bouton « Scanner » (`data-testid="isbn-scan"`) entre l'ISBN et « Rechercher » ; `applyScannedIsbn()` remplit l'ISBN, toast « Code-barres lu », puis `searchGoogleBooks()` (une seule recherche, même limiteur).
- Build : `vite.config.ts` (plugin `famapp:zxing-without-cdn-fallback`, retire l'URL jsDelivr de zxing-wasm ; le build échoue si le motif disparaît) ; `scripts/check-build-no-cdn.mjs`, lancé par `npm run build` après `vite build` : aucune URL de CDN de code dans `public/build`, `zxing_reader-*.wasm` présent et de SHA-256 égal à `ZXING_WASM_SHA256` de `barcode-detector`.
- Tests : Vitest `support/library/{bookBarcode,barcodeDetector}.test.ts`, `components/Library/{IsbnScannerModal,BookForm.scanner}.test.ts` (module détecteur mocké par `vi.mock('@/support/library/barcodeDetector')`, `navigator.mediaDevices` défini par `Object.defineProperty`, `fetch` simulé). Pas de Playwright (pas de caméra en CI).

### Garage
- `tool_shelves` : `id`, `user_id` (FK cascade), `name` (100, trimé), `normalized_name` (100, `utf8mb4_bin` sous MySQL), `position` (1..n compact), unique `user_id`+`normalized_name`, unique `user_id`+`position`. Pas de table « garage » : le garage d'un membre = ses étagères. Migration `database/migrations/2026_11_19_000000_create_tool_shelves_table.php`.
- `app/Models/Garage/ToolShelf.php` (`#[Fillable(['name'])]`, `#[UsePolicy]`, `#[UseFactory]`, `isOwnedBy()`, `normalizeName()` → `NormalizedName`, mutateur de `name` remplit `normalized_name`), `User::toolShelves()` (triée position puis id), `database/factories/Garage/ToolShelfFactory.php` (position suivante du membre).
- `app/Services/Garage/ToolShelfService.php` : `listFor`, `create`, `rename`, `delete`, `move` ; `writingFor` verrouille la ligne `users` du propriétaire, doublon d'index sur `normalized_name` → `ValidationException` « Vous avez déjà une étagère portant ce nom. » ; positions via `OrderedPositions`. Copie du patron `BookshelfService` (verrou et traduction d'exception restent propres à chaque module).
- `app/Policies/Garage/ToolShelfPolicy.php` : `view` ouvert à tout connecté (F11) ; `update`, `delete`, `move` propriétaire seulement, sinon `Response::denyAsNotFound()`.
- `app/Rules/Garage/UniqueToolShelfName.php` (compare `normalized_name`, parmi les étagères du garage seulement) ; `app/Enums/Garage/ToolShelfMoveDirection.php` (`offset()`).
- `app/Http/Requests/Garage/{ToolShelfNameRequest (abstraite),StoreToolShelfRequest,UpdateToolShelfRequest,MoveToolShelfRequest}.php` (`Gate::inspect` dans `authorize()` pour update et move).
- `app/Http/Controllers/Garage/{GarageController,ToolShelfController,ToolShelfPositionController}.php` : `GET /garage` (`garage.index`) redirige vers `garage.tools.index` (« Mes outils », depuis F10) ; resource `garage.shelves.{index,store,update,destroy}` (paramètre `{shelf}`) + `PATCH /garage/shelves/{shelf}/move` (`garage.shelves.move`). Toutes dans `$authenticatedRoutes` de Ziggy.
- `app/Http/Resources/Garage/ToolShelfResource.php` : `id`, `name`, `position`.
- Front : `pages/Garage/Shelves/Index.vue` (« Mon garage », fil d'Ariane Garage › Étagères, bouton « Mes outils », lien « Voir les outils » par étagère, état vide « Votre garage est vide. », monter/descendre bloqués pendant la requête), `components/Garage/{ToolShelfRenameModal,ToolShelfDeleteModal}.vue`, `types/garage.ts` (`ToolShelf`, `ToolShelfMoveDirection`), `navigation/modules/garage.ts` (« Garage », `i-lucide-warehouse`, `garage.index`, `requiredRole: 'user'`, après Bibliothèque).
- Recherche (F12) : `app/Services/Garage/ToolSearchSource.php` (groupe `tools`, `ToolPhotoService` pour l'image ; nom, marque, accessoires ; URL `garage.tools.show`).
- Tests : `tests/Feature/Garage/*` (`ToolShelf{Access,Index,NameUniqueness,ServiceLock,Service}Test`, `{Store,Update,Destroy,Move}ToolShelfTest`, `GarageRouteListTest`, `ShelfModulesIsolationTest` : un id d'étagère de l'autre module = 404 des deux côtés), `tests/Unit/Garage/ToolShelfPolicyTest.php`, Vitest `pages/Garage/Shelves/Index.test.ts`, `layouts/DashboardLayout.garage.test.ts`.

#### Garage : outils (F10)
- `brands` (partagée par toute la famille, jamais supprimée) : `id`, `name` (100, casse du premier qui l'a saisie), `normalized_name` (100, unique, `utf8mb4_bin` sous MySQL). `tools` : `id`, `user_id` (FK cascade), `name` (255), `brand_id` (FK nulle, `restrictOnDelete`), index `user_id`+`name`. `tool_tool_shelf` : `tool_id`, `tool_shelf_id` (FK cascade des deux côtés, clé primaire composite) ; supprimer une étagère ou un outil ne retire que les liens. `tool_photos` : `tool_id` (cascade), `path`, `position` (1..n). `tool_accessories` : `tool_id` (cascade), `name` (100), `position` (1..n). Migration `database/migrations/2026_11_20_000000_create_tools_tables.php`. La cascade SQL ne supprime pas les fichiers des photos.
- `app/Models/Garage/{Tool,Brand,ToolPhoto,ToolAccessory}.php` : `Tool` `#[Fillable(['name'])]` (ni `user_id` ni `brand_id`), `#[UsePolicy]`, `#[UseFactory]`, `brand()`, `shelves()` (par position), `photos()` et `accessories()` (par position), `thumbnail()` = `hasOne()->oldestOfMany('position')` (une requête en chargement anticipé), `isOwnedBy()` ; `Brand::normalizeName()` + mutateur de `name` ; `ToolShelf::tools()`, `User::tools()`. Fabriques `database/factories/Garage/{Tool,Brand}Factory.php`.
- `app/Services/Garage/ToolService.php` : `listFor(User, ?ToolShelf, ?Brand)` (`brand`, `shelves`, `thumbnail` ; tri nom puis id), `brandsOf(User)` (marques de ses outils, pour le filtre), `create`, `update`, `delete`. `ToolInput` (readonly : `name`, `brand`, `shelfIds`, `accessories`, `photos` = ids à garder ou fichiers neufs, dans l'ordre).
- `app/Services/Garage/BrandService.php` : `all()` (choix du formulaire), `resolve(?string)` (marque existante par `normalized_name`, sinon créée ; `null` si vide ; course d'unicité rattrapée, voir pièges). `ToolPhotoService` : façade de `ResizedJpegStorage` (`DIRECTORY` `tools`, `MAX_WIDTH` 1200 ; `store`, `delete(iterable)`, `url`).
- `app/Http/Requests/Garage/ToolRequest.php` (abstraite : champs partagés, trim, `toolInput()`, constantes `MAX_PHOTOS` 8, `MAX_ACCESSORIES` 30, longueurs ; `keptPhotoRules()` abstraite), `StoreToolRequest` (`photos.*.id` interdit), `UpdateToolRequest` (`Gate::inspect('update')` dans `authorize()`, `photos.*.id` = photo de cet outil), `ListToolsRequest` (`shelfFilter()`, `brandFilter()` : inconnu, non entier, étagère d'un autre ou marque sans outil du membre = 404).
- `app/Policies/Garage/ToolPolicy.php` : `view` ouvert à tout connecté (F11) ; `update`, `delete` propriétaire seulement, sinon `denyAsNotFound()`.
- `app/Http/Controllers/Garage/ToolController.php` : resource complète `garage.tools.*` (paramètre `{tool}`), toast puis fiche après création/modification, liste après suppression ; `show` lisible par tout membre, avec `owner` et `editable` comme pour les livres ; routes dans `$authenticatedRoutes` de Ziggy. Resources `ToolResource` (`id`, `name`, `brand`, `thumbnail_url`, `shelves`), `ToolDetailsResource` (+ `photos` `{id,url}`, `accessories` `{id,name}`), `BrandResource`.
- Front : `pages/Garage/Tools/{Index,Create,Edit,Show}.vue` (Show : `owner`/`editable` comme les livres ; Index : cartes + filtres étagère/marque), `components/Garage/{ToolCard,ToolThumbnail,ToolForm,ToolPhotosField,ToolAccessoriesField,ToolDeleteModal}.vue`, `support/garage/toolFormRows.ts` (lignes de photos/accessoires du formulaire), types `Tool`, `ToolDetails`, `Brand`, `ToolFilters`… dans `types/garage.ts`. Marque : `USelectMenu` avec `create-item`. Modification : `POST` + `_method=patch`. `data-testid` : `brand-select`, `shelf-select`, `accessory-add`, `photo-row`, `tool`, `tool-brand`.
- Tests : `tests/Feature/Garage/{ToolAccess,ToolIndex,ToolPages,StoreTool,UpdateTool,DestroyTool,BrandService,ToolPhotoService}Test.php`, `tests/Unit/Garage/ToolPolicyTest.php`, Vitest `pages/Garage/Tools/{Index,Show}.test.ts`, `components/Garage/ToolForm.test.ts`, `support/garage/toolFormRows.test.ts`, Playwright `tests/e2e/tool.spec.ts` (étagère, puis outil avec marque créée, accessoire, étagère, photo ; vignette réellement servie). Helpers `tests/Pest.php` : `photoWithExif()`, `truncatedJpeg()`, `toolWithPhotos()`, `toolPhotoPaths()`, `toolAccessoryNames()`.

### Fichiers de test partagés
- `tests/Pest.php` : `inertiaHeaders()`, `bookshelfNames(User)` et `toolShelfNames(User)` (vérifient aussi les positions 1..n), `signIn()`, `transparentPng(w, h)`, `jpegBytes(w, h)`, `storedImage($path)` (largeur, hauteur, MIME d'une image du disque `public`), `photoWithExif()`, `truncatedJpeg()`, `toolWithPhotos()`, `toolPhotoPaths()`, `toolAccessoryNames()`, `googleVolume()` / `googleSearch()` (réponses Google factices) ; `RefreshDatabase` sur `Feature`.
- `tests/TestCase.php` : `withoutVite()` + remise à zéro de Ziggy avant chaque test.
- `resources/js/test/{mountWithUi,setup,testPage}.ts` : voir recettes.

## Recettes (comment on fait ici)

### Ajouter une page Inertia protégée
1. Contrôleur `app/Http/Controllers/<Module>/XxxController.php` : `Inertia::render('<Module>/Xxx/Index', [...])`, données via une `JsonResource` + `->resolve()`.
2. Route dans le groupe `Route::middleware('auth')` de `routes/web.php` (nommée, préfixe et nom par module, comme `library.`).
3. Page `resources/js/pages/<Module>/Xxx/Index.vue` : `<DashboardLayout title="…" :breadcrumb="[…]">`, `defineProps<{…}>()` typé depuis `resources/js/types/<module>.ts`.
4. Exposer la route à Ziggy (recette suivante), puis ajouter la route de test dans `resources/js/test/setup.ts` (`globalThis.Ziggy.routes`).

### Ajouter une route et l'exposer à Ziggy
1. Ajouter le nom dans `config/ziggy.php` : `$guestRoutes` (visiteurs), `$authenticatedRoutes` (connectés, admins compris) ou `$adminRoutes` (admin seulement). `only` = union guest + auth, jamais d'admin.
2. Côté front : `route('nom', { param: id }, false)` (URL relative, `ziggy-js`).
3. Dans les tests Vitest, déclarer la route dans `resources/js/test/setup.ts`.
4. Test Pest d'exposition sur le modèle de `tests/Feature/Library/LibraryRouteListTest.php` (visible aux connectés, absente pour un invité, bon groupe) ou `tests/Feature/Admin/AdminRouteListTest.php` (admin).

### Ajouter une entrée de menu
1. Créer `resources/js/navigation/modules/<module>.ts` (`NavigationEntry` : `label`, `icon` `i-lucide-…`, `route` nommée, `requiredRole`) et l'ajouter à `navigationRegistry` dans `registry.ts`.
2. `requiredRole: 'user'` = tout membre connecté (admins inclus) ; `'admin'` = admins ; `null` = tout le monde (même invité, à éviter).
3. Pointer vers l'entrée du module (`library.index`, `garage.index` redirigent) pour rester actif sur toutes les sous-pages (`isActiveUrl`).
4. Mettre à jour `registry.test.ts` et `DashboardLayout.roles.test.ts` (ils comptent les entrées) ; ajouter un `DashboardLayout.<module>.test.ts`.
5. Les icônes Lucide sont embarquées au build (`@iconify-json/lucide`) : aucune requête Iconify à l'exécution.

### Exposer une page en lecture à tous les membres (F11)
1. Policy : `view` renvoie `Response::allow()` (la signature `User` exclut déjà l'invité) ; `update`/`delete`/`move` restent `ownerOnly` (404). Ne pas créer de Policy « consultation » séparée.
2. Le contrôleur de lecture garde `Gate::authorize('view', $x)` ; la fiche reçoit `owner` (`MemberResource`) et `editable` (`Gate::allows('update', $x)`) : le front n'affiche les actions que si `editable`.
3. Données d'un autre membre : Resources existantes uniquement (aucun email, `user_id`, chemin de fichier, horodatage) ; `MemberResource` pour la personne ; `withCount` ou chargement anticipé, jamais de N+1.
4. Garder un test « toute écriture d'un autre = 404, admin compris » route par route (modèle : `tests/Feature/Core/ReadOnlyFamilyTest.php`).
5. Page de membre : filtres bornés au membre affiché (`ShowMemberRequest`), liste de membres par `User::activated()`.

### Ajouter une Policy avec 404 pour un autre utilisateur
1. `app/Policies/<Module>/XxxPolicy.php` : méthodes qui retournent `Response::allow()` ou `Response::denyAsNotFound()` (modèle : `BookshelfPolicy`). Lier avec `#[UsePolicy(XxxPolicy::class)]` sur le modèle.
2. Form Request de modification : `authorize(): Response { return Gate::inspect('update', $this->route('xxx')); }` : le 404 passe avant la validation.
3. Sans Form Request (suppression) : `Gate::authorize('delete', $xxx)` dans le contrôleur.
4. Tests : `tests/Unit/<Module>/XxxPolicyTest.php` + test Feature « autre utilisateur et admin = 404, rien ne change » (voir `BookshelfAccessTest`).

### Ajouter un limiteur
1. Déclarer dans `RateLimitServiceProvider::boot()` avec `$this->perClient(Limit::perMinute(n), $request, MESSAGE)` : clé `ClientNetwork::rateLimitKey()`, réponse `back()->withErrors(['email' => …])` (pas de 429 brut), jamais `withInput`.
2. Appliquer `->middleware('throttle:nom')` sur la route.
3. Tester : au-delà du quota, message français, rien enregistré ; autre IP autorisée ; IPv6 d'un même /64 partage le quota.

### Envoyer une notification française en file d'attente
1. `app/Notifications/<Module>/XxxNotification.php` : `extends Notification implements ShouldQueue`, `use Queueable`, `via()` = `['mail']`, `toMail()` avec `MailMessage` en français (sujet, `greeting`, `action`, `salutation`) ; pas de fichier de traduction.
2. Dans une transaction : `$this->afterCommit()` dans le constructeur (modèle : `SetPasswordNotification`).
3. Contenu saisi par un visiteur : le neutraliser (voir `AccountRequestedNotification`).
4. Test unitaire : `->toMail($user)`, sujet, `render()` contient le français et pas « Hello » ni « Regards » ; `ShouldQueue` ; test Feature avec `Notification::fake()`.

### Afficher un toast
1. Serveur : `Inertia::flash('toast', ['type' => 'success'|'error', 'message' => '…'])` puis `to_route(...)`.
2. Front : rien à faire, `DashboardLayout` monte `useFlashToasts()`. Les pages d'auth (`AuthLayout`) n'ont pas de toast : utiliser `with('status', …)` et l'`UAlert` de la page.

### Appeler une API externe en sécurité (F07, modèle : Google Books)
1. Config dans `config/services.php` (clé, `base_url`, délais, liste d'hôtes) ; `config()` partout, clé optionnelle ajoutée seulement si non vide (`withKey()`).
2. Client `Http::baseUrl()->withoutRedirecting()->timeout()->connectTimeout()->acceptJson()` : une redirection est une réponse inattendue, jamais suivie (elle enverrait la clé ailleurs).
3. Appel par le serveur seulement : le navigateur n'envoie qu'un identifiant validé (regex), le serveur relit la donnée de confiance (cache puis API) et ignore tout champ « données » posté.
4. Toute réponse inattendue (JSON invalide, type de champ faux, champ requis absent) lève une exception de domaine avec un statut ; l'appelant la traduit en message français (503) sans exposer le détail.
5. Journaliser chaque appel (`GoogleBooksJournal`, succès comme échec, y compris les refus avant appel) : type, requête sans secret, statut, code HTTP, durée, membre.
6. Deux limiteurs : un de route par membre (JSON 429) et un budget pour les appels faits pendant un enregistrement (`GoogleBooksSaveLimiter`).
7. Télécharger un fichier distant : URL vérifiée par une liste d'hôtes exacts (`GoogleBooksCoverPolicy`), redirections suivies à la main (3 max, mêmes règles), taille bornée de trois façons, type lu dans les octets, puis `BookCoverService::store()`. Un échec ne bloque pas l'enregistrement.
8. Tests : `Http::preventStrayRequests()` + `Http::fake()` dans chaque fichier, clé et en-têtes vérifiés avec `Http::assertSent`.

### Ajouter une source à la recherche globale (F12)
1. Écrire `app/Services/<Module>/XxxSearchSource.php` qui implémente `App\Services\Core\Search\SearchSource` : `key()` (identifiant du groupe), `label()` (intitulé français), `icon()` (`i-lucide-…`, pour les résultats sans image), `search(SearchTerm $term, User $searcher, int $limit)`.
2. Dans `search()` : requête Eloquent avec `select` minimal et `with()` des relations affichées, puis `$term->ranked($query, '<colonne libellé>', fn (Builder $any) => …conditions « ou »…, $limit)` ; conditions par `$term->whereContains($any, 'colonne', 'or')` (jamais `whereLike` ni `like` à la main : les jokers ne seraient pas échappés), relations par `orWhereHas`.
3. Mapper vers `new SearchResult(type, id, label, description, route('…show', $x, false), image)` : données minimales (ni email, ni données brutes, ni chemin de fichier : URL publique seulement) ; respecter ce que le membre a le droit de voir.
4. Enregistrer la classe dans `SearchServiceProvider::boot()` (l'ordre = ordre des groupes). Rien à changer côté front : intitulé, icône et URL viennent du serveur.
5. Tests sur le modèle de `BookSearchSourceTest` (champs, casse, jokers, tri, limite, forme du résultat) ; ajuster `SearchRegistryTest` (liste des clés) et la forme attendue dans `SearchEndpointTest` si le terme y correspond.

### Écrire un test Pest
- Utiliser `Pest\Laravel\get|post|patch|delete|actingAs` et `signIn()` (pas `$this->get()`).
- Rendu Inertia : `get(route(...))->assertInertia(...)` ; requête Inertia : passer `inertiaHeaders()` en en-têtes.
- Positions d'étagères : `bookshelfNames($user)`, `toolShelfNames($user)`. Mails : `Notification::fake()` ; files : `Queue::fake()` ; temps : `freezeTime()`.
- Fabriques : `User::factory()->admin()`, `->invited()`, `Bookshelf::factory()->for($user)`, `ToolShelf::factory()->for($user)`, `Book::factory()->for($user)` (`->withCover()`), `Tool::factory()->for($user)`, `Brand::factory()`, `AccountRequest::factory()->approved()`.

### Écrire un test Vitest
- Monter avec `mountWithUi(Composant, { props, attachTo: document.body })` (renvoie le wrapper du composant testé).
- Utilisateur connecté : `testPage.props.auth.user = testUser` ; changer l'URL : `testPage.url = '/library/bookshelves'` (réinitialisés après chaque test).
- Requêtes : `vi.spyOn(router, 'post'|'patch'|'delete').mockImplementation((url, data, options) => { options?.onSuccess?.(...) })`, URL relatives (`/library/bookshelves/9`).
- Modales (`UModal` téléportée) : chercher dans `document.body`, pas dans le wrapper ; `vi.waitFor` pour l'ouverture.
- Un composant avec logique a son `.test.ts` à côté ; ESLint + `vue-tsc` couvrent les tests.

### Ajouter un formulaire Inertia
- Page : `useForm({...})`, `form.post(route('nom', undefined, false), { preserveScroll: true, onSuccess: () => form.reset() })`, erreurs serveur `form.errors.champ` passées à `UFormField :error` (ou `fields[].error` de `UAuthForm`).
- Form Request dans `app/Http/Requests/<Module>/`, `prepareForValidation()` pour trim ; noms de champs français dans `lang/fr/validation.php` (`attributes`).
- Modèle de modale : `components/Library/BookshelfRenameModal.vue` (`v-model:open`, prop nullable, `watch` pour préremplir).

### Uploader une image (F06, F10)
- Validation : `ImageUploadRules::rules()` (`app/Support/ImageUploadRules.php` : `File::image()->types([...])->max(Ko)` + `Rule::dimensions()` 6000 px, qui borne la mémoire de GD) ; modèles `BookRequest` (`cover`) et `ToolRequest` (`photos.*.file`). Pour un champ nullable : `['nullable', ...ImageUploadRules::rules()]`.
- Stockage : `ResizedJpegStorage::store($file, '<dossier>', <largeurMax>)` (`app/Services/Core/`) : GD natif, réduction sans agrandir **avant** la rotation EXIF (largeur cible = largeur après rotation), décodage selon le type MIME lu, JPEG sur fond blanc donc sans EXIF, nom aléatoire, disque `public`. Ne pas le réécrire : créer une façade par module qui traduit `UnreadableImageException` en `ValidationException` sur son champ (modèles `BookCoverService` : `covers`, 800 px ; `ToolPhotoService` : `tools`, 1200 px).
- Cycle de vie : fichier écrit avant la transaction, supprimé si elle échoue ; ancien fichier supprimé après le commit (`BookService::withNewCover`, `ToolService::withNewPhotos`). La cascade SQL ne supprime pas les fichiers.
- Affichage : `cover_url` / `thumbnail_url` / `photos[].url` via les Resources ; en local, `php artisan storage:link` est nécessaire (l'image prod le fait, `scripts/prepare-e2e.sh` aussi). Sans image : bloc `UIcon` neutre (`BookCover.vue`, `ToolThumbnail.vue`).
- Front : `UFileUpload` (`v-model` `File | null`, ou `multiple` pour plusieurs) dans un `useForm` : Inertia passe en FormData tout seul ; une modification avec fichier part en `form.transform(d => ({ ...d, _method: 'patch' })).post(...)` (PHP ne lit pas le multipart en PATCH).
- Liste ordonnée mêlant éléments existants et neufs (photos d'outil) : `photos[i][id]` (existant, vérifié appartenir à **cet** outil) ou `photos[i][file]` ; l'ordre posté fait foi, les absents sont supprimés.
- Tests : `Storage::fake('public')`, `UploadedFile::fake()->image()`, helpers `storedImage()`, `transparentPng()`, `photoWithExif()`, `truncatedJpeg()` de `tests/Pest.php` ; Vitest (happy-dom) : définir `URL.createObjectURL` pour l'aperçu (absent), le retirer en `afterEach`.

### Verrou et courses (écritures sensibles)
- `DB::transaction` + `Model::query()->lockForUpdate()->findOrFail()` puis revérifier l'état sur le modèle relu (modèle : `AccountRequestReviewService::lockPending`).
- Données d'un membre (étagères, ordre) : verrouiller la ligne `users` du propriétaire (`User::query()->whereKey($id)->lockForUpdate()->firstOrFail()`) au début de **chaque** écriture ; c'est le verrou commun, qui existe même quand le membre n'a encore aucune ligne (verrouiller les étagères ne verrouille rien à la première création). Modèles : `BookshelfService::writingFor`, `ToolShelfService::writingFor`.
- Renuméroter des positions sous index unique : `OrderedPositions::save()` (déplace d'abord les lignes qui changent au-dessus de toutes les positions, puis les pose) et `OrderedPositions::swapWithNeighbour()` pour monter/descendre ; ne pas réécrire cette logique.
- Une `UniqueConstraintViolationException` devient une `ValidationException` française sur le champ (seulement si c'est l'index attendu, sinon la relancer).
- Test du verrou : écouter les requêtes (`DB::listen`) et vérifier que la première est le `select` sur `users` (`for update` vérifié sous MySQL seulement : SQLite l'omet).

## Décisions et conventions

- Tests : Vitest tourne sous **happy-dom** (`vitest.config.ts`), pas jsdom ; Pest en SQLite mémoire (`phpunit.xml`), file `sync`, mail `array` ; CI backend sur MySQL ; pas de build Vite requis (F00).
- Aucun fichier de traduction : textes d'interface en français en dur ; seuls les messages Laravel sont dans `lang/fr/` (F00).
- Ziggy en liste d'autorisation par groupe ; la liste dépend de la connexion et du rôle, d'où le rechargement complet (`Inertia::location`) à la connexion/déconnexion et quand l'état d'authentification change (F02).
- Un admin est aussi un membre : `rolesOf('admin')` = `['admin','user']` (F02).
- Pas de Policy pour l'admin : Gate `admin` + `can:admin` sur le groupe de routes (F04).
- Anti-énumération : réponses identiques pour email connu/inconnu (mot de passe oublié, réinitialisation, demande de compte) ; décision dans un job, pas dans la requête HTTP (F02, F03).
- Invitation = URL signée temporaire (7 jours, `famapp.invitation_expire_days`), usage unique via `password_set_at` ; pas de broker supplémentaire (F04).
- Toasts : flash Inertia `toast` (`success|error`), non conservé dans l'historique (F04).
- Un accès à la ressource d'un autre membre répond 404, pas 403, admins compris (F05).
- Positions d'ordre : 1..n compactes, renumérotées à chaque création, suppression ou déplacement sous verrou ; déplacement par pas (monter/descendre), pas de glisser-déposer (F05).
- Unicité d'un nom libre : colonne `normalized_name` (trim, espaces multiples réduits, minuscules ; accents **conservés** : « Café » ≠ « Cafe ») calculée par le modèle, index unique dessus, collation `utf8mb4_bin` sous MySQL ; la règle de validation compare cette colonne (F05).
- Même nom autorisé entre bibliothèque et garage : `bookshelves` et `tool_shelves` sont deux espaces de noms indépendants, l'unicité ne vaut que parmi les étagères du même module et du même membre (F09).
- Code partagé entre modules : seulement du générique sans état dans `app/Support/` (`NormalizedName`, `OrderedPositions`), extrait à la deuxième copie (F09) ; service, requests, règle, enum, contrôleurs, page et modales restent dupliqués par module (textes, routes et modèles propres).
- Contrôleurs : retour sur la liste avec toast ; `/library` est l'entrée du module et redirige, depuis F06 vers `library.books.index` (« Mes livres ») et non plus vers les étagères (F05, F06).
- Doublons d'ISBN autorisés, même chez un seul membre (choix de l'utilisateur) : aucun index unique ni validation d'unicité sur `isbn` ni sur le titre (F06).
- ISBN stocké normalisé (chiffres, `X` final majuscule, sans tirets ni espaces), clé de contrôle vérifiée (F06).
- Couvertures : JPEG réencodé, 800 px max, sans EXIF ; limites 10 Mo et 6000 px de côté, types JPEG/PNG/WebP seulement (pas de GIF ni SVG) (F06).
- Livre : liste triée par titre puis id ; filtre `?bookshelf=<id>` sur une étagère inconnue ou d'un autre = 404 ; étagère d'un autre dans `bookshelf_ids` = erreur de validation (n'en révèle pas l'existence) (F06).
- Après création ou modification : retour sur la fiche ; après suppression : retour sur la liste (F06).
- Google Books : on retient le **premier volume** de `q=isbn:<isbn>` ; pas de choix entre résultats ni de recherche par titre (F07).
- Le navigateur n'envoie que `google_volume_id` ; `google_books_data` vient toujours du serveur (cache 30 min ou nouvel appel) (F07).
- Préremplissage : jamais d'écrasement d'une saisie différente ; l'encart « Remplacer » / « Garder ma saisie » décide, le lien au volume et la couverture proposée restent dans les deux cas (F07).
- Couverture Google en échec : non bloquante (livre enregistré sans couverture, ancienne gardée en modification, toast complété) ; un fichier envoyé gagne toujours sur la couverture Google (F07).
- Volume non confirmable à l'enregistrement (Google en panne, budget épuisé) : enregistrement refusé avec erreur sur `google_volume_id` ; fermer l'encart Google permet d'enregistrer à la main (F07).
- Sans `google_volume_id`, les données brutes existantes sont conservées ; un nouveau volume les remplace (F07).
- Données Google et URL de couverture distantes jamais exposées au front ni stockées : seule la réponse du endpoint `{volume_id, title, author, cover_url}` sort (F07).
- Scan EAN-13 : dépendance `barcode-detector` (API standard `BarcodeDetector`, natif préféré) ; le `.wasm` zxing est servi par notre build (`zxing-wasm/reader/zxing_reader.wasm?url`, chargé à la demande), **aucun CDN** : override `locateFile`, plugin Vite et `scripts/check-build-no-cdn.mjs` en défense ; une mise à jour de la bibliothèque qui change l'URL de repli ou le `.wasm` fait échouer le build, à traiter plutôt que contourner (F08).
- Scan : seul un EAN-13 de livre (978/979, clé juste) est accepté, un autre code est ignoré avec un message discret ; l'image d'une photo est décodée dans le navigateur, jamais envoyée au serveur ; un scan valide remplit l'ISBN et lance seul la recherche Google Books (F08).
- Marques d'outils : liste **partagée** par toute la famille (table `brands`, hors compte), jamais supprimée (`restrictOnDelete`), pas d'écran de gestion avant nouvel ordre ; une marque saisie est comparée par `normalized_name` (« bosch » = « BOSCH  »), la première saisie fixe la casse ; elle est créée **dans la transaction de l'outil**, après l'écriture des photos (F10).
- Accessoires d'un outil : simples noms sans identité côté serveur, **remplacés en bloc** à chaque modification (suppression puis recréation, positions 1..n) ; 30 au plus, 100 caractères (F10).
- Photos d'un outil : 8 au plus, 1200 px de large, JPEG sans EXIF, la première est la vignette (même fichier) ; mêmes limites d'envoi que les couvertures (10 Mo, 6000 px, JPEG/PNG/WebP) ; ordre par boutons monter/descendre (F10).
- Outil : nom 255, marque 100 ; liste triée par nom puis id ; filtres `?shelf=` et `?brand=` combinables, 404 si inconnus ou hors du périmètre du membre ; étagère d'un autre membre ou de la bibliothèque dans `shelf_ids` = erreur de validation (F10).
- Un module Garage ne partage que le générique : l'image (`ResizedJpegStorage`, `ImageUploadRules`) est extraite à la deuxième utilisation (F10), `BookCoverService` garde son API (F10).
- Consultation (F11) : `view` des quatre Policies (livre, outil, étagère de bibliothèque, étagère de garage) est ouvert à tout connecté, admins compris ; l'écriture reste 404 pour tout non-propriétaire, admins compris, y compris `edit` (l'accès en lecture ne doit pas ouvrir un formulaire).
- Seuls les comptes **activés** (`password_set_at` non nul) sont des membres de la famille : une invitation non utilisée ou expirée est absente de « La famille » et sa page répond 404 ; elle réapparaît dès que le mot de passe est défini (F11).
- Données minimales d'un membre pour les autres : `id` et `name` seulement, jamais d'email ; liste avec nombres de livres et d'outils ; aucun horodatage ni identifiant interne (F11).
- Page d'un membre : une seule route, onglets côté client (`?tab=garage` au chargement), filtres par étagère seulement (pas de filtre par marque), pas de pagination ; sur sa propre page, même vue en lecture seule + liens vers « Mes livres » / « Mes outils » (F11).
- Code de consultation dans le module `Core` (il agrège Library et Garage) ; réutilise les services `listFor` et les Resources des modules (F11).
- Recherche globale (F12) : un seul endpoint qui agrège une source par module (registre) ; **groupes (clé, intitulé, icône) et URL de chaque résultat fournis par le serveur**, le front n'a rien de propre à un module ; 5 résultats par groupe, 2 à 100 caractères ; « commence par » avant « contient » (libellé qui commence par le terme, puis toute autre correspondance, puis libellé et id) ; résultats de tous les membres ; membres activés seulement ; « chez vous » / « Vous » pour le chercheur ; pas d'étagères dans les résultats ; `LIKE` simple, sans moteur externe (ni Scout ni FULLTEXT).
- Casse et accents de la recherche : MySQL (`utf8mb4_unicode_ci`) ignore casse et accents (« eleve » trouve « Élève ») ; SQLite (tests locaux) ignore seulement la casse ASCII, **pas les accents**. Le test d'accents ne tourne que sous MySQL (CI) ; les autres tests utilisent de l'ASCII.
- `route()` du front vient de `ziggy-js` (`skip-route-function`), en URL relative (`false`) (F01).
- `Fortify` : fonctionnalité `resetPasswords()` uniquement ; routes d'auth écrites dans `routes/web.php` ; `laravel/passkeys` est présent mais inactif (F02).
- Pas de Playwright hors parcours clés ; `tests/e2e/{login,book,tool}.spec.ts` (connexion F02 ; étagère puis livre F06 ; étagère puis outil F10) ; job CI `e2e`.

## Pièges connus

### Sécurité et comportement
- Ne jamais exposer une route admin dans `only` ni dans le groupe `auth` de Ziggy (liste imprimée dans chaque page, visiteurs compris).
- Une session expirée sur une requête Inertia doit recevoir `Inertia::location(route('login'))` (409), pas un 302 : sinon page blanche avec la mauvaise liste Ziggy (`UnauthenticatedResponse`, `RedirectAuthenticatedUsers`).
- Énumération d'emails : ne pas distinguer « aucun utilisateur » / « jeton invalide » ; mêmes erreur et redirection. `ResetPasswordNotification` est en file pour que le temps de réponse ne révèle rien.
- Le temps de réponse de la demande de compte doit être identique dans tous les cas : le contrôleur ne fait que valider et mettre `SubmitAccountRequest` en file.
- Notification contenant une saisie visiteur : ramener sur une ligne, neutraliser HTML/Markdown par références numériques (`&#91;`), retirer `\p{Cf}` ; pas d'échappement par antislash (il reste visible dans la version texte).
- Limiteurs : clé `ClientNetwork::rateLimitKey()` (IPv6 par /64), pas `$request->ip()` ; pas de `withInput` dans les réponses de limiteur.
- Rôle jamais assignable en masse : l'affecter explicitement (`forceFill` / attribut). Rôle admin validé par `Rule::in(Role::assignableValues())`.
- Une Form Request de modification qui autorise via `Gate::inspect()` doit le faire dans `authorize()` : le 404 doit passer avant la validation.
- Deux admins ou deux clics : relire sous `lockForUpdate()` et revérifier l'état (un modèle périmé ne doit pas permettre un double traitement).
- Compte créé par admin : mot de passe aléatoire inconnu, `password_set_at` nul ; « Mot de passe oublié » ou la réinitialisation Fortify rendent l'invitation caduque (remplissent `password_set_at`).

- Collations : `utf8mb4_unicode_ci` (MySQL) ignore casse **et accents**, SQLite compare octet par octet (sensible à la casse) : un index unique sur un texte libre ne se comporte pas pareil en local et en prod. Indexer une colonne normalisée en PHP et la passer en `utf8mb4_bin` sous MySQL (`->collation()` seulement si le driver est `mysql` : SQLite ne connaît pas cette collation).

### Upload, couvertures et photos (relevés en relecture F06 et F10)
- Une rotation GD copie l'image : redimensionner **avant** de pivoter, sinon la mémoire double. Pour un quart de tour (EXIF 6 ou 8), la largeur cible est la hauteur de la source (4032×3024 → 800×1067).
- Écrire le fichier avant la transaction et le supprimer si elle échoue ; supprimer l'ancien fichier seulement après le commit (`BookService::withNewCover`). Tests : faire échouer `Book::saving` et vérifier le disque.
- Garder les bornes de dimensions (`Rule::dimensions()`) : sans elles GD peut épuiser la mémoire sur une image légère mais énorme.
- Photo source avec EXIF (GPS) : le réencodage GD le supprime, le test le vérifie ; ne pas stocker l'original.
- Un fichier ne part pas en PATCH (PHP ne lit pas le multipart) : `POST` + `_method=patch` ; `Update` doit rester testé avec fichier.
- La cascade SQL ne supprime pas les fichiers : tout nouveau chemin de suppression de livre (ou de compte) doit appeler `BookCoverService::delete`.
- `UploadedFile::fake()->image()` est opaque : `transparentPng()` pour tester le fond blanc.
- Étagère d'un autre dans `bookshelf_ids` / `shelf_ids` : `Rule::exists(...)->where('user_id', …)` obligatoire, sinon on range son objet sur l'étagère d'un autre.
- Suppression d'un outil : transaction, verrou de la ligne de l'outil, lecture des chemins, suppression, effacement des fichiers **après** le commit (une suppression en échec garde lignes et fichiers). Même verrou en modification (`ToolService::lock`).
- Un JPEG tronqué passe la validation (en-tête lisible) mais GD ne le décode pas : `UnreadableImageException` → erreur sur `photos` ; rien ne doit rester (ni marque, ni fichier).
- Id de photo posté pour un autre outil : refusé par la validation (`Rule::exists` limité à l'outil) ; ne jamais faire confiance à un id de ligne enfant.
- Taille des envois : 25 Mo pour tout le site (nginx `client_max_body_size`, PHP `post_max_size`), routes visiteurs comprises. **Toute nouvelle route d'upload volumineux doit avoir son propre bloc nginx** (modèle : `location ~ ^/garage/tools(/\d+)?$`, 90 Mo, F10) qui passe directement à `index.php` (pas de `try_files` : la taille est contrôlée dans la location qui lit le corps) et relève `post_max_size` par `PHP_VALUE`. Ne jamais relever la limite globale. `PHP_VALUE` reste dans le processus PHP-FPM pour les requêtes suivantes : n'y mettre que des valeurs sans effet ailleurs (`post_max_size`, déjà borné par nginx), pas `upload_max_filesize`.
- Donnée partagée créée pendant un enregistrement (marque d'outil) : la créer dans la transaction, après l'écriture des fichiers, pour qu'un échec ne laisse rien ; après une violation d'unicité, relire avec `lockForUpdate()` (une lecture simple en REPEATABLE READ ne voit pas la ligne de la requête concurrente).

### Appels externes et Google Books (relevés en relecture F07)
- SSRF : ne télécharger qu'en https, hôte exact de `cover_hosts`, sans identifiants ni port ; refuser aussi chaque redirection non conforme, **avant** toute requête vers l'hôte. Google liste ses images en `http://` : relever en https seulement pour un hôte autorisé.
- `withoutRedirecting()` sur le client API (sinon la clé suit la redirection) ; la clé n'apparaît ni dans le journal (ISBN, identifiant, hôte+chemin sans query string), ni dans une réponse, ni dans Ziggy ; ne pas journaliser ni rapporter l'exception de connexion ou l'URL complète d'une requête.
- Bombe gzip : télécharger avec `Accept-Encoding: identity` et `decode_content => false`, sinon le plafond de 2 Mo ne porte pas sur les octets réels ; contrôler `Content-Length`, l'option `progress` de Guzzle et la taille lue (seuls les deux derniers sont testables avec `Http::fake()`).
- MySQL réordonne les clés d'un JSON stocké : comparer `google_books_data` avec `toEqual`, jamais `toBe`, sinon vert en SQLite et rouge en CI.
- Type d'image lu dans les octets (`getimagesizefromstring`, JPEG/PNG/WebP, 6000 px) : ne pas se fier au `Content-Type` ; HTML, SVG et GIF refusés.
- Un appel qui part pendant un enregistrement (volume absent du cache, couverture) consomme `GoogleBooksSaveLimiter` ; un nouvel appel externe dans `BookService` doit faire de même.
- Toute exception autre que `ValidationException` à l'écriture de la couverture devient `storage_failed` (livre enregistré sans couverture) ; ne pas la laisser remonter en 500.
- `ConnectionException` (délai, DNS, refus) est journalisée `timeout`, un 404 `not_found` ; ne pas les confondre avec `invalid_response`.
- Tests : tout fichier Google Books pose `Http::preventStrayRequests()` ; un test de formulaire qui compare la charge utile exacte doit inclure `google_volume_id` et `use_google_cover` (deux tests de F06 complétés).

### Scanner de code-barres (relevés en relecture F08)
- Le polyfill `barcode-detector` avale l'échec de chargement du `.wasm` dans son constructeur : attendre `prepareZXingModule({ fireImmediately: true })` avant de le créer (`purgeZXingModule()` en cas d'échec pour permettre un nouvel essai), et traiter un rejet `NotSupportedError` de `detect()` comme « lecteur indisponible » (arrêter la caméra), jamais comme « aucun code ».
- Le plugin Vite qui retire l'URL jsDelivr de repli de zxing-wasm n'agit qu'au build ; en `npm run dev`, l'URL reste dans le code préempaqueté mais l'override `locateFile` sert le `.wasm` localement.
- `zxing-wasm` n'est pas déclaré dans `package.json` (dépendance transitive de `barcode-detector`, une seule copie avec npm) : ne pas en ajouter une autre version, `scripts/check-build-no-cdn.mjs` compare le `.wasm` émis à celui que la bibliothèque attend.
- Worktree avec `node_modules` en lien symbolique : Vite refuse l'import `zxing-wasm/reader/zxing_reader.wasm?url` et 3 tests de `barcodeDetector.test.ts` échouent ; utiliser un `node_modules` propre au worktree.
- Un `File` venant de `UFileUpload` (ou rangé dans un `ref`) est un proxy réactif refusé par les API natives : `shallowRef` + `toRaw()` avant `detect()`.
- Décodages asynchrones (caméra et photo) liés à un jeton de session incrémenté à chaque ouverture/fermeture : une réponse tardive ne doit rien émettre.

### Ziggy, Inertia, tests
- Le seul `whereRaw` du projet est dans `SearchTerm` (`<colonne> like ? escape '!'`, valeur liée, `%` `_` `!` échappés). Pour toute recherche texte : réutiliser `SearchTerm::whereContains()` / `whereStartsWith()` / `ranked()`, ne jamais concaténer la saisie dans du SQL ni dans un motif. `whereLike()` de Laravel n'a pas de clause `ESCAPE` (SQLite n'a pas d'échappement par défaut) : `%` et `_` saisis deviendraient des jokers. Ne pas choisir `\` comme échappement (traité différemment selon le moteur).
- `UDashboardSearch` vide son champ et se ferme à chaque choix ; ses groupes doivent porter `ignoreFilter: true` pour des résultats déjà filtrés par le serveur, sinon Fuse les refiltre côté client.
- MySQL ne remet pas les auto-incréments à zéro entre les tests (SQLite, si) : ne jamais supposer qu'un id existe ni qu'il est absent, ni que deux ids de tables différentes diffèrent ; créer les lignes voulues et utiliser leurs ids réels (tests de filtre de F11 corrigés en relecture).
- Test Pest d'une page : Ziggy imprime sa liste une fois par processus PHP ; `tests/TestCase.php` la réinitialise (`BladeRouteGenerator::$generated`, `Ziggy::clearRoutes()`), ne pas retirer.
- Requête Inertia dans un test sans `X-Inertia-Version` : le contrôle de version renvoie un 409 ; utiliser `inertiaHeaders()`.
- `$this->get()` est mal typé par Larastan dans les closures Pest : utiliser les fonctions `Pest\Laravel\*`.
- `usePage()` est mocké en Vitest (`test/setup.ts`) ; l'URL active se simule via `testPage.url`, pas via `route().current()`.
- Route utilisée par le front mais absente de `test/setup.ts` (Ziggy de test) : « route not found » dans Vitest.
- `UModal` est téléportée : ses boutons ne sont pas dans le wrapper, mais dans `document.body`.
- `UAuthForm` n'a pas de champ textarea et son slot dynamique `#<champ>-field` est mal typé : lier un `UTextarea` à `useForm` directement (`RequestAccount.vue`).
- `UDashboardSearch` ajoute un groupe « Thème » : désactivé par `:color-mode="false"`.
- `components.d.ts` et `auto-imports.d.ts` sont générés (unplugin) mais versionnés : ils changent après `npm run build` ou Vitest ; ne les éditer pas à la main, committer leur régénération seulement si une feature change les composants utilisés.
- Un changement de redirection (`/library`) casse des tests d'une feature précédente : les ajuster (jamais les retirer), comme `BookshelfIndexTest` et `BookshelfAccessTest` en F06.
- Les tests existants qui comptent les entrées de menu doivent être ajustés (jamais retirés) quand un module ajoute une entrée.
- Playwright : `scripts/prepare-e2e.sh` lance aussi `php artisan storage:link --force` : sans lui une image uploadée en e2e n'est pas servie (404 sur `/storage/...`) ; navigateurs non téléchargeables ici ; en local `PLAYWRIGHT_EXECUTABLE_PATH=/usr/bin/google-chrome`, la CI installe chromium.

### Environnement de travail des agents
- Utiliser `FETCH_HEAD` : le dépôt n'a pas de refspec de fetch, `origin/<branche>` n'existe pas ou est périmé (`git fetch origin <branche>` puis `git checkout -B <branche> FETCH_HEAD` ; push avec `git -c push.negotiate=true push origin HEAD:<branche>`).
- Ne jamais lancer `composer install` dans un worktree (disque limité) ; réutiliser `vendor/` du dépôt principal.
- Le quota d'API peut interrompre un agent en cours de tâche : garder des commits fréquents et un état de branche reprenable.
- Composer : `COMPOSER_ALLOW_SUPERUSER=1`, `--prefer-source` (archives GitHub refusées par le proxy) ; nouvelle version de `phpstan/phpstan` : `scripts/seed-phpstan-cache.sh <version>` avant Composer ; `config.platform.php` figé à 8.3.6 (PHP local 8.3, Docker et CI 8.4).
- Pas de démon Docker : les tests tournent directement en PHP/Node ; la CI construit l'image `prod`.
- Dockerfile : pas de `|| true` sur `composer install` (il masquait un échec) ; `.npmrc` et `COMPOSER_ALLOW_SUPERUSER` y sont nécessaires.
- Le Playwright local utilise `scripts/prepare-e2e.sh` (`.env.e2e` et `database/e2e.sqlite`, `APP_ENV=e2e`, mail `log`) sans toucher `.env`.

## Notes pour les features à venir

- Page membre (F11) : les deux onglets sont toujours calculés ; si le volume grandit, charger l'onglet inactif à la demande (`Inertia::optional`).
- F13 : la page de contrôle admin ira dans le groupe `admin` de Ziggy (`$adminRoutes`).
- F12 : `LIKE '%terme%'` ne se sert d'aucun index (seuls `user_id`+`title` / `user_id`+`name` existent) : acceptable pour une famille, à revoir si les volumes grandissent.
- F13 : page admin : compter aussi les recherches ? non requis (rien n'est journalisé côté recherche).
- F13 : le journal `google_books_requests` (F07, modèle `App\Models\Library\GoogleBooksRequest`, statuts `GoogleBooksRequestStatus`) grossit sans fin : le purger (`Prunable` + durée de conservation configurable, `model:prune` planifié) ; un index `(status, created_at)` sera probablement utile pour les filtres de la page de contrôle (seul `created_at` est indexé aujourd'hui).
- F14 : la caméra du scanner (F08) exige HTTPS (sauf `localhost`) ; nginx doit servir `.wasm` en `application/wasm` (sinon repli sans streaming, plus lent) ; configurer `trustProxies` (sinon tous les visiteurs partagent la même IP dans les limiteurs) ; les liens d'invitation signés exigent la bonne config proxy/HTTPS ; masquer `signature` dans les logs Nginx.
- Les comptes ne peuvent pas être supprimés aujourd'hui ; les étagères se suppriment en cascade avec le compte.
- Si un jour un compte peut être supprimé, supprimer aussi ses couvertures (`books.cover_path`) et ses photos d'outils (`tool_photos.path`) : la cascade SQL ne nettoie pas le disque.
