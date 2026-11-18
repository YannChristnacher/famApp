# Feature: F16 — Seeders de démonstration

## Overview
Remplir la base locale avec un jeu de données réaliste, en français, qui couvre toutes les features (membres, invitation, demandes de compte, étagères, livres avec couverture et commentaire, outils avec marques, photos et accessoires, journal Google Books, jobs en échec), pour pouvoir tout tester à la main. Une seule commande : `php artisan db:seed --class=DemoSeeder` (ou `php artisan migrate:fresh --seed --seeder=DemoSeeder` pour repartir de zéro). Le jeu refuse de tourner en production.

## Out of scope
- Toute donnée de production ou d'import réel ; aucune page ni route nouvelle.
- Téléchargement de vraies couvertures ou photos : les images sont générées localement avec GD (aucun appel réseau).
- Mise à jour d'un jeu déjà chargé (relancer ne recrée rien ; pour repartir de zéro : `migrate:fresh`).
- `DatabaseSeeder` garde son rôle (compte admin depuis la config) et n'appelle pas le jeu de démonstration.

## Acceptance criteria
- [x] AC1: Given une base vide (admin de la config renseigné), when on lance `db:seed --class=DemoSeeder`, then on obtient l'admin de la config, 4 membres de démo activés en `@famapp.local` (dont un second admin), 1 invité non activé, des `last_login_at` variés (dont un jamais connecté), 2 demandes de compte en attente, 1 approuvée liée à l'invité, 1 refusée.
- [x] AC2: Given le jeu chargé, then chaque membre de démo a des étagères de bibliothèque et de garage aux positions 1..n compactes, et chaque lien livre/outil ↔ étagère relie deux objets du même propriétaire.
- [x] AC3: Given le jeu chargé, then il y a 30 livres réels (titre, auteur, ISBN-13 valide selon la règle `Isbn`), dont des doublons d'ISBN entre membres, des livres sur 0, 1 ou plusieurs étagères, des commentaires, et des couvertures JPEG présentes sur le disque `public` (quelques livres sans couverture).
- [x] AC4: Given le jeu chargé, then il y a 20 outils réalistes, des marques réelles uniques par `normalized_name` (une marque saisie avec une autre casse est rattachée à la même), des accessoires aux positions 1..n, 0 à 3 photos JPEG présentes sur le disque aux positions 1..n, des commentaires.
- [x] AC5: Given le jeu chargé, then le journal `google_books_requests` contient des appels des 7 derniers jours avec au moins 5 statuts différents, et `failed_jobs` contient 2 jobs relançables (payload JSON valide d'un vrai job, sans mot de passe, jeton ni clé).
- [x] AC6: Given `Http::preventStrayRequests()`, when le jeu est chargé, then aucun appel HTTP n'est fait.
- [x] AC7: Given un environnement autre que `local` ou `testing` (`production`, `staging`, `prod`…), when on lance `DemoSeeder` ou n'importe lequel de ses sous-seeders directement, then il s'arrête avec un message clair et n'écrit rien ; `local` et `testing` sont acceptés.
- [x] AC8: Given le jeu déjà chargé, when on relance le seeder, then il ne plante pas, ne recrée rien et l'indique.
- [x] AC9: Given le jeu chargé, when un compte de démo activé se connecte avec le mot de passe documenté (`password`), then il est authentifié ; l'invité ne peut pas se connecter.
- [x] AC10: Given le README, then il documente la commande, les comptes, le mot de passe, `storage:link` et « jamais en production ».

## Technical approach
1. `database/seeders/DemoSeeder.php` : comme chaque sous-seeder, hérite de `Demo\DemoDataSeeder`, dont `__invoke()` refuse tout environnement hors de la liste d'autorisation `local`, `testing` (`RuntimeException` au message français) ; ne fait rien si le compte `camille@famapp.local` existe déjà (message « déjà chargé »), appelle `DatabaseSeeder` si aucun admin n'existe et que `ADMIN_EMAIL` est renseigné (sinon simple avertissement : le second admin de démo suffit), puis appelle dans une transaction les sous-seeders `Demo\DemoAccountsSeeder`, `Demo\DemoLibrarySeeder`, `Demo\DemoGarageSeeder`, `Demo\DemoActivitySeeder`, et affiche le tableau des comptes et le mot de passe.
2. `Demo\DemoMembers` : emails, noms et mot de passe de démo (constantes partagées par les sous-seeders, les tests et la sortie de la commande).
3. Images : `Demo\DemoImageGenerator` dessine avec GD un PNG (aplat de couleur stable selon le texte + titre en police intégrée, translittéré ASCII) et le fait passer par le pipeline existant (`BookCoverService::store`, `ToolPhotoService::store`) : JPEG réencodé, nom aléatoire, disque `public`. Tailles par défaut 400×600 (couverture) et 800×600 (photo), réglables (petites dans les tests).
4. Fabriques complétées : `UserFactory::activated()`, `loggedInAt()`, `AccountRequestFactory::approvedFor(User)`, nouvelle `GoogleBooksRequestFactory` (`HasFactory` sur le modèle). Étagères par `Bookshelf::factory()` / `ToolShelf::factory()` (position suivante calculée par la fabrique), livres et outils par leurs fabriques, marques par `BrandService::resolve()` (unicité normalisée), photos et accessoires par les relations avec positions 1..n.
5. Jobs en échec : `FailedJobProviderInterface::log()` avec une charge utile au format de Laravel (`uuid`, `displayName`, `job` `CallQueuedHandler@call`, `data.command` = `SubmitAccountRequest` sérialisé d'un visiteur fictif) : une relance crée une demande de compte en attente et notifie les admins (Mailpit).
6. README : section « Données de démonstration ».

Hypothèses : le mot de passe de démo `password` (connu, documenté) est volontairement faible : il ne passe pas par `Password::defaults()` (réservé aux mots de passe choisis par un membre) ; l'idempotence est « ne rien recréer » (marqueur = compte de Camille) plutôt qu'une mise à jour ; un échec en cours de chargement annule les lignes (transaction) mais peut laisser des images orphelines sur le disque (sans conséquence en local, `migrate:fresh` puis relance) ; les ISBN sont ceux d'éditions françaises courantes (Folio, Livre de Poche…), leur clé est vérifiée par le test.

## Status
- Branch: feature/F16-seeders-demo
- Tests written: yes (tests/Feature/Core/DemoSeederTest.php)
- Tests passing: yes (composer test, composer lint, npm run test, npm run lint, npm run build)

## Relecture (2026-11-18)
Relecteur indépendant : aucun point bloquant, ISBN vérifiés par sondage (8 sur 9 confirmés). Corrigé : le garde-fou n'était que dans `DemoSeeder` et excluait seulement `production` ; un sous-seeder lancé directement (`db:seed --class="Database\Seeders\Demo\DemoAccountsSeeder" --force`) ou un `APP_ENV=staging` le contournait. Désormais classe de base `Demo\DemoDataSeeder` (liste d'autorisation `local`, `testing`, vérifiée dans `__invoke()`) héritée par les cinq seeders de démonstration ; tests : chaque seeder refuse `production`, `staging` et `prod` sans rien écrire, `local` et `testing` acceptés.
