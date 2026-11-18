# Feature: F13 — Page de contrôle admin

## Overview
L'administrateur dispose d'une **page de contrôle** (`/admin/control`, entrée de menu « Contrôle » réservée au rôle admin) qui réunit les informations utiles au suivi d'activité et au débogage : demandes de compte en attente, compteurs (membres, invités, livres, outils, étagères, marques), derniers ajouts, dernières connexions, jobs en échec (avec « Relancer » / « Supprimer »), dernières erreurs du journal Laravel et activité Google Books. La page est un tableau de bord Nuxt UI en lecture (cartes de statistiques, `UTable`, badges), en français, avec horodatage relatif. Le journal `google_books_requests` devient purgeable (90 jours par défaut) par la tâche planifiée `model:prune`.

## Out of scope
- Graphiques, export, filtres, pagination des blocs.
- Gestion des comptes existants (désactivation, changement de rôle, suppression).
- Lancement du planificateur en production (F14 : service Docker `schedule:work` ou cron).
- Stockage de l'adresse IP de connexion (inutile, donnée personnelle).
- Agrégation de plusieurs fichiers de log ou de canaux autres que fichier (syslog, stderr…).

## Acceptance criteria
- [x] AC1: Given un admin, when il ouvre `GET /admin/control`, then 200 et la page Inertia `Admin/Control/Index` ; given un membre non admin, then 403 ; given un invité, then redirection vers la connexion. Idem pour les actions sur les jobs en échec (rien n'est modifié en cas de refus).
- [x] AC2: Given des demandes de compte en attente, acceptées et refusées, then la page indique le nombre de demandes **en attente** seulement et le front affiche un lien vers `admin.account-requests.index`.
- [x] AC3: Given des comptes activés (admins compris), des invitations non utilisées, des livres, outils, étagères des deux modules et marques, then les compteurs `members`, `invited`, `books`, `tools`, `bookshelves`, `toolShelves`, `brands` sont exacts.
- [x] AC4: Given des livres et outils de plusieurs membres, then « derniers ajouts » renvoie les 10 plus récents, livres et outils mêlés, triés du plus récent au plus ancien (puis id décroissant), chacun avec `type` (`book`|`tool`), `id`, `label` (titre ou nom), `owner` (nom), `createdAt` (ISO 8601) et `url` relative vers la fiche.
- [x] AC5: Given l'événement `Illuminate\Auth\Events\Login` (connexion réelle par le formulaire comprise), then `users.last_login_at` du membre vaut l'heure courante, sans autre donnée stockée (aucune IP) ; la page renvoie les 10 dernières connexions (`id`, `name`, `lastLoginAt`) de la plus récente à la plus ancienne, sans les comptes jamais connectés ; `last_login_at` n'apparaît pas dans les props partagées `auth.user`.
- [x] AC6: Given des lignes dans `failed_jobs`, then la page renvoie leur nombre total et les 10 plus récentes avec `uuid`, `queue`, `connection`, `job` (nom de la classe lu dans `displayName`), `exception` (première ligne seulement, masquée, tronquée à 300 caractères) et `failedAt` ; **ni le payload ni la trace** ne figurent dans la réponse.
- [x] AC7: Given un job en échec, when l'admin le relance (`POST /admin/failed-jobs/{uuid}/retry`), then le job est remis dans sa file et retiré de `failed_jobs`, avec toast de succès et retour sur la page ; when il le supprime (`DELETE /admin/failed-jobs/{uuid}`), then la ligne est supprimée, toast ; un uuid inconnu répond 404, un identifiant mal formé aussi.
- [x] AC8: Given un faux fichier de log Laravel, then « dernières erreurs » renvoie au plus 10 entrées de niveau `ERROR` ou plus grave (`CRITICAL`, `ALERT`, `EMERGENCY`), de la plus récente à la plus ancienne, avec `level`, `loggedAt` et `message` ; le message ne contient ni contexte JSON ni trace, il est tronqué à 300 caractères, et les motifs `password=`, `token=`, `signature=`, `api_key=`, `"password":"…"`, `Bearer …`, clés Google `AIza…` sont masqués (`***`). Seule la fin du fichier est lue (256 Ko max) ; sans fichier, la liste est vide.
- [x] AC9: Given des appels Google Books journalisés à différentes dates, then la page renvoie, pour les 24 dernières heures et les 7 derniers jours, le nombre d'appels par statut (libellé français), le total et la durée moyenne en ms (`null` sans appel) ; et les 10 derniers échecs (statuts autres que `found` et `not_found`) avec type, requête, statut, code HTTP, membre et date.
- [x] AC10: Given des lignes de `google_books_requests` plus anciennes que `services.google_books.journal_retention_days` (90 par défaut, configurable), when `php artisan model:prune` tourne, then elles sont supprimées et les plus récentes conservées ; given le planificateur, then `model:prune` est déclaré chaque jour dans `routes/console.php`.
- [x] AC11: Given les routes de la page et des actions, then elles sont dans le groupe Ziggy `admin` seulement : imprimées pour un admin, jamais pour un membre ni un invité.
- [x] AC12: Given beaucoup de données (livres, outils, connexions, jobs, appels), then le nombre de requêtes SQL de la page est constant (pas de N+1).
- [x] AC13 (Vitest): la page affiche chaque bloc (cartes de compteurs, lien vers les demandes, derniers ajouts avec lien vers la fiche, connexions, jobs, erreurs, Google Books) avec un horodatage relatif lisible (« il y a 5 minutes ») ; chaque bloc vide affiche un message d'état vide ; « Relancer » et « Supprimer » ouvrent une confirmation, seule la confirmation envoie la requête ; l'entrée de menu « Contrôle » n'est visible que pour un admin.

## Technical approach
1. **Données** :
   - Migration `add_last_login_at_to_users_table` (`timestamp` nul + index) ; cast `datetime` sur `User`.
   - Migration `add_status_created_at_index_to_google_books_requests_table` : index `(status, created_at)` (derniers échecs et comptes par statut).
   - `GoogleBooksRequest` : trait `MassPrunable`, `prunable()` = `created_at < now() - journal_retention_days` ; `config/services.php` → `google_books.journal_retention_days` (`GOOGLE_BOOKS_JOURNAL_RETENTION_DAYS`, 90). `routes/console.php` : `Schedule::command('model:prune')->daily()`.
   - `GoogleBooksRequestStatus::label()` et `GoogleBooksRequestType::label()` (libellés français, fournis par le serveur).
2. **Connexions** : listener `app/Listeners/Auth/RecordLastLogin.php` sur `Login` (découverte automatique des listeners de Laravel) : `forceFill(['last_login_at' => now()])->saveQuietly()`.
3. **Services** (`app/Services/Admin/`), une responsabilité chacun :
   - `ControlCountsService::counts()` : demandes en attente + compteurs (requêtes `count()` constantes).
   - `RecentActivityService::latestAdditions(limit)` (deux requêtes bornées avec `user:id,name`, fusion et tri en PHP) et `latestLogins(limit)`.
   - `FailedJobService` : `count()`, `latest(limit)` (query builder sur `queue.failed.table`, `payload` lu côté serveur pour le seul `displayName`, jamais renvoyé), `retry(uuid)` (`Artisan::call('queue:retry')`), `forget(uuid)` (`FailedJobProviderInterface::forget`) ; `find(uuid)` ou 404.
   - `ErrorLogReader::latest(limit)` : fichier `laravel*.log` le plus récent du dossier configuré, lecture bornée depuis la fin, analyse des lignes `[date] env.LEVEL: message`, coupe du contexte (` {"…`, ` [] []`), masquage, troncature.
   - `GoogleBooksActivityService::summary(limit)` : deux requêtes groupées par statut (`count(*)`, `sum(duration_ms)`, expressions fixes sans saisie), derniers échecs avec `user:id,name`.
   - `App\Support\SecretMasker::mask(string)` : masquage partagé (log et exceptions des jobs) ; `App\Support\TruncatedText::of()` (UTF-8 invalide remplacé, puis troncature avec « … »).
   - DTO lecture seule `ErrorLogEntry`, `FailedJobSummary`, `GoogleBooksPeriodSummary`.
4. **HTTP** : `Admin/ControlPanelController` (invocable, `GET /admin/control`, `admin.control`), `Admin/FailedJobController` (`retry` `POST /admin/failed-jobs/{uuid}/retry` → `admin.failed-jobs.retry` ; `destroy` `DELETE /admin/failed-jobs/{uuid}` → `admin.failed-jobs.destroy` ; `whereUuid`), toast flash puis `to_route('admin.control')`. Routes ajoutées à `$adminRoutes` de `config/ziggy.php` uniquement. Resources `Admin/RecentAdditionResource`, `RecentLoginResource`, `FailedJobResource`, `ErrorLogEntryResource`, `GoogleBooksPeriodResource`, `GoogleBooksFailureResource`. `config/famapp.php` → `control` : `recent_limit` (10), `log_directory` (`storage/logs`), `log_tail_bytes` (256 Ko), `message_max_length` (300).
5. **Front** : `pages/Admin/Control/Index.vue` ; composants `components/Admin/Control/{ControlCounters,RecentAdditionsTable,RecentLoginsTable,FailedJobsTable,FailedJobActionModal,ErrorLogTable,GoogleBooksActivity,RelativeTime}.vue` (`UPageCard`/`UCard`, `UTable`, `UBadge`, `UModal` de confirmation) ; `support/admin/relativeTime.ts` (`Intl.RelativeTimeFormat('fr')`, date complète en `title`) ; types dans `types/admin.ts` ; entrée « Contrôle » (`i-lucide-activity`, `admin.control`, `requiredRole: 'admin'`) avant « Demandes de compte ». Tout le texte affiché passe par l'interpolation Vue (aucun `v-html`).
6. **Tests** : Pest `tests/Feature/Admin/{ControlPanelAccess,ControlPanelData,FailedJobs,ErrorLog,GoogleBooksActivity,RecordLastLogin,GoogleBooksJournalPruning}Test.php`, `tests/Unit/Core/SecretMaskerTest.php`, `AdminRouteListTest` complété ; Vitest `pages/Admin/Control/Index.test.ts`, `components/Admin/Control/FailedJobsTable.test.ts`, `support/admin/relativeTime.test.ts`, `DashboardLayout.admin.test.ts` complété.

### Hypothèses
- **Dernières erreurs = lecture du fichier de log** plutôt qu'une table alimentée par le gestionnaire d'exceptions : aucune écriture supplémentaire pendant une erreur (une base en panne ne masque pas l'erreur d'origine), rien à purger, et le canal par défaut (`single`, `storage/logs/laravel.log`) ou `daily` (`laravel-AAAA-MM-JJ.log`) sont couverts en prenant le fichier `laravel*.log` modifié le plus récemment. Seule la première ligne d'une entrée est retenue (le message), le contexte JSON et la trace sont coupés ; masquage avant troncature. Si la production loggue ailleurs (stderr), le bloc reste vide : à trancher en F14.
- Masquage : clés contenant `password`, `passwd`, `secret`, `token`, `signature`, `key`, `authorization`, `cookie` suivies de `=` ou `:` (forme URL, texte ou JSON) ; `Bearer <jeton>` ; clés d'API Google `AIza…`. Le masquage est une défense en profondeur, pas une garantie : le log reste réservé à l'admin.
- Relancer / supprimer un job en échec est simple (`queue:retry`, `FailedJobProviderInterface::forget`) : inclus, avec confirmation par `UModal`.
- Le nom du job vient de `displayName` du payload, lu côté serveur ; le payload n'est jamais envoyé au front ; l'exception est réduite à sa première ligne, masquée et tronquée.
- « Derniers ajouts » mêle livres et outils dans une seule liste de 10 (badge de type), plus lisible que deux tableaux.
- `last_login_at` est mis à jour à chaque événement `Login`, y compris une reconnexion par cookie « se souvenir de moi » ; `saveQuietly` ne déclenche pas d'événements de modèle. Aucune IP ni agent utilisateur stockés.
- `count(*)`/`sum()` groupés par `selectRaw` avec des expressions fixes (aucune saisie) : seule façon agrégée de compter par statut avec Eloquent.
- Rétention du journal Google Books dans `config/services.php` (à côté du reste de la config Google Books) ; `MassPrunable` (suppression en une requête, pas d'événement par ligne).

## Status
- Branch: feature/F13-controle-admin
- Tests written: yes (tests/Feature/Admin/{ControlPanelAccess,ControlPanelData,FailedJobs,ErrorLog,GoogleBooksActivity,RecordLastLogin,GoogleBooksJournalPruning}Test.php, tests/Unit/Core/SecretMaskerTest.php, AdminRouteListTest complété ; Vitest pages/Admin/Control/Index.test.ts, components/Admin/Control/FailedJobsTable.test.ts, support/admin/relativeTime.test.ts, DashboardLayout.admin.test.ts complété, registry.test.ts et DashboardLayout.roles.test.ts ajustés pour la nouvelle entrée)
- Tests passing: yes en local (composer test, composer lint, npm run test, npm run lint, npm run build)

## Relecture (2026-11-18)
Relecteur indépendant : aucun point bloquant. Corrigés :
- Relance d'un job : refusée avant `queue:retry` si la ligne a disparu ou si le payload n'est pas un objet JSON (sinon la commande pousse `"null"` dans la file et supprime la ligne) ; tout échec de la commande (commande sérialisée corrompue, commande chiffrée illisible) est rapporté puis traduit en `FailedJobNotRetryableException` (toast d'erreur français, retour sur la page), la ligne `failed_jobs` restant intacte.
- `FailedJobActionRequest` (`authorize()` = Gate `admin`) sur la relance et la suppression, en plus du middleware.
- Lecture du log : fichiers retenus seulement s'ils sont lisibles et ne sont pas des liens symboliques ; toute erreur de lecture (fichier supprimé par la rotation, illisible) donne une liste vide, jamais une 500 ; fichier toujours refermé (taille 0 comprise) ; contexte en liste (` ["…`) coupé aussi.
- `SecretMasker` : identifiants dans une URL (`scheme://***@`), `Authorization: Basic|Digest ***` (reste de la ligne), `base64:` de 20 caractères ou plus, hachés bcrypt et argon2, JWT, clés `pwd`, `pass`, `credential`, `session`, `csrf`, `remember_`, valeur JSON entre guillemets entièrement masquée (espaces et guillemets échappés compris).
- Le test « fichier illisible » est ignoré sous root (root lit tout) : il tourne en CI (utilisateur `runner`).
