# Feature: F02 — Authentification

## Overview
Réserver famApp aux membres de la famille : connexion par email et mot de passe (avec « se souvenir de moi »), déconnexion, mot de passe oublié et réinitialisation par lien envoyé en français, deux rôles (`admin`, `user`) et un premier compte administrateur créé par seeder à partir de la configuration. Toutes les pages exigent d'être connecté ; le menu de navigation est filtré selon le rôle réel et la sidebar affiche un menu utilisateur.

## Out of scope
- Inscription publique, demande de compte (F03), création de compte par l'admin (F04).
- Vérification d'email, double authentification, passkeys, mise à jour du profil ou du mot de passe depuis l'app, confirmation de mot de passe.
- Écrans d'administration (F04, F13) : seule la Gate `admin` est préparée.

## Acceptance criteria
- [x] AC1: Given un utilisateur existant, when il poste un email et un mot de passe corrects (avec `remember`), then il est authentifié, un cookie « se souvenir de moi » est posé, et il est redirigé vers `home` (rechargement complet via `Inertia::location`).
- [x] AC2: Given un mauvais mot de passe, when il poste le formulaire, then il reste invité et reçoit sur `email` le message français « Ces identifiants ne correspondent pas à nos enregistrements. ».
- [x] AC3: Given 5 échecs pour un même email et une même IP en une minute, when il tente une 6e fois (même avec le bon mot de passe), then la connexion est refusée avec le message français de trop nombreuses tentatives.
- [x] AC4: Given un utilisateur connecté, when il poste `logout`, then il est déconnecté, sa session invalidée, et il est renvoyé vers la page de connexion.
- [x] AC5: Given un visiteur, when il demande `home` (ou toute page de l'app), then il est redirigé vers `login` ; given un utilisateur connecté, when il demande `login`, then il est redirigé hors des pages invité.
- [x] AC6: Given les pages `login`, `password.request` et `password.reset`, when un visiteur les demande, then elles rendent respectivement les composants Inertia `Auth/Login`, `Auth/ForgotPassword` et `Auth/ResetPassword` (avec le jeton et l'email).
- [x] AC7: Given un email existant, when on demande un lien de réinitialisation, then la notification `ResetPasswordNotification` est envoyée à cet utilisateur, et son mail a un sujet et un contenu en français avec un lien vers `password.reset` ; given un email inconnu, then aucune notification n'est envoyée et la réponse (redirection, message de statut, absence d'erreur) est identique à celle d'un email existant.
- [x] AC8: Given un jeton valide, when on poste un nouveau mot de passe confirmé, then le mot de passe est changé et on est redirigé vers `login` avec un message de statut ; given un jeton invalide, then une erreur française est renvoyée et le mot de passe est inchangé.
- [x] AC9: Given l'application, when on demande `GET /register` ou `POST /register`, then la réponse est 404 ; et les routes de vérification d'email, 2FA, passkeys, profil et changement de mot de passe n'existent pas.
- [x] AC10: Given `famapp.admin.email` et `famapp.admin.password` configurés, when on lance le seeder admin, then un utilisateur de rôle `admin` est créé avec ces identifiants ; when on le relance, then aucun doublon n'est créé et le mot de passe existant n'est pas écrasé ; given l'email ou le mot de passe manquant, then le seeder échoue avec un message explicite et ne crée rien.
- [x] AC11: Given un utilisateur créé sans rôle, then son rôle vaut `Role::User` ; le rôle n'est pas assignable en masse.
- [x] AC12: Given un utilisateur connecté, when une page Inertia est rendue, then la prop partagée `auth.user` contient exactement `id`, `name`, `email`, `role` ; given un visiteur, then `auth.user` vaut `null`.
- [x] AC13: Given une route protégée par `can:admin`, when un admin la demande, then 200 ; when un `user` la demande, then 403 ; when un visiteur la demande, then redirection vers `login`.
- [x] AC14: Given la liste Ziggy, when un visiteur charge une page, then seules les routes d'authentification nécessaires sont exposées (pas `home`, `logout`) ; when un utilisateur connecté charge une page, then `home` et `logout` sont exposées mais pas les routes invité ; une route non autorisée n'est jamais exposée.
- [x] AC15 (Vitest): Given `auth.user` partagé avec le rôle `user`, when le layout est rendu, then les entrées exigeant `admin` sont masquées ; avec le rôle `admin`, then elles sont visibles.
- [x] AC16 (Vitest): Given le menu utilisateur en pied de sidebar, when il est rendu, then il affiche le nom de l'utilisateur ; when on choisit « Se déconnecter », then un `POST` vers `logout` est envoyé.
- [x] AC17 (Vitest): Given la page `Auth/Login`, when elle est rendue, then elle affiche un `UAuthForm` en français (email, mot de passe, « Se souvenir de moi », lien « Mot de passe oublié ? ») et les erreurs serveur sous le champ email.
- [x] AC18 (Playwright, CI): Given l'admin seedé, when il se connecte avec le bon mot de passe, then il arrive sur l'accueil ; with un mauvais mot de passe, then le message d'erreur français s'affiche.
- [x] AC19: Given le code, when on lance `composer test`, `composer lint`, `npm run test`, `npm run lint`, `npm run build`, then tout passe, et la CI (dont le job `e2e`) est verte.

## Technical approach
1. **Fortify** (`laravel/fortify`) : `config/fortify.php` avec `features` = `resetPasswords()` seulement, `views` = `false`, `home` = `/`, `limiters.login` = `null` (verrouillage intégré de Fortify : 5 échecs par email + IP, message français `auth.throttle` sous le champ email, au lieu d'un 429 brut du middleware `throttle`). `App\Providers\FortifyServiceProvider` : action `ResetUserPassword`, réponses personnalisées :
   - `LoginResponse` → `Inertia::location(intended(home))` (rechargement complet pour que la liste Ziggy de l'utilisateur connecté soit imprimée) ; `LogoutResponse` → `Inertia::location(route('login'))`.
   - `SuccessfulPasswordResetLinkRequestResponse` et `FailedPasswordResetLinkRequestResponse` liées à une même réponse neutre (`back()` + statut français « Si un compte correspond… ») : aucune fuite sur l'existence d'un email.
2. **Pages GET** (Fortify `views=false`) : `App\Http\Controllers\Auth\AuthPageController` (`login`, `forgotPassword`, `resetPassword`) rend les pages Inertia, routes nommées `login`, `password.request`, `password.reset` sous le middleware `guest` dans `routes/web.php`. Avec `views=false`, Fortify n'enregistre pas non plus la page de confirmation de mot de passe.
3. **Rôles** : enum `App\Enums\Role` (`Admin = 'admin'`, `User = 'user'`), migration `add_role_to_users_table` (string, défaut `user`), cast sur `User`, `isAdmin()`, état de factory `admin()`. Gate `admin` dans `AppServiceProvider` (utilisée via `can:admin`).
4. **Seeder** : `config/famapp.php` (`admin.name|email|password` depuis `ADMIN_*`), `App\Services\Auth\AdminAccountService::ensureAdmin()` (crée ou promeut, n'écrase jamais le mot de passe), `Database\Seeders\AdminUserSeeder` (valide la config, lève une `RuntimeException` explicite), appelé par `DatabaseSeeder`.
5. **Notification** : `App\Notifications\Auth\ResetPasswordNotification` (textes français en dur, `MailMessage`), branchée via `User::sendPasswordResetNotification()`.
6. **Inertia** : `HandleInertiaRequests::share()` → `auth.user` (id, name, email, role) et `status` (message de session). Typage `InertiaConfig.sharedPageProps` dans `types/global.d.ts`.
7. **Ziggy** : `groups.guest` (`login`, `login.store`, `password.request`, `password.email`, `password.update`) et `groups.auth` (`home`, `logout`) ; `@routes(auth()->check() ? 'auth' : 'guest')`. `only` reste la liste d'autorisation complète (union des deux groupes, cf. CLAUDE.md) ; les groupes ne font que la partager selon l'état de connexion.
8. **Front** : `layouts/AuthLayout.vue` (page centrée, `UPageCard`), pages `Auth/Login`, `Auth/ForgotPassword`, `Auth/ResetPassword` avec `UAuthForm` + `useForm` d'Inertia (erreurs serveur passées dans `fields[].error`, statut dans un `UAlert`). `composables/useAuth.ts` (utilisateur partagé + rôles). `components/Auth/UserMenu.vue` (`UDropdownMenu` + `UUser`, « Se déconnecter » → `router.post(route('logout'))`) en pied de sidebar. Le layout passe les rôles réels au filtrage du registre.
9. **Playwright** : `@playwright/test`, `playwright.config.ts` (`webServer` = `php artisan serve` avec `APP_ENV=e2e`), `tests/e2e/login.spec.ts`, `scripts/prepare-e2e.sh` (écrit `.env.e2e` en SQLite, `migrate:fresh --seed` avec `ADMIN_*`, `npm run build` ; le `.env` local n'est pas touché) ; job CI `e2e` : PHP + Node, `npx playwright install --with-deps chromium`, script de préparation, `npx playwright test`, rapport en artefact en cas d'échec.

Hypothèses :
- Un admin est aussi un membre : ses rôles effectifs pour le filtrage de navigation sont `['admin', 'user']` ; un `user` a `['user']` ; un visiteur `[]`.
- Relancer le seeder sur un compte existant le (re)passe `admin` mais ne modifie ni son nom ni son mot de passe (un mot de passe réinitialisé n'est pas écrasé).
- `ADMIN_NAME` a une valeur par défaut (« Administrateur ») ; seuls l'email et le mot de passe sont obligatoires.
- Le lien de réinitialisation expire selon `auth.passwords.users.expire` (60 min), mentionné dans l'email.
- ~~Les routes de confirmation de mot de passe restent enregistrées par Fortify~~ : depuis la relecture, `Fortify::ignoreRoutes()` et seules les routes nécessaires sont déclarées dans `routes/web.php`.
- ~~La demande de lien n'a pas de limite propre~~ : limite par IP ajoutée à la relecture.

## Status
- Branch: feature/F02-authentification
- Tests written: yes (tests/Feature/Auth/*, tests/Unit/Auth/ResetPasswordNotificationTest.php, tests/Feature/Core/HomeTest.php, resources/js/composables/useAuth.test.ts, resources/js/components/Auth/UserMenu.test.ts, resources/js/layouts/DashboardLayout.roles.test.ts, resources/js/pages/Auth/*.test.ts, tests/e2e/login.spec.ts)
- Tests passing: yes en local (composer test, composer lint, npm run test, npm run lint, npm run build) ; Playwright passé en local avec le Chrome du système (`PLAYWRIGHT_EXECUTABLE_PATH=/usr/bin/google-chrome`), le téléchargement des navigateurs Playwright étant bloqué ; AC18-AC19 confirmés par la CI de la PR #3 (jobs backend, frontend, docker, e2e verts).

Notes d'implémentation :
- Les emails de notification de Laravel contenaient des textes anglais (« Hello! », « Regards, », « If you're having trouble… », « All rights reserved. ») : les gabarits `resources/views/vendor/notifications/email.blade.php` et `resources/views/vendor/mail/{html,text}/message.blade.php` sont publiés avec ces textes en français en dur (pas de fichier de traduction) ; ils serviront aussi aux emails de F03/F04.
- Le bouton de repli de la sidebar passe dans la navbar (`#leading`, comme le modèle dashboard de Nuxt UI) pour laisser toute la largeur du pied de sidebar au menu utilisateur.
- Les stubs Fortify inutiles (création d'utilisateur, profil, changement de mot de passe) et les migrations 2FA/passkeys publiées par Fortify ne sont pas conservés. `laravel/passkeys` est une dépendance imposée par Fortify 1.42, mais la fonctionnalité est désactivée et ses routes ne sont pas enregistrées.
- Ziggy : tests Vitest — `test/setup.ts` déclare les routes d'authentification ; `testPage` porte `auth.user` et `status`, réinitialisés après chaque test.

## Relecture (2026-11-16)
Relecteur indépendant, corrigé sur la même branche :
- Bloquant — énumération d'emails par `POST /reset-password` (email inconnu : « aucun utilisateur », email connu : « jeton invalide ») : `FailedPasswordResetResponse` liée à `InvalidPasswordResetResponse`, même message dans les deux cas (« Ce lien de réinitialisation est invalide ou a expiré… »), limite par IP (5/min). Test : email inconnu = même erreur et même redirection qu'un email connu avec un mauvais jeton.
- Bloquant — page blanche après expiration de session (Inertia suivait le 302 vers `/login` en gardant la liste Ziggy du groupe `auth`) : une requête Inertia non authentifiée reçoit `Inertia::location(route('login'))` (409 + `X-Inertia-Location`, URL visée mémorisée pour les GET), via `App\Http\Responses\Auth\UnauthenticatedResponse` branché dans `bootstrap/app.php` ; cas inverse traité par le middleware `guest` remplacé par `RedirectAuthenticatedUsers` (utilisateur connecté vers une page invité → rechargement complet vers l'accueil). Tests Pest des deux sens, et des requêtes hors Inertia (302) et JSON (401) inchangées ; scénario vérifié dans le navigateur (cookies effacés puis clic dans le menu → page de connexion fonctionnelle).
- Les routes d'authentification sont désormais déclarées dans `routes/web.php` avec les contrôleurs Fortify (`Fortify::ignoreRoutes()`), ce qui permet un limiteur par route et supprime les routes de confirmation de mot de passe.
- Limites par IP (réponse française « Trop de tentatives… » sous le champ email, sans 429 brut) : connexion 20/min (en plus du verrouillage Fortify 5 échecs par email + IP), demande de lien 5/min, réinitialisation 5/min. Testées.
- `ResetPasswordNotification` implémente `ShouldQueue` (le temps de réponse ne révèle plus l'existence d'un compte). Testé.
- `authenticateSessions()` : une session dont le mot de passe a changé ailleurs est déconnectée ; la réinitialisation renouvelle aussi le `remember_token` (appareils mémorisés). Testé.
- Seeder : email normalisé en minuscules (recherche et enregistrement), `ADMIN_PASSWORD` validé par `Password::default()` avec un échec explicite. Testé.
- Connexion : assertion que l'identifiant de session change.
- Tests Pest : helper `inertiaHeaders()` (`tests/Pest.php`) qui envoie aussi `X-Inertia-Version`, sinon le contrôle de version d'Inertia remplace la réponse par un 409 vers l'URL courante.
