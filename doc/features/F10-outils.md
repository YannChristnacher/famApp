# Feature: F10 — Garage : CRUD des outils

## Overview
Chaque membre inventorie ses outils dans le module Garage : nom, une ou plusieurs photos ordonnées (la première sert de vignette), marque facultative choisie dans une liste partagée par toute la famille (ou créée à la volée), liste ordonnée d'accessoires, et zéro, une ou plusieurs de ses étagères à outils. L'ajout est toujours manuel. `/garage` ouvre désormais « Mes outils ».

## Out of scope
- Consultation des outils des autres membres (F11), recherche globale (F12).
- Écran de gestion des marques (renommage, fusion, suppression) : une marque n'est jamais supprimée.
- Prêt, réservation, état de l'outil, recherche par API.
- Glisser-déposer : les photos et accessoires se réordonnent par boutons monter/descendre (comme les étagères).

## Acceptance criteria
- [x] AC1: Given un membre connecté, when il ouvre `/garage`, then il est redirigé vers `garage.tools.index` (« Mes outils ») ; un invité est redirigé vers la connexion sur toutes les routes `garage.tools.*`.
- [x] AC2: Given des outils de plusieurs membres, when un membre ouvre « Mes outils », then il ne voit que les siens, triés par nom puis id, chacun avec sa vignette (première photo ou `null`), sa marque et ses étagères ; le nombre de requêtes ne dépend pas du nombre d'outils.
- [x] AC3: Given ses étagères et ses marques, when il filtre par `?shelf=<id>` et/ou `?brand=<id>`, then seuls les outils correspondants s'affichent ; une étagère ou une marque qui n'est pas la sienne / pas utilisée par ses outils, inconnue ou non entière répond 404. Les filtres de marque proposés sont les marques de ses outils seulement.
- [x] AC4: Given le formulaire de création, when il envoie nom, marque, accessoires, étagères et photos valides, then l'outil est créé pour lui (jamais pour un `user_id` posté), avec ses accessoires et photos dans l'ordre envoyé, puis il est redirigé vers la fiche avec un toast.
- [x] AC5: Given une marque saisie, when elle correspond à une marque existante une fois normalisée (« bosch », « BOSCH  »), then la marque existante est réutilisée ; sinon une marque est créée avec la casse saisie ; une marque vide laisse l'outil sans marque ; deux créations concurrentes du même nom aboutissent à une seule marque.
- [x] AC6: Given un outil, when il est modifié ou supprimé, then aucune marque n'est jamais supprimée.
- [x] AC7: Given des accessoires, when ils sont envoyés, then ils sont enregistrés dans l'ordre (positions 1..n) et remplacent les précédents (ajout, retrait, réordonnancement) ; un nom vide, de plus de 100 caractères, ou plus de 30 lignes est refusé.
- [x] AC8: Given des photos, when elles sont envoyées (8 au plus, JPEG/PNG/WebP, 10 Mo et 6000 px max), then chacune est redimensionnée (1200 px de large max), réencodée en JPEG sans EXIF sous un nom aléatoire sur le disque `public`, dans l'ordre envoyé ; la première est la vignette.
- [x] AC9: Given un outil avec photos, when il est modifié avec une liste ordonnée mêlant photos existantes (`id`) et nouvelles (`file`), then l'ordre est appliqué, les photos absentes sont supprimées (lignes et fichiers, après le commit) ; un id de photo d'un autre outil est refusé (erreur de validation) et l'autre outil est intact.
- [x] AC10: Given une écriture qui échoue en base, when des photos neuves ont été écrites, then elles sont supprimées du disque et les anciennes restent.
- [x] AC11: Given un outil, when il est supprimé (confirmation), then l'outil, ses photos (lignes et fichiers), ses accessoires et ses liens d'étagère disparaissent ; ses étagères et sa marque restent ; retour à la liste avec toast.
- [x] AC12: Given une étagère d'un autre membre (ou de la bibliothèque) dans `shelf_ids`, then erreur de validation, rien n'est écrit.
- [x] AC13: Given l'outil d'un autre membre, when un membre ou un admin ouvre la fiche, le formulaire, modifie ou supprime, then 404 et rien ne change (Policy `ToolPolicy`).
- [x] AC14: Given les routes `garage.tools.*`, then elles sont exposées à Ziggy pour les connectés et absentes pour un invité.
- [x] AC15: Given F06, when le pipeline d'image est extrait dans un service partagé, then les tests de `BookCoverService` passent inchangés.
- [x] AC16 (Vitest): liste (cartes, vignette ou image neutre, filtres, états vides), formulaire (marque existante / créée, lignes d'accessoires, photos avec aperçus, ordre et retrait, charge utile envoyée en `POST` + `_method=patch` en modification), fiche (galerie, accessoires, étagères), confirmation de suppression.
- [x] AC17 (Playwright): connexion, création d'une étagère, puis d'un outil avec une marque créée à la volée, un accessoire, l'étagère et une photo ; l'outil apparaît dans la liste avec sa vignette.

## Technical approach
1. **Tables** (migration `2026_11_20_000000_create_tools_tables.php`) :
   - `brands` : `id`, `name` (100), `normalized_name` (100, unique, `utf8mb4_bin` sous MySQL), timestamps.
   - `tools` : `id`, `user_id` (FK cascade), `name` (255), `brand_id` (FK nulle, `restrictOnDelete` : une marque n'est jamais supprimée), timestamps, index `user_id`+`name`.
   - `tool_tool_shelf` : `tool_id`, `tool_shelf_id` (FK cascade des deux côtés), clé primaire composite (unicité du couple).
   - `tool_photos` : `id`, `tool_id` (FK cascade), `path`, `position`, timestamps, index `tool_id`+`position`.
   - `tool_accessories` : `id`, `tool_id` (FK cascade), `name` (100), `position`, index `tool_id`+`position`.
2. **Modèles** `app/Models/Garage/{Tool,Brand,ToolPhoto,ToolAccessory}.php` ; `Tool` : `#[Fillable(['name'])]` (ni `user_id` ni `brand_id`), `brand()`, `shelves()`, `photos()` et `accessories()` triés par position, `thumbnail()` = `hasOne()->oldestOfMany('position')` (une seule requête en chargement anticipé). `Brand` : mutateur de `name` qui remplit `normalized_name` via `NormalizedName`. `ToolShelf::tools()`, `User::tools()`.
3. **Image partagée** : `app/Services/Core/ResizedJpegStorage.php` reçoit le pipeline générique de `BookCoverService` (décodage selon le type lu, réduction sans agrandir avant rotation EXIF avec la bonne largeur cible, fond blanc, JPEG sans métadonnées, nom aléatoire, disque `public`) et lève `UnreadableImageException`. `BookCoverService` garde son API et ses constantes et traduit l'exception en erreur `cover` (comportement F06 inchangé). `app/Support/ImageUploadRules.php` porte les règles de validation communes (types JPEG/PNG/WebP par contenu, 10 Mo, `Rule::dimensions` 6000 px : garde avant décodage) ; `BookRequest` s'en sert.
4. **Services Garage** : `ToolPhotoService` (`store`, `delete`, `url`, dossier `tools`, 1200 px), `BrandService::resolve(?string)` (insertion sous point de sauvegarde ; la seconde requête concurrente rattrape la violation d'unicité et relit la marque par une lecture verrouillante), appelé dans la transaction de l'outil, après l'écriture des photos, `ToolService` (`listFor(User, ?ToolShelf, ?Brand)` avec `brand`, `shelves`, `thumbnail` ; `create`, `update`, `delete`). Les nouvelles photos sont écrites avant la transaction et supprimées si elle échoue ; les photos retirées sont supprimées du disque après le commit ; la mise à jour verrouille la ligne de l'outil. Accessoires remplacés en bloc dans l'ordre.
5. **Requêtes** `app/Http/Requests/Garage/{ToolRequest (abstraite),StoreToolRequest,UpdateToolRequest,ListToolsRequest}.php` : `name` requis 255, `brand` nul 100 (trimé), `shelf_ids.*` `exists` parmi les étagères à outils du membre, `accessories` (30 max) liste de noms (requis, 100, trimés), `photos` (8 max) liste ordonnée d'éléments `{id}` (photo existante **de cet outil**, `distinct` ; interdit à la création) ou `{file}` (image). `UpdateToolRequest::authorize()` = `Gate::inspect('update')`.
6. **Policy** `app/Policies/Garage/ToolPolicy.php` : `view`, `update`, `delete`, propriétaire seulement, sinon `denyAsNotFound()`.
7. **Contrôleur** `ToolController` (resource `garage.tools.*`, paramètre `{tool}`), `GarageController` redirige vers `garage.tools.index` ; `ToolResource` (`id`, `name`, `brand` `{id,name}|null`, `thumbnail_url`, `shelves`, et sur la fiche/modification `photos` `{id,url}`, `accessories` `{id,name}`) ; `BrandResource`. Routes ajoutées au groupe `auth` de Ziggy.
8. **Front** : `pages/Garage/Tools/{Index,Create,Edit,Show}.vue`, `components/Garage/{ToolCard,ToolThumbnail,ToolForm,ToolPhotosField,ToolAccessoriesField,ToolDeleteModal}.vue`, types dans `types/garage.ts`. Marque : `USelectMenu` avec `create-item` et `@create` (ajoute la marque aux choix et la sélectionne), `clear`. Photos : `UFileUpload multiple :preview="false"` pour ajouter, liste ordonnée avec aperçus (`URL.createObjectURL`), monter/descendre/retirer, compteur 8 max. Accessoires : lignes `UInput` + boutons. Modification : `POST` + `_method=patch`. Page des étagères : bouton « Mes outils » et lien « Voir les outils » par étagère.
9. **Limites d'envoi** : 25 Mo pour tout le site (nginx `client_max_body_size` et PHP `post_max_size`, comme en F06, routes visiteurs comprises) ; seules la création et la modification d'outil (`location ~ ^/garage/tools(/\d+)?$` dans `docker/nginx/default.conf`) acceptent 90 Mo (8 photos de 10 Mo), avec `PHP_VALUE "post_max_size=90M\nupload_max_filesize=20M"`.

Hypothèses : nom de l'outil 255 caractères ; marque 100 ; accessoires 30 lignes de 100 caractères ; 8 photos ; largeur des photos 1200 px (vignette = même fichier, affichée en `object-cover`) ; tri de la liste par nom ; filtres combinables ; les accessoires n'ont pas d'identité côté serveur (remplacés en bloc), donc un id d'accessoire posté est refusé par la validation (élément non texte).

## Status
- Branch: feature/F10-outils
- Tests written: yes (tests/Feature/Garage/{ToolAccess,ToolIndex,ToolPages,StoreTool,UpdateTool,DestroyTool,BrandService,ToolPhotoService}Test.php, tests/Unit/Garage/ToolPolicyTest.php, GarageRouteListTest ; Vitest pages/Garage/Tools/{Index,Show}.test.ts, components/Garage/ToolForm.test.ts, support/garage/toolFormRows.test.ts, pages/Garage/Shelves/Index.test.ts ; Playwright tests/e2e/tool.spec.ts)
- Tests passing: yes (Pest, Pint + Larastan, Vitest, ESLint + vue-tsc, build, Playwright en local)

Notes d'implémentation :
- Les tests de F06 sont inchangés, sauf le helper `photoWithExif()` déplacé de `BookCoverServiceTest.php` vers `tests/Pest.php` pour être partagé (aucune assertion retirée).
- `ToolShelfIndexTest` et `ToolShelfAccessTest` : redirection de `/garage` ajustée vers `garage.tools.index`.
- Course sur la marque simulée en faisant manquer la vérification d'existence (sous-classe de `BrandService`) : une insertion concurrente faite dans le même test serait annulée avec le point de sauvegarde.
- `scripts/prepare-e2e.sh` lance `php artisan storage:link --force` pour que la vignette soit servie en e2e.

## Relecture (2026-11-18)
Relecteur indépendant : aucun point bloquant. Corrigés :
- **Limite d'envoi** : les 100 Mo s'appliquaient à tout le site, routes visiteurs comprises (corps énormes écrits sur disque avant tout limiteur). Retour à 25 Mo partout ; bloc nginx dédié `^/garage/tools(/\d+)?$` à 90 Mo qui passe directement à `index.php` (pas de `try_files` : nginx contrôle la taille dans la location qui lit le corps, une redirection interne vers `\.php$` imposerait 25 Mo), avec `SCRIPT_FILENAME`, `SCRIPT_NAME` et `DOCUMENT_URI` sur `index.php`. Vérifié avec nginx 1.24 + PHP-FPM 8.3 et un `index.php` sonde : 40 Mo acceptés sur `/garage/tools` et `/garage/tools/{id}` (`post_max_size` = 90M), 413 sur `/account-request` et `/garage/tools/create`, 413 au-delà de 90 Mo. `upload_max_filesize` reste à 20M partout (la validation borne une photo à 10 Mo) : PHP-FPM garde une valeur `PHP_VALUE` dans le processus pour les requêtes suivantes (constaté), ce qui est sans effet sur `post_max_size` puisque nginx limite les autres routes à 25 Mo, mais aurait changé le comportement de F06 pour `upload_max_filesize`.
- **Marque orpheline** : la marque est désormais résolue dans la transaction de l'outil, après l'écriture des photos ; une photo illisible (JPEG tronqué qui passe la validation) ou une écriture en échec ne crée aucune marque. Relecture après violation d'unicité par `lockForUpdate()` (une lecture simple dans une transaction REPEATABLE READ ne verrait pas la ligne validée par la requête concurrente).
- **Suppression** : transaction, verrou de l'outil, lecture des chemins, suppression, puis effacement des fichiers après le commit ; une suppression en échec garde lignes et fichiers. Testé (ordre des requêtes, échec).
