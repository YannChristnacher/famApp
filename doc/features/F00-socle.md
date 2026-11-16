# Feature: F00 — Socle technique

## Overview
Mettre en place une application Laravel 13 + Inertia + Vue 3 (TypeScript) + Nuxt UI v4 qui démarre, s'affiche en français, et dont toute la chaîne qualité (tests, lint, analyse statique, build, image Docker, CI) fonctionne. Toutes les features suivantes s'appuient sur ce socle.

## Out of scope
- Authentification, rôles, comptes (F02 et suivantes).
- Layout dashboard, sidebar, header (F01).
- Déploiement en production (F14).
- Tests Playwright (ajoutés avec le premier parcours clé, F02).

## Acceptance criteria
- [x] AC1: Given l'application installée, when on fait `GET /`, then la réponse est 200 et rend le composant Inertia `Core/Home`.
- [x] AC2: Given la page `Home`, when elle est montée dans Vitest, then elle affiche le nom de l'application dans un composant Nuxt UI (`UApp` racine).
- [x] AC3: Given la configuration, when on lit `config('app.locale')`, then la valeur est `fr`, et un message de validation `required` est en français (contient « obligatoire »).
- [x] AC4: Given le code, when on lance `composer lint`, then Pint (`--test`) et Larastan (niveau 6) passent sans erreur.
- [x] AC5: Given le code, when on lance `npm run lint`, then ESLint et `vue-tsc --noEmit` passent sans erreur.
- [x] AC6: Given le code, when on lance `composer test` et `npm run test`, then Pest et Vitest passent.
- [x] AC7: Given le code, when on lance `npm run build`, then `public/build/manifest.json` est produit.
- [x] AC8: Given `.env.example`, then il pointe vers les services Docker (`DB_HOST=mysql`, `MAIL_HOST=mailpit`), et `phpunit.xml` utilise SQLite en mémoire.
- [ ] AC9: Given la CI GitHub Actions, when une pull request est ouverte, then les jobs `backend` (MySQL), `frontend` et `docker` (build de l'image `prod`) sont verts.

## Technical approach
1. `composer create-project laravel/laravel` (13.x), sans starter kit : l'UI du starter kit officiel (shadcn-vue) serait remplacée de toute façon par Nuxt UI.
2. Back : `inertiajs/inertia-laravel`, middleware `HandleInertiaRequests`, vue racine `app.blade.php`, contrôleur `Core\HomeController`.
3. Front : `vue`, `@inertiajs/vue3`, `@nuxt/ui` (plugin Vite avec `router: 'inertia'`), `typescript`, `vue-tsc`. Versions d'Inertia et de Nuxt UI choisies pour être compatibles (cf. nuxt/ui#6245).
4. Français : `APP_LOCALE=fr`, `APP_FALLBACK_LOCALE=fr`, fichiers `lang/fr/*` pour les messages Laravel (validation, auth, passwords, pagination).
5. Qualité : Pest, Pint, Larastan ; ESLint (flat config Vue + TS), Vitest + `@vue/test-utils` + `happy-dom`.
6. Scripts : `composer test`, `composer lint`, `npm run test`, `npm run lint`, `npm run build`.
7. Docker et CI déjà présents (commit initial) : ajustés si besoin pour que l'image `prod` se construise.

Risque : la compatibilité Nuxt UI ↔ Inertia (peer dependency). On fige les versions qui s'installent sans `--force`.

## Status
- Branch: feature/F00-socle
- Tests written: yes (tests/Feature/Core/HomeTest.php, tests/Feature/Core/LocaleTest.php, resources/js/pages/Core/Home.test.ts)
- Tests passing: yes en local (AC1 à AC8) ; AC9 vérifié sur la pull request
