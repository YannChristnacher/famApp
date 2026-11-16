# Feature: F03 — Demande de création de compte

## Overview
Permettre à un membre de la famille qui n'a pas encore de compte de le demander : une page publique « Demander un compte » (lien depuis la page de connexion) recueille son nom, son email et un message facultatif. La demande est enregistrée en base (statut `pending`) et un email en français part, en file d'attente, vers chaque administrateur. La réponse au visiteur est toujours la même, pour ne révéler ni les comptes existants ni les demandes déjà faites. Seule une limite de débit par IP protège le formulaire.

## Out of scope
- Liste des demandes, approbation ou refus, création du compte et email « définir mon mot de passe » (F04).
- Champ piège, captcha ou toute autre protection anti-spam que la limite de débit (refusés par l'utilisateur).
- Email de confirmation au demandeur.
- Parcours Playwright (CLAUDE.md réserve Playwright aux parcours clés).

## Acceptance criteria
- [x] AC1: Given un visiteur, when il demande `account-request.create`, then la page rend le composant Inertia `Auth/RequestAccount`.
- [x] AC2: Given un utilisateur connecté, when il demande `account-request.create` ou poste `account-request.store`, then il est redirigé vers l'accueil comme pour les autres pages invité (rechargement complet pour une visite Inertia) et rien n'est enregistré.
- [x] AC3: Given le formulaire, when il est posté sans nom ni email, then des erreurs françaises sont renvoyées sur `name` et `email` ; given un email invalide, then une erreur française sur `email` ; given un message de plus de 2000 caractères ou un nom de plus de 255, then une erreur sur le champ concerné ; dans tous ces cas rien n'est enregistré ni envoyé.
- [x] AC4: Given un nom, un email (avec majuscules et espaces) et un message, when le visiteur poste le formulaire, then une demande `account_requests` est créée avec l'email en minuscules sans espaces, le nom et le message, et le statut `pending` ; le message est facultatif (enregistré `null` s'il est vide).
- [x] AC5: Given deux admins et un user, when une nouvelle demande est enregistrée, then la notification `AccountRequestedNotification` est envoyée à chaque admin et à aucun user.
- [x] AC6: Given la notification, then elle implémente `ShouldQueue`, passe par `mail`, a un sujet et un contenu en français contenant le nom, l'email et le message du demandeur (ou l'absence de message), et un bouton vers l'application ; un contenu Markdown saisi par le visiteur (lien, titre) n'est pas interprété dans l'email.
- [x] AC7: Given une demande valide, when elle est postée, then la réponse est une redirection vers `login` avec le statut « Votre demande a bien été envoyée. Un administrateur vous contactera par email. ».
- [x] AC8: Given un email qui a déjà un compte (quelle que soit la casse), when une demande est postée pour cet email, then la réponse est identique à AC7 (même code, même destination, même statut, aucune erreur), aucune demande n'est enregistrée et aucune notification n'est envoyée.
- [x] AC9: Given une demande `pending` existante pour un email, when une nouvelle demande est postée pour cet email, then la réponse est identique à AC7, aucune nouvelle demande n'est enregistrée et aucune notification n'est envoyée ; given une demande précédente `rejected` ou `approved` (sans compte), then une nouvelle demande `pending` est créée et notifiée.
- [x] AC10: Given 5 demandes postées depuis une même IP dans l'heure, when une 6e est postée, then elle est refusée avec l'erreur française « Trop de demandes envoyées. Veuillez réessayer plus tard. » sous le champ `email` (redirection vers la page du formulaire, saisie conservée), sans enregistrement ni notification.
- [x] AC11: Given la liste Ziggy, when un visiteur charge une page, then `account-request.create` et `account-request.store` sont exposées ; when un utilisateur connecté charge une page, then elles ne le sont pas.
- [x] AC12 (Vitest): Given la page `Auth/RequestAccount`, when elle est rendue, then elle affiche dans un `UAuthForm` les champs « Nom », « Adresse email », « Message » (`UTextarea`), le bouton « Envoyer la demande » et un lien retour vers `/login`.
- [x] AC13 (Vitest): Given la page remplie, when on la soumet, then un `POST` vers `/account-request` est envoyé avec `name`, `email`, `message` ; given des erreurs serveur, then elles s'affichent sous les champs concernés.
- [x] AC14 (Vitest): Given la page `Auth/Login`, when elle est rendue, then elle affiche un lien « Demander un compte » vers `/account-request`.
- [x] AC15: Given le code, when on lance `composer test`, `composer lint`, `npm run test`, `npm run lint`, `npm run build`, then tout passe, et la CI est verte.

## Technical approach
1. **Données** : enum `App\Enums\AccountRequestStatus` (`Pending`, `Approved`, `Rejected`) ; migration `create_account_requests_table` (`name`, `email` indexé, `message` text nullable, `status` string défaut `pending`, timestamps) ; modèle `App\Models\Auth\AccountRequest` (cast du statut, statut par défaut `pending`, non assignable en masse) ; factory `Database\Factories\Auth\AccountRequestFactory` avec états `approved()` et `rejected()`.
2. **Validation** : `App\Http\Requests\Auth\StoreAccountRequestRequest` — `name` requis ≤ 255, `email` requis, `email`, ≤ 255, `message` facultatif ≤ 2000 ; `prepareForValidation` normalise l'email (trim + minuscules). Messages et noms de champs français issus de `lang/fr/validation.php` (`nom`, `adresse e-mail`, `message`).
3. **Service** : `App\Services\Auth\AccountRequestService::submit(array $data)` — si un utilisateur ou une demande `pending` existe pour cet email, ne fait rien ; sinon crée la demande et envoie `AccountRequestedNotification` à `User::where('role', admin)`.
4. **Notification** : `App\Notifications\Auth\AccountRequestedNotification` (`ShouldQueue`, `mail`, textes français en dur, gabarits publiés par F02). Les valeurs saisies par le visiteur sont ramenées sur une ligne et leur ponctuation interprétable (HTML ou Markdown) est convertie en références numériques (`&#91;`…) dans une `HtmlString` : CommonMark les traite comme du texte, la version HTML n'affiche ni lien, ni titre, ni balise, et la version texte (qui décode les entités) montre la saisie à l'identique. Bouton « Ouvrir famApp » vers `home`.
5. **Contrôleur** : `App\Http\Controllers\Auth\AccountRequestController` (`create` rend `Auth/RequestAccount`, `store` appelle le service puis `to_route('login')->with('status', …)`).
6. **Routes** (`routes/web.php`, groupe `guest`) : `GET /account-request` (`account-request.create`), `POST /account-request` (`account-request.store`, `throttle:account-request`). Limiteur `account-request` déclaré dans `AppServiceProvider` : 5 par heure et par IP, réponse `back()` avec la saisie et l'erreur française sous `email` (même forme que les limiteurs de F02, pas de 429 brut).
7. **Ziggy** : les deux routes ajoutées au groupe `guest` (le front en a besoin pour le formulaire et le lien).
8. **Front** : page `pages/Auth/RequestAccount.vue` (`AuthLayout` + `UAuthForm`, champ message rendu par le slot `#message-field` avec un `UTextarea` car `UAuthForm` n'a pas de type textarea ; `useForm` d'Inertia, erreurs serveur dans `fields[].error`) ; lien « Demander un compte » dans le pied de `Auth/Login`. Le statut de succès s'affiche dans l'`UAlert` déjà présent sur la page de connexion.

Hypothèses :
- Module `Auth` (backlog) : la demande naît avant tout compte ; F04 (module Admin) la consommera.
- **Comportement anti-énumération retenu** : pour un email qui a déjà un compte ou une demande en attente, la demande est ignorée silencieusement (ni enregistrement, ni email à l'admin, ni email au demandeur). C'est le plus sûr : aucun email ne part vers une adresse saisie par un inconnu (pas de relais de spam), l'admin n'est pas inondé, et la réponse HTTP est strictement la même. La notification étant en file d'attente, le temps de réponse ne trahit pas non plus le cas.
- Une demande `rejected` ou `approved` n'empêche pas une nouvelle demande (le compte a pu être supprimé, ou la personne a précisé sa demande) ; l'admin tranchera en F04.
- Limite : 5 demandes par heure et par IP (une famille derrière une même box peut faire plusieurs demandes ; un robot est vite freiné). Les envois rejetés par la validation comptent aussi.
- Deux envois simultanés pour le même email pourraient créer deux demandes `pending` (pas de contrainte d'unicité : un email peut avoir plusieurs demandes historiques) ; risque jugé négligeable, F04 pourra les fusionner.
- S'il n'y a aucun admin, la demande est enregistrée sans notification (elle sera visible en F04).
- Le lien de l'email pointe vers l'accueil (`home`) : un admin non connecté passe par la page de connexion puis y revient.

## Status
- Branch: feature/F03-demande-compte
- Tests written: yes (tests/Feature/Auth/AccountRequestTest.php, tests/Feature/Auth/AccountRequestServiceTest.php, tests/Unit/Auth/AccountRequestedNotificationTest.php, tests/Feature/Core/HomeTest.php, resources/js/pages/Auth/RequestAccount.test.ts, resources/js/pages/Auth/Login.test.ts)
- Tests passing: yes en local (composer test, composer lint, npm run test, npm run lint, npm run build) ; AC15 à confirmer par la CI de la PR.

Notes d'implémentation :
- `UAuthForm` n'a pas de type de champ textarea et le slot dynamique `#message-field` est mal typé par Nuxt UI (collision avec les slots `-label`/`-hint`…, props `{}`) : le `UTextarea` est lié directement au `useForm` d'Inertia (`form.message`), `UAuthForm` ne porte que le nom et l'email.
- Un premier essai d'échappement par antislash laissait des `\` dans la version texte de l'email (l'adresse du demandeur devenait `jp\_d@example\.com`) : remplacé par les références numériques, testées sur les deux versions.
- Parcours vérifié dans le navigateur (Chrome du système, environnement `e2e`) : lien depuis la connexion, envoi, retour sur la connexion avec le message, demande enregistrée avec l'email en minuscules, email admin rendu dans le log. Pas de test Playwright ajouté (hors parcours clés).
