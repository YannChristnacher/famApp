# Mémoire partagée famApp
> À lire avant d'explorer le code. Mise à jour par l'agent scribe à chaque feature. Dernière mise à jour : F05.

## État du projet

- F00 socle (Laravel 13, Inertia, Vue + TS, Nuxt UI v4, Docker, CI) : terminée.
- F01 layout dashboard (sidebar à registre de modules, header, palette vide) : terminée.
- F02 authentification (Fortify, rôles admin/user, seeder admin, Playwright login) : terminée.
- F03 demande de compte publique (job, notification admin, limiteur) : terminée.
- F04 admin : liste des demandes, création du compte, invitation signée 7 jours, toasts flash : terminée.
- F05 bibliothèque : CRUD et ordre des étagères (PR #6) : en relecture, CI verte.
- Prochaine : F06 (livres en saisie manuelle). F09 (étagères du Garage) peut aussi démarrer (dépend de F02).
- Suite du backlog : `doc/BACKLOG.md` (source de vérité des statuts).

## Carte du code par module

### Core
- `app/Http/Controllers/Core/HomeController.php` : page `Core/Home`.
- `app/Http/Middleware/HandleInertiaRequests.php` : props partagées `auth.user` (`id`, `name`, `email`, `role`, rien d'autre) et `status`.
- `app/Providers/RateLimitServiceProvider.php` : tous les limiteurs (`login`, `password-reset-link`, `password-reset`, `account-request`) et leur réponse commune.
- `app/Support/ClientNetwork.php` : clé de limiteur (IPv4 telle quelle, IPv6 groupée par /64, IPv4 encapsulée ramenée à l'IPv4).
- `app/Support/ZiggyGroup.php` : groupes Ziggy imprimés selon l'utilisateur (`['guest']`, `['auth']`, `['auth','admin']`).
- `config/ziggy.php` : liste d'autorisation des routes du front (groupes `guest`, `auth`, `admin` + `only`).
- `config/famapp.php` : `admin.*` (`ADMIN_NAME|EMAIL|PASSWORD`), `invitation_expire_days` (7).
- `bootstrap/app.php` : alias middleware `guest` (remplacé) et `invitation`, `authenticateSessions()`, `UnauthenticatedResponse`.
- Front : `resources/js/app.ts`, `appRoot.ts` (`UApp` + locale `fr`, partagé avec les tests), `layouts/DashboardLayout.vue`, `layouts/AuthLayout.vue`.
- `navigation/registry.ts` (`navigationRegistry`, `filterEntriesByRoles`, `toNavigationMenuItems`, `isActiveUrl`), `navigation/types.ts`, `navigation/modules/{core,library,admin}.ts`.
- `composables/useAuth.ts` (`useAuth()`, `rolesOf()` : un admin a `['admin','user']`), `composables/useFlashToasts.ts` (monté par `DashboardLayout`).
- Types : `resources/js/types/{auth,flash,admin,library}.ts` ; `global.d.ts` typé `Ziggy`, `sharedPageProps`, `flashDataType`.
- `resources/views/vendor/{notifications,mail}/...` : gabarits d'email en français (tous les emails).
- Tests : `tests/Feature/Core/{HomeTest,LocaleTest}.php`, `tests/Unit/Core/ClientNetworkTest.php`.

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
- `app/Models/Library/Bookshelf.php` (`#[Fillable(['name'])]`, `#[UsePolicy]`, `isOwnedBy()`, `normalizeName()` : trim, espaces multiples réduits, minuscules `mb_strtolower`, accents conservés ; le mutateur de `name` remplit `normalized_name`), `User::bookshelves()` (triée position puis id).
- `app/Http/Controllers/Library/{LibraryController,BookshelfController,BookshelfPositionController}.php` : `/library` redirige vers `library.bookshelves.index`.
- `app/Http/Requests/Library/{BookshelfNameRequest,StoreBookshelfRequest,UpdateBookshelfRequest,MoveBookshelfRequest}.php` ; `app/Rules/Library/UniqueBookshelfName.php` (compare `normalized_name`, même normalisation que l'index) ; `app/Enums/Library/BookshelfMoveDirection.php`.
- `app/Policies/Library/BookshelfPolicy.php` : `update`, `delete`, `move` propriétaire seulement, sinon `Response::denyAsNotFound()`.
- `app/Services/Library/BookshelfService.php` : `listFor`, `create`, `rename`, `delete`, `move` ; chaque écriture verrouille d'abord la ligne `users` du propriétaire (`writingFor`), renumérotation 1..n en deux temps (compatible avec l'index unique des positions).
- `app/Http/Resources/Library/BookshelfResource.php` (`id`, `name`, `position`), factory `BookshelfFactory`.
- Front : `pages/Library/Bookshelves/Index.vue`, `components/Library/{BookshelfRenameModal,BookshelfDeleteModal}.vue`, `navigation/modules/library.ts`.
- Tests : `tests/Feature/Library/*` (dont `BookshelfNameUniquenessTest`, `BookshelfServiceLockTest`), `tests/Unit/Library/BookshelfPolicyTest.php`, `resources/js/pages/Library/Bookshelves/Index.test.ts`, `DashboardLayout.library.test.ts`.

### Garage
- Rien encore (F09, F10).

### Fichiers de test partagés
- `tests/Pest.php` : `inertiaHeaders()`, `bookshelfNames(User)` (vérifie aussi les positions 1..n), `signIn()` ; `RefreshDatabase` sur `Feature`.
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
3. Pointer vers l'entrée du module (`library.index` redirige) pour rester actif sur toutes les sous-pages (`isActiveUrl`).
4. Mettre à jour `registry.test.ts` et `DashboardLayout.roles.test.ts` (ils comptent les entrées) ; ajouter un `DashboardLayout.<module>.test.ts`.
5. Les icônes Lucide sont embarquées au build (`@iconify-json/lucide`) : aucune requête Iconify à l'exécution.

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

### Écrire un test Pest
- Utiliser `Pest\Laravel\get|post|patch|delete|actingAs` et `signIn()` (pas `$this->get()`).
- Rendu Inertia : `get(route(...))->assertInertia(...)` ; requête Inertia : passer `inertiaHeaders()` en en-têtes.
- Positions d'étagères : `bookshelfNames($user)`. Mails : `Notification::fake()` ; files : `Queue::fake()` ; temps : `freezeTime()`.
- Fabriques : `User::factory()->admin()`, `->invited()`, `Bookshelf::factory()->for($user)`, `AccountRequest::factory()->approved()`.

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

### Verrou et courses (écritures sensibles)
- `DB::transaction` + `Model::query()->lockForUpdate()->findOrFail()` puis revérifier l'état sur le modèle relu (modèle : `AccountRequestReviewService::lockPending`).
- Données d'un membre (étagères, ordre) : verrouiller la ligne `users` du propriétaire (`User::query()->whereKey($id)->lockForUpdate()->firstOrFail()`) au début de **chaque** écriture ; c'est le verrou commun, qui existe même quand le membre n'a encore aucune ligne (verrouiller les étagères ne verrouille rien à la première création). Modèle : `BookshelfService::writingFor`.
- Renuméroter des positions sous index unique : déplacer d'abord les lignes qui changent au-dessus de toutes les positions, puis les poser (`BookshelfService::savePositions`).
- Une `UniqueConstraintViolationException` devient une `ValidationException` française sur le champ (seulement si c'est l'index attendu, sinon la relancer).
- Test du verrou : écouter les requêtes (`DB::listen`) et vérifier que la première est le `select` sur `users` (`for update` vérifié sous MySQL seulement : SQLite l'omet).

## Décisions et conventions

- Tests : Pest en SQLite mémoire (`phpunit.xml`), file `sync`, mail `array` ; CI backend sur MySQL ; pas de build Vite requis (F00).
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
- Contrôleurs : retour sur la liste avec toast ; `/library` est l'entrée du module et redirige (F05).
- `route()` du front vient de `ziggy-js` (`skip-route-function`), en URL relative (`false`) (F01).
- `Fortify` : fonctionnalité `resetPasswords()` uniquement ; routes d'auth écrites dans `routes/web.php` ; `laravel/passkeys` est présent mais inactif (F02).
- Pas de Playwright hors parcours clés ; actuellement seulement `tests/e2e/login.spec.ts` (job CI `e2e`) ; l'ajout de livre arrive avec F06 (F02).

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

### Ziggy, Inertia, tests
- Test Pest d'une page : Ziggy imprime sa liste une fois par processus PHP ; `tests/TestCase.php` la réinitialise (`BladeRouteGenerator::$generated`, `Ziggy::clearRoutes()`), ne pas retirer.
- Requête Inertia dans un test sans `X-Inertia-Version` : le contrôle de version renvoie un 409 ; utiliser `inertiaHeaders()`.
- `$this->get()` est mal typé par Larastan dans les closures Pest : utiliser les fonctions `Pest\Laravel\*`.
- `usePage()` est mocké en Vitest (`test/setup.ts`) ; l'URL active se simule via `testPage.url`, pas via `route().current()`.
- Route utilisée par le front mais absente de `test/setup.ts` (Ziggy de test) : « route not found » dans Vitest.
- `UModal` est téléportée : ses boutons ne sont pas dans le wrapper, mais dans `document.body`.
- `UAuthForm` n'a pas de champ textarea et son slot dynamique `#<champ>-field` est mal typé : lier un `UTextarea` à `useForm` directement (`RequestAccount.vue`).
- `UDashboardSearch` ajoute un groupe « Thème » : désactivé par `:color-mode="false"`.
- `components.d.ts` et `auto-imports.d.ts` sont générés (unplugin) mais versionnés : ils changent après `npm run build` ou Vitest ; ne les éditer pas à la main, committer leur régénération seulement si une feature change les composants utilisés.
- Les tests existants qui comptent les entrées de menu doivent être ajustés (jamais retirés) quand un module ajoute une entrée.
- Playwright : navigateurs non téléchargeables ici ; en local `PLAYWRIGHT_EXECUTABLE_PATH=/usr/bin/google-chrome`, la CI installe chromium.

### Environnement de travail des agents
- Utiliser `FETCH_HEAD` : le dépôt n'a pas de refspec de fetch, `origin/<branche>` n'existe pas ou est périmé (`git fetch origin <branche>` puis `git checkout -B <branche> FETCH_HEAD` ; push avec `git -c push.negotiate=true push origin HEAD:<branche>`).
- Ne jamais lancer `composer install` dans un worktree (disque limité) ; réutiliser `vendor/` du dépôt principal.
- Le quota d'API peut interrompre un agent en cours de tâche : garder des commits fréquents et un état de branche reprenable.
- Composer : `COMPOSER_ALLOW_SUPERUSER=1`, `--prefer-source` (archives GitHub refusées par le proxy) ; nouvelle version de `phpstan/phpstan` : `scripts/seed-phpstan-cache.sh <version>` avant Composer ; `config.platform.php` figé à 8.3.6 (PHP local 8.3, Docker et CI 8.4).
- Pas de démon Docker : les tests tournent directement en PHP/Node ; la CI construit l'image `prod`.
- Dockerfile : pas de `|| true` sur `composer install` (il masquait un échec) ; `.npmrc` et `COMPOSER_ALLOW_SUPERUSER` y sont nécessaires.
- Le Playwright local utilise `scripts/prepare-e2e.sh` (`.env.e2e` et `database/e2e.sqlite`, `APP_ENV=e2e`, mail `log`) sans toucher `.env`.

## Notes pour les features à venir

- F06 : `Bookshelf` n'a pas de relation livres ; prévoir une table pivot `book_bookshelf` (étagères multiples) et recompter ce que la suppression d'une étagère fait aux livres (ils ne sont pas supprimés). Ajouter `library.books.*` au groupe `auth` de Ziggy ; `/library` redirige aujourd'hui vers les étagères, F06 peut en faire une vue d'ensemble. Premier upload : `FILESYSTEM_DISK=public` (déjà dans `.env.example`), nom de fichier généré, jamais celui du client.
- F06/F10 : la Policy du modèle appartenant à un membre suit `BookshelfPolicy` (404 pour un autre membre) ; la lecture seule des autres membres arrive en F11, avec ses propres Policies de consultation.
- F09 : le Garage copie le patron de F05 (`Garage/` : modèle, policy, service, requests, page, entrée de menu `navigation/modules/garage.ts`) ; l'ordre des étagères peut réutiliser la logique de position, à extraire seulement si le motif est dupliqué une deuxième fois.
- F12 : chaque module fournit sa source de recherche globale ; `UDashboardSearch` a `:groups="[]"` et un texte « arrive bientôt » dans `DashboardLayout.vue` à remplacer.
- F13 : la page de contrôle admin ira dans le groupe `admin` de Ziggy (`$adminRoutes`).
- F14 : configurer `trustProxies` (sinon tous les visiteurs partagent la même IP dans les limiteurs) ; les liens d'invitation signés exigent la bonne config proxy/HTTPS ; masquer `signature` dans les logs Nginx.
- Les comptes ne peuvent pas être supprimés aujourd'hui ; les étagères se suppriment en cascade avec le compte.
