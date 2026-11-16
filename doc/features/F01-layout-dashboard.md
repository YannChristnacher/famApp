# Feature: F01 — Layout dashboard

## Overview
Donner à famApp son squelette d'interface : un dashboard Nuxt UI v4 avec une sidebar à gauche (repliable, en tiroir sur mobile) construite à partir d'un registre de navigation commun aux modules, et un header qui affiche le titre de la page, son fil d'Ariane et un bouton de recherche ouvrant la palette de commandes. La page `Core/Home` est la première à utiliser ce layout. `<UApp>` est déplacé à la racine de l'application (point reporté de la relecture de F00).

## Out of scope
- Authentification, rôles réels, menu utilisateur (F02).
- Recherche effective et endpoint back-end (F12) : la palette affiche seulement un état vide.
- Mode sombre configurable (défaut de Nuxt UI conservé).
- Contenu des modules Library / Garage.

## Acceptance criteria
- [x] AC1: Given le registre de navigation, when on le lit, then il contient une seule entrée « Accueil » (icône, route `home`, sans rôle requis), déclarée par le module Core.
- [x] AC2: Given des entrées dont certaines exigent un rôle, when on filtre avec une liste de rôles, then seules les entrées sans rôle requis ou dont le rôle figure dans la liste sont conservées (liste vide → uniquement les entrées sans rôle).
- [x] AC3: Given des entrées visibles, when on les convertit en éléments de `UNavigationMenu`, then chaque élément porte le libellé, l'icône, l'URL résolue depuis le nom de route et l'état actif de la route courante.
- [x] AC4: Given le layout monté avec un titre et un fil d'Ariane, when il est rendu, then le titre apparaît dans `UDashboardNavbar` et chaque élément du fil d'Ariane dans `UBreadcrumb` ; rien n'est codé en dur dans le layout.
- [x] AC5: Given le layout, when il est rendu, then il contient `UDashboardGroup`, `UDashboardSidebar` (repliable) avec un `UNavigationMenu` construit depuis le registre, `UDashboardPanel` et `UDashboardSearchButton`.
- [x] AC6: Given le layout, when on clique sur le bouton de recherche, then la palette `UDashboardSearch` s'ouvre et affiche un état vide en français.
- [x] AC7: Given l'application, when elle démarre, then `<UApp>` (locale française de Nuxt UI) enveloppe toutes les pages depuis `app.ts`, et la page `Core/Home` ne contient plus son propre `<UApp>`.
- [x] AC8: Given `GET /`, when la page est rendue, then le composant Inertia est `Core/Home` (Pest) ; and given la page montée dans Vitest, then elle rend le layout avec le titre « Accueil », le fil d'Ariane « Accueil » et un message d'accueil.
- [x] AC9: Given le code, when on lance `composer test`, `composer lint`, `npm run test`, `npm run lint`, `npm run build`, then tout passe.

## Technical approach
1. **Routes nommées côté front** : ajout de Ziggy (`tightenco/ziggy` + `ziggy-js`, la solution standard Laravel/Inertia). La directive `@routes` dans `app.blade.php` expose les routes nommées ; le registre ne stocke que le **nom** de route (`home`), résolu en URL au moment de construire le menu. En Vitest, une configuration Ziggy minimale est posée dans `test/setup.ts`. Ziggy-js ne dépend pas de `vendor/`, donc le job CI `frontend` (sans PHP) fonctionne.
2. **Registre** (`resources/js/navigation/`) :
   - `types.ts` : `NavigationEntry { label, icon, route, requiredRole: string | null }`.
   - `modules/core.ts` : entrées du module Core (« Accueil », `i-lucide-house`, `home`).
   - `registry.ts` : `navigationRegistry` (concaténation des entrées des modules), `filterEntriesByRoles(entries, roles)`, `toNavigationMenuItems(entries, { url, isActive })` (résolveurs injectés → testable sans Ziggy).
   - Un nouveau module ajoute son fichier `modules/<module>.ts` et l'ajoute au registre.
3. **Layout** `resources/js/layouts/DashboardLayout.vue` : props `title: string` et `breadcrumb: BreadcrumbItem[]` (type Nuxt UI) déclarées par chaque page, slot par défaut pour le contenu. Structure : `UDashboardGroup` > `UDashboardSidebar` (`collapsible`, `resizable`, mode `slideover` par défaut = tiroir latéral sur mobile ; header avec le nom de l'app, `UNavigationMenu` vertical, `UDashboardSidebarCollapse` en pied) + `UDashboardPanel` (header : `UDashboardNavbar` avec le titre et `UDashboardSearchButton` à droite, puis `UDashboardToolbar` avec `UBreadcrumb` ; body : slot) + `UDashboardSearch` (aucun groupe, slot `empty` en français). Le layout pose aussi `<Head :title>`.
4. **Rôles** : pour F01 la liste de rôles transmise au filtrage est vide (aucun rôle n'existe) ; F02 la branchera sur l'utilisateur connecté.
5. **Racine** : `app.ts` rend `h(UApp, { locale: fr }, () => h(App, props))`. `mountWithUi` (tests) enveloppe de la même manière le composant testé dans `UApp`.
6. **Page** `Core/Home` : utilise le layout (titre « Accueil », fil d'Ariane « Accueil »), message d'accueil dans un `UCard`.

Hypothèses :
- Le fil d'Ariane est placé dans un `UDashboardToolbar` sous la navbar (toujours dans le header du panneau) plutôt qu'au centre de la navbar, pour rester lisible sur mobile.
- « Tiroir sur mobile » = mode `slideover` de `UDashboardSidebar` (panneau latéral gauche), le mode `drawer` de Nuxt UI étant un tiroir par le bas.
- Une entrée de menu n'exige qu'un seul rôle (`requiredRole`), `null` = visible par tous.
- Le layout est utilisé comme composant dans chaque page (et non comme layout persistant Inertia) pour que la page lui passe directement titre et fil d'Ariane ; l'état replié de la sidebar est de toute façon persisté par Nuxt UI (cookie).

## Status
- Branch: feature/F01-layout-dashboard
- Tests written: yes (resources/js/navigation/registry.test.ts, resources/js/layouts/DashboardLayout.test.ts, resources/js/pages/Core/Home.test.ts, tests/Feature/Core/HomeTest.php)
- Tests passing: yes (composer test, composer lint, npm run test, npm run lint, npm run build en local)

Notes d'implémentation :
- `config/ziggy.php` : la fonction `route()` vient du paquet npm `ziggy-js` (`skip-route-function`), les routes internes `storage.*` ne sont pas exposées.
- Ziggy n'imprime la liste complète des routes qu'une fois par processus PHP : `tests/TestCase.php` remet `BladeRouteGenerator::$generated` à `false` avant chaque test.
- Tests Vitest : `usePage()` d'Inertia est simulé (les liens Nuxt UI en mode Inertia en ont besoin) et `<Icon>` d'Iconify est remplacé par un stub hors ligne (`test/stubs/IconifyIconStub.ts`) ; happy-dom démarre sur `http://localhost/`.
- Le groupe « Thème » que `UDashboardSearch` ajoute par défaut est désactivé (`:color-mode="false"`), le mode sombre configurable étant hors périmètre.
- En production, les icônes Lucide sont chargées à la demande depuis l'API Iconify (comportement par défaut de Nuxt UI en mode Vue).
