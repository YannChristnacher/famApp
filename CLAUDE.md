# famApp — instructions pour Claude et ses agents

Inventaire familial partagé : chaque membre montre aux autres ses **livres** (module Library) et ses **outils** (module Garage). Ce n'est pas un système de prêt ni de réservation.

- Cadrage complet : https://claude.ai/artifact/BXrka7SFLUnJgpQhyAmt6D
- Avancement : `doc/BACKLOG.md` (à mettre à jour à chaque changement de statut)
- Plans de features : `doc/features/<id>-<slug>.md`

## Stack

- Laravel (dernière version stable), PHP ≥ 8.3, Fortify pour l'authentification
- Inertia + Vue 3 (Composition API, `<script setup lang="ts">`) + TypeScript strict
- Nuxt UI v4 (`router: 'inertia'`) + Tailwind CSS 4 — **toujours utiliser un composant Nuxt UI quand il existe** avant d'en écrire un
- MySQL (Docker) ; tests locaux en SQLite mémoire, CI sur MySQL
- Docker Compose : app (PHP-FPM + Nginx), queue worker, mysql, mailpit, vite (dev)

## Architecture

Découpage par module métier, dans les dossiers Laravel classiques :

```
app/Http/Controllers/<Module>/XxxController.php
app/Http/Requests/<Module>/StoreXxxRequest.php
app/Models/<Module>/Xxx.php
app/Policies/<Module>/XxxPolicy.php
app/Services/<Module>/XxxService.php
resources/js/pages/<Module>/...
resources/js/components/<Module>/...
tests/Feature/<Module>/... · tests/Unit/<Module>/...
```

Modules : `Core`, `Auth`, `Admin`, `Library`, `Garage`. Un nouveau module déclare son entrée de menu dans le registre de navigation et fournit sa source de recherche globale.

## Règles de code (d'après alexeymezenin/laravel-best-practices)

- Contrôleurs minces : validation dans des Form Requests, logique métier dans des services, droits dans des Policies.
- Une responsabilité par classe et par méthode ; des noms explicites plutôt que des commentaires.
- Eloquent + collections, pas de SQL brut ; chargement anticipé des relations (pas de N+1).
- Affectation en masse depuis `$request->validated()`.
- Injection de dépendances, pas de `new` sur les services ; `config()` dans le code, jamais `env()`.
- Aucune logique dans les fichiers de routes ; routes nommées.
- PSR-12 et conventions Laravel (vérifiées par Pint).
- Interface **en français uniquement, sans fichiers de traduction** (seuls les messages de validation Laravel sont en `lang/fr`). Code, noms, commits en anglais.

## Tests — obligatoires

Toute logique fonctionnelle est couverte. Tests écrits **avant** le code.

- Pest : routes, Form Requests, Policies, services, emails (`Mail::fake()`), API externes (`Http::fake()`).
- Vitest + Vue Test Utils : composants et composables qui portent de la logique.
- Playwright : uniquement les parcours clés (connexion, ajout livre, ajout outil).

## Commandes

```
composer test          # Pest
composer lint          # Pint --test + Larastan
npm run test           # Vitest
npm run lint           # ESLint + vue-tsc
npm run build
```

Une feature n'est terminée que si **toutes** ces commandes passent.

Conventions de test :
- Pest : utiliser les fonctions `Pest\Laravel\get()`, `post()`, `actingAs()`… plutôt que `$this->get()` (Larastan ne sait pas typer `$this` dans les closures Pest).
- Vitest : monter les composants avec `mountWithUi()` (`resources/js/test/mountWithUi.ts`) ; `<Head>` d'Inertia est neutralisé dans `resources/js/test/setup.ts`.
- Les tests n'ont pas besoin du build Vite (`withoutVite()` dans `tests/TestCase.php`).

## Environnement de travail de Claude (session cloud)

- PHP 8.3 en local (le Docker et la CI utilisent 8.4) ; `composer.json` fige `config.platform.php` à 8.3.6 pour que le lock reste installable partout.
- Les archives GitHub (`api.github.com/.../zipball`) sont refusées par le proxy : installer avec `composer require|update --prefer-source`. `phpstan/phpstan` n'a pas de source git : en cas de nouvelle version, lancer `scripts/seed-phpstan-cache.sh <version>` avant Composer.
- Pas de démon Docker : les tests tournent directement en PHP/Node, la CI construit l'image.
- Exporter `COMPOSER_ALLOW_SUPERUSER=1` avant d'appeler Composer.

## Workflow (boucle autonome)

1. Prendre la première feature `à faire` du backlog dont les dépendances sont `terminée`.
2. Créer la branche `feature/<id>-<slug>` depuis `main`, passer la feature `en cours`.
3. Écrire le plan (skill `plan-feature` → `doc/features/`).
4. Écrire les tests, puis le code, jusqu'à ce que toutes les commandes passent.
5. Commits Conventional Commits (`feat:`, `fix:`, `test:`, `chore:`, `docs:`), push, pull request.
6. Relecture par un agent indépendant qui n'a pas écrit le code. Problème → retour à l'étape 4.
7. Relecture OK + CI verte → merge sur `main`, feature `terminée` dans le backlog.

Ne jamais pousser directement sur `main` après F00. Ne jamais désactiver ou supprimer un test pour faire passer la CI.
