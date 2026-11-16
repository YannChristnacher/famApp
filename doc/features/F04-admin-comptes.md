# Feature: F04 — Admin : demandes de compte et création du compte

## Overview
Donner à l'administrateur une page « Demandes de compte » (module Admin) qui liste les demandes reçues via le formulaire public de F03. Depuis une demande en attente, il crée le compte (nom, email, rôle) dans un formulaire prérempli ; le nouveau membre reçoit un email en français avec un lien pour définir son mot de passe, qui réutilise le mécanisme de réinitialisation de F02. L'admin peut aussi refuser une demande. Une demande déjà traitée ne peut plus l'être.

## Out of scope
- Modifier ou supprimer des comptes existants, liste des utilisateurs (non prévu).
- Page de contrôle admin avec compteurs (F13).
- Email au demandeur en cas de refus (refusé par le cadrage).
- Pagination et recherche dans la liste (volume familial).
- Parcours Playwright (réservé aux parcours clés).

## Acceptance criteria
- [x] AC1: Given un admin, when il demande `admin.account-requests.index`, `admin.account-requests.account.create` (demande en attente), poste `admin.account-requests.account.store` ou `admin.account-requests.reject`, then l'accès est autorisé ; given un `user`, then chaque route répond 403 et rien ne change ; given un invité, then chaque route redirige vers `login` et rien ne change.
- [x] AC2: Given des demandes `approved`, `rejected` et `pending` de dates variées, when l'admin ouvre la liste, then la page rend `Admin/AccountRequests/Index` avec les demandes (`id`, `name`, `email`, `message`, `status`, `createdAt` ISO 8601) triées : en attente d'abord, puis les plus récentes en premier ; aucune autre donnée n'est exposée.
- [x] AC3: Given une demande en attente, when l'admin ouvre `admin.account-requests.account.create`, then la page `Admin/AccountRequests/CreateAccount` reçoit la demande (`id`, `name`, `email`, `message`) et les rôles proposables (`user`, `admin`) ; given une demande déjà traitée, then il est redirigé vers la liste avec un toast d'erreur « Cette demande a déjà été traitée. ».
- [x] AC4: Given une demande en attente, when l'admin poste un nom, un email avec majuscules et espaces et le rôle `admin` (ou `user`), then un utilisateur est créé avec ce nom, l'email en minuscules sans espaces et ce rôle ; son mot de passe est un hachage d'une valeur aléatoire inconnue (ni vide, ni `password`, ni l'email) ; la demande passe à `approved` et est liée à l'utilisateur (`user_id`) ; l'admin est redirigé vers la liste avec le toast de succès « Le compte de <nom> a été créé. Un email lui a été envoyé pour définir son mot de passe. ».
- [x] AC5: Given la création d'un compte, then la notification `SetPasswordNotification` est envoyée au nouvel utilisateur ; elle implémente `ShouldQueue`, part après la validation de la transaction, passe par `mail`, a le sujet « Bienvenue sur famApp, définissez votre mot de passe », un contenu en français (salutation au nom de l'utilisateur, durée de validité, renvoi vers « Mot de passe oublié » si le lien a expiré) et un bouton « Définir mon mot de passe » vers `password.reset` avec un jeton valide et l'email.
- [x] AC6: Given le lien reçu dans la notification, when le nouvel utilisateur ouvre la page de réinitialisation puis poste un mot de passe confirmé, then le mot de passe est enregistré et il peut ensuite se connecter avec cet email et ce mot de passe.
- [x] AC7: Given le formulaire de création, when il est posté sans nom, sans email, avec un email invalide, un email déjà utilisé par un utilisateur (quelle que soit la casse), un nom ou un email de plus de 255 caractères, ou un rôle hors `user`/`admin`, then des erreurs françaises sont renvoyées sur le champ concerné, aucun utilisateur n'est créé, la demande reste `pending` et aucune notification n'est envoyée.
- [x] AC8: Given une demande en attente, when l'admin la refuse, then elle passe à `rejected`, aucun utilisateur n'est créé, aucune notification n'est envoyée, et l'admin est redirigé vers la liste avec le toast « La demande de <nom> a été refusée. ».
- [x] AC9: Given une demande `approved` ou `rejected`, when l'admin tente de l'accepter (création du compte) ou de la refuser, then rien ne change (statut, utilisateurs, notifications) et il est redirigé vers la liste avec le toast d'erreur « Cette demande a déjà été traitée. ». Given un échec pendant la création (exception), then ni utilisateur ni changement de statut ne subsistent (transaction).
- [x] AC10: Given la notification admin de F03, then son bouton « Voir les demandes de compte » pointe vers `admin.account-requests.index` et le texte « arrive bientôt » a disparu ; given un admin non connecté qui suit ce lien, then après connexion il arrive sur la page des demandes.
- [x] AC11: Given la liste Ziggy, when un admin charge une page, then les routes `admin.*` et les routes connectées (`home`, `logout`) sont exposées ; when un `user` charge une page, then aucune route `admin.*` n'est exposée ; when un invité charge une page, then non plus. Les groupes restent dans la liste autorisée.
- [x] AC12 (Vitest): Given la page `Admin/AccountRequests/Index` avec des demandes, when elle est rendue, then un `UTable` affiche nom, email, message, date (format français) et statut en français (« En attente », « Acceptée », « Refusée ») ; seules les demandes en attente ont les actions « Créer le compte » (lien vers le formulaire) et « Refuser » ; « Refuser » poste vers `admin.account-requests.reject` ; sans demande, un texte « Aucune demande de compte. » s'affiche.
- [x] AC13 (Vitest): Given la page `Admin/AccountRequests/CreateAccount`, when elle est rendue, then les champs « Nom », « Adresse email » et « Rôle » sont préremplis avec la demande (rôle `user` par défaut) et le message du demandeur est affiché ; when on modifie et soumet, then un `POST` vers `admin.account-requests.account.store` est envoyé avec `name`, `email`, `role` ; given des erreurs serveur, then elles s'affichent sous les champs.
- [x] AC14 (Vitest): Given le registre de navigation, then il contient l'entrée « Demandes de compte » (`admin.account-requests.index`, `requiredRole: 'admin'`) ; given le `DashboardLayout`, then l'entrée est visible pour un admin et invisible pour un user.
- [x] AC15 (Vitest): Given le `DashboardLayout` monté, when Inertia émet un flash `toast` (`{ type: 'success' | 'error', message }`), then un toast Nuxt UI avec ce message et la couleur correspondante est ajouté.
- [x] AC16: Given le code, when on lance `composer test`, `composer lint`, `npm run test`, `npm run lint`, `npm run build`, then tout passe, et la CI est verte.

## Technical approach
1. **Données** : migration `add_user_id_to_account_requests_table` (`user_id` nullable, clé étrangère vers `users`, `nullOnDelete`) ; relation `AccountRequest::user()` ; méthode `isPending()`.
2. **Routes** (`routes/web.php`) : groupe `auth` + `can:admin` (Gate existant), préfixe `/admin`, noms `admin.` :
   - `GET /admin/account-requests` → `Admin\AccountRequestController@index` (`admin.account-requests.index`)
   - `GET /admin/account-requests/{accountRequest}/account` → `Admin\AccountRequestAccountController@create` (`admin.account-requests.account.create`)
   - `POST /admin/account-requests/{accountRequest}/account` → `Admin\AccountRequestAccountController@store` (`admin.account-requests.account.store`)
   - `POST /admin/account-requests/{accountRequest}/reject` → `Admin\AccountRequestController@reject` (`admin.account-requests.reject`)
3. **Validation** : `App\Http\Requests\Admin\StoreAccountFromRequestRequest` — `name` requis ≤ 255, `email` requis, email, ≤ 255, `unique:users,email` ; `role` requis, `Rule::enum(Role::class)` ; `prepareForValidation` : trim + minuscules sur l'email. Attribut `role` traduit (« rôle ») dans `lang/fr/validation.php` si absent.
4. **Service** : `App\Services\Admin\AccountRequestReviewService`
   - `createAccount(AccountRequest, array $data): User` : dans `DB::transaction`, recharge la demande avec `lockForUpdate` et vérifie qu'elle est en attente (sinon `AccountRequestAlreadyProcessedException`), crée l'utilisateur (`password` = `Str::random(64)`, haché par le cast ; rôle affecté explicitement car non assignable en masse), passe la demande à `approved` avec `user_id`, crée un jeton via le broker de mots de passe (`PasswordBroker` injecté) et notifie `SetPasswordNotification` (en file, `afterCommit`).
   - `reject(AccountRequest): void` : même verrou et même vérification, statut `rejected`.
   - `pendingFirst(): Collection` : demandes triées `latest()` puis triées par la collection (en attente d'abord, tri stable) — pas de SQL brut.
5. **Exception** : `App\Exceptions\Admin\AccountRequestAlreadyProcessedException` avec `render()` qui redirige vers la liste avec le flash Inertia `toast` d'erreur. Le contrôleur reste mince ; la règle « une seule fois » est dans le service, protégée contre deux admins simultanés par le verrou.
6. **Notifications** : `App\Notifications\Auth\SetPasswordNotification` (textes en dur, `ShouldQueue`, `afterCommit`, bouton vers `password.reset` avec jeton et email, durée lue dans `auth.passwords.*.expire`) ; `AccountRequestedNotification` : bouton « Voir les demandes de compte » vers `admin.account-requests.index`.
7. **Toasts** : flash Inertia v3 (`Inertia::flash('toast', ['type' => …, 'message' => …])`), non conservé dans l'historique ; type `flashDataType` dans `global.d.ts` ; composable `useFlashToasts()` (écoute `router.on('flash')`, appelle `useToast().add`) monté par `DashboardLayout`.
8. **Ziggy** : groupe `admin` = les seules routes admin ; `App\Support\ZiggyGroup::for(?User)` renvoie `['guest']`, `['auth']` ou `['auth', 'admin']` (Ziggy accepte une liste de groupes) ; `app.blade.php` l'utilise. La liste par défaut `only` reste celle des invités et connectés, sans route admin (règle de CLAUDE.md).
9. **Front** :
   - `navigation/modules/admin.ts` (entrée « Demandes de compte », `i-lucide-inbox`, `requiredRole: 'admin'`) ajouté au registre.
   - `components/Admin/AccountRequestsTable.vue` : `UTable` (colonnes nom, email, message, date `Intl` fr-FR, statut en `UBadge`, actions `UButton`), émet `reject`.
   - `pages/Admin/AccountRequests/Index.vue` : `DashboardLayout` + table, `router.post` pour le refus.
   - `pages/Admin/AccountRequests/CreateAccount.vue` : `DashboardLayout` + `UCard` + `UForm`/`UFormField`/`UInput`/`USelect`, `useForm` d'Inertia prérempli, bouton « Créer le compte », lien « Annuler ».
   - `types/admin.ts` : types des demandes partagées.

Hypothèses :
- Le formulaire de création est une page dédiée (plus simple à tester et à relire qu'une modale) ; le refus se fait sans confirmation, car il n'envoie rien et le demandeur peut refaire une demande (F03 l'autorise après un refus).
- Pas de Policy dédiée : le seul droit est « être admin », porté par le Gate `admin` existant sur tout le groupe de routes ; l'état de la demande est une règle métier vérifiée par le service.
- Le lien « définir le mot de passe » utilise le broker par défaut, donc la même durée de validité que la réinitialisation (60 minutes). L'email l'indique et renvoie vers « Mot de passe oublié » en cas d'expiration (le compte existe, le parcours de F02 fonctionne).
- Un admin qui crée un compte pour un email déjà présent reçoit une erreur de validation (pas de rattachement à un compte existant).
- L'email de la demande peut être modifié par l'admin : le compte est créé avec l'email saisi ; la demande garde son email d'origine.
- Les autres demandes en attente pour le même email (cas rare de doublon décrit en F03) restent en attente ; l'admin peut les refuser.

## Status
- Branch: feature/F04-admin-comptes
- Tests written: yes (tests/Feature/Admin/*.php, tests/Unit/Auth/SetPasswordNotificationTest.php, tests/Unit/Auth/AccountRequestedNotificationTest.php, resources/js/components/Admin/AccountRequestsTable.test.ts, resources/js/pages/Admin/AccountRequests/*.test.ts, resources/js/composables/useFlashToasts.test.ts, resources/js/layouts/DashboardLayout.admin.test.ts, resources/js/navigation/registry.test.ts)
- Tests passing: yes en local (composer test, composer lint, npm run test, npm run lint, npm run build) ; AC16 confirmé par la CI de la PR #5 (jobs backend, frontend, e2e, docker verts).

Notes d'implémentation :
- Le lien « définir le mot de passe » est créé par `PasswordBroker::sendResetLink()` (contrat injecté) avec un rappel qui envoie `SetPasswordNotification` au lieu de la notification de réinitialisation : même jeton, même page, même durée.
- Le refus et la création relisent la demande sous `lockForUpdate` dans la transaction : un modèle périmé ne permet pas de traiter deux fois (testé).
- Tests existants ajustés (aucun retiré) : `registry.test.ts` (nouvelle entrée), `DashboardLayout.roles.test.ts` (l'entrée réelle s'ajoute à l'entrée simulée), `AccountRequestedNotificationTest` (nouveau bouton).
- Parcours vérifié dans Chrome (environnement `e2e`, script Playwright jetable non commité) : connexion admin, entrée de menu, liste, formulaire prérempli, création avec email en majuscules, toast de succès, statut « Acceptée », email de bienvenue dans le log avec lien `reset-password` pour `paul.martin@example.com`.
