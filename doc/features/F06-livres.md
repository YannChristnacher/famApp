# Feature: F06 — Bibliothèque : CRUD des livres en saisie manuelle

## Overview
Un membre range ses **livres** dans sa bibliothèque : il saisit à la main le titre, l'auteur (un seul champ texte), l'ISBN (facultatif), choisit zéro, une ou plusieurs de **ses** étagères et peut envoyer une photo de couverture, redimensionnée à l'envoi. Il consulte « Mes livres » (grille de cartes filtrable par étagère), la fiche d'un livre, le modifie et le supprime avec confirmation. Depuis la page des étagères, il ouvre les livres d'une étagère. Un même ISBN peut exister plusieurs fois, même chez un seul membre (choix de l'utilisateur).

## Out of scope
- Google Books et préremplissage par ISBN (F07) : la colonne `google_books_data` est créée, jamais exposée ni saisie.
- Scan du code-barres (F08).
- Consultation des livres des autres membres (F11) : tout livre d'un autre répond 404.
- Recherche globale (F12).
- Contrôle d'unicité (ISBN ou titre) : doublons explicitement autorisés.
- Ordre des livres sur une étagère : liste triée par titre puis id.

## Acceptance criteria
- [x] AC1: Given un invité, when il appelle une route `library.books.*` (`index`, `create`, `store`, `show`, `edit`, `update`, `destroy`), then il est redirigé vers `login` et rien ne change.
- [x] AC2: Given un utilisateur connecté, when il ouvre `library.index`, then il est redirigé vers `library.books.index` (`/library/books`), qui rend `Library/Books/Index` avec `books` = ses seuls livres (`id`, `title`, `author`, `isbn`, `cover_url`, `bookshelves` `{id, name}`) triés par titre, et `bookshelves` = ses étagères (pour le filtre), `filter` = null.
- [x] AC3: Given des livres sur plusieurs étagères, when il ouvre `library.books.index?bookshelf=<id>` avec une de ses étagères, then seuls les livres de cette étagère sont listés et `filter` vaut l'id ; given l'étagère d'un autre ou un id inconnu, then 404 ; given un paramètre non entier, then 404.
- [x] AC4: Given 1 livre ou 5 livres avec étagères et couvertures, when il charge la liste, then le nombre de requêtes SQL est identique (chargement anticipé, pas de N+1).
- [x] AC5: Given le formulaire, when il poste titre, auteur, ISBN, deux de ses étagères et une couverture sur `library.books.store`, then le livre est créé pour lui (titre et auteur trimés), rangé sur ces étagères, il est redirigé vers la fiche (`library.books.show`) avec le toast « Le livre « <titre> » a été ajouté. » ; sans étagère ni ISBN ni couverture, then il est aussi créé (`isbn` et `cover_path` nuls).
- [x] AC6: Given un ISBN saisi avec tirets, espaces ou `x` final minuscule (« 978-2-07-036822-8 », « 2 07 036822 x »), then il est enregistré normalisé (« 9782070368228 », « 207036822X ») ; given un ISBN de longueur incorrecte, avec caractères interdits, ou dont la clé de contrôle (ISBN-10 modulo 11, ISBN-13 modulo 10) est fausse, then l'erreur française « Le champ ISBN doit être un ISBN-10 ou ISBN-13 valide. » est renvoyée et rien n'est créé.
- [x] AC7: Given le formulaire, when le titre ou l'auteur est absent ou vide après trim, ou dépasse 255 caractères, then une erreur française est renvoyée sur le champ et rien n'est créé.
- [x] AC8: Given une couverture, when ce n'est pas une image JPEG, PNG ou WebP (ex. PDF, SVG, GIF), dépasse 10 Mo ou 6000 px de côté, then une erreur française est renvoyée sur `cover` et aucun fichier n'est stocké.
- [x] AC9: Given une couverture de 2000×3000 px, when elle est envoyée, then un fichier JPEG est stocké sur le disque `public` sous `covers/` avec un nom généré (jamais celui du client), large de 800 px en gardant les proportions (800×1200) ; given une image de 400×600, then elle n'est pas agrandie ; given un PNG transparent, then il est converti en JPEG sur fond blanc.
- [x] AC10: Given un livre de l'utilisateur, when il poste sur `library.books.update`, then titre, auteur, ISBN et étagères (synchronisées, liste vide = aucune) sont mis à jour, redirection vers la fiche avec le toast « Le livre « <titre> » a été modifié. » ; given une nouvelle couverture, then elle remplace l'ancienne et l'ancien fichier est supprimé du disque ; given `remove_cover`, then la couverture est retirée et son fichier supprimé ; sans couverture ni `remove_cover`, then la couverture existante est conservée.
- [x] AC11: Given une étagère d'un autre utilisateur (ou inexistante) dans `bookshelf_ids`, when il crée ou modifie un livre, then une erreur de validation française est renvoyée et rien ne change ; given deux fois la même étagère, then une erreur de validation est renvoyée.
- [x] AC12: Given deux livres avec le même ISBN chez le même utilisateur, when il crée le second, then il est accepté.
- [x] AC13: Given un livre avec couverture rangé sur une étagère, when il le supprime (`library.books.destroy`), then le livre, ses liens aux étagères et le fichier de couverture disparaissent, l'étagère reste ; redirection vers la liste avec le toast « Le livre « <titre> » a été supprimé. ».
- [x] AC14: Given une étagère contenant des livres (aussi rangés ailleurs), when il supprime l'étagère, then les livres existent toujours, ne sont plus liés à cette étagère et restent sur leurs autres étagères.
- [x] AC15: Given un livre d'un autre utilisateur, when il (user ou admin) appelle `show`, `edit`, `update` ou `destroy`, then 404 et rien ne change ; la Policy `BookPolicy` n'autorise `view`, `update`, `delete` qu'au propriétaire (testée unitairement).
- [x] AC16: Given la liste Ziggy, when un connecté charge une page, then les routes `library.books.*` sont exposées (groupe `auth`) ; pour un invité, aucune.
- [x] AC17 (Vitest): Given la page « Mes livres », then les livres s'affichent en cartes (couverture ou image neutre, titre, auteur, lien vers la fiche) ; les filtres listent « Tous mes livres » et chaque étagère, le filtre actif est mis en avant ; sans livre, then « Vous n'avez encore aucun livre. » et un bouton d'ajout ; filtré sans résultat, then « Aucun livre sur l'étagère « <nom> ». ».
- [x] AC18 (Vitest): Given le formulaire de livre, when on choisit plusieurs étagères dans le `USelectMenu` multiple et une image dans `UFileUpload`, then l'aperçu de l'image s'affiche et le `POST` vers `/library/books` contient `title`, `author`, `isbn`, `bookshelf_ids` et `cover` ; en modification, la couverture actuelle s'affiche, « Retirer la couverture » envoie `remove_cover`, l'envoi part en `POST` avec `_method=patch` vers `/library/books/<id>` ; les erreurs serveur s'affichent sous les champs.
- [x] AC19 (Vitest): Given la fiche d'un livre, when on clique « Supprimer », then une `UModal` nommant le livre s'ouvre ; « Annuler » ne fait rien ; « Supprimer » envoie un `DELETE` vers `/library/books/<id>`.
- [x] AC20 (Vitest): Given la page des étagères, then chaque étagère a un lien « Voir les livres » vers `/library/books?bookshelf=<id>` et la page un lien vers « Mes livres ».
- [x] AC21 (Playwright): Given l'admin seedé, when il se connecte, crée une étagère, crée un livre rangé sur cette étagère, then il arrive sur la fiche et le livre apparaît dans « Mes livres » et dans le filtre de l'étagère.
- [ ] AC22: Given le code, when on lance `composer test`, `composer lint`, `npm run test`, `npm run lint`, `npm run build`, then tout passe, et la CI est verte.

## Technical approach
1. **Données** : migration `create_books_table` : `books` (`id`, `user_id` FK cascade, `title` 255, `author` 255, `isbn` 13 nullable, `cover_path` nullable, `google_books_data` JSON nullable, timestamps, index `user_id`+`title`) et `book_bookshelf` (`book_id`, `bookshelf_id`, FK cascade des deux côtés, clé primaire composite = unicité du couple). La suppression d'une étagère détache ses livres par la cascade de la clé étrangère.
2. **Modèle** `App\Models\Library\Book` (`#[Fillable(['title','author','isbn'])]`, `google_books_data` casté en `array`, `#[UsePolicy]`, `#[UseFactory]`), relations `user()`, `bookshelves()` (triées par position), `isOwnedBy()` ; `Bookshelf::books()`, `User::books()`. Factory avec état `withCover()`.
3. **ISBN** : règle `App\Rules\Library\Isbn` (`normalize()` statique : retire tirets et espaces, majuscules ; validation ISBN-10 `^\d{9}[\dX]$` + somme pondérée modulo 11, ISBN-13 `^\d{13}$` + poids 1/3 modulo 10). La normalisation a lieu dans `prepareForValidation()`.
4. **Requests** : `BookRequest` abstraite (`title`/`author` `required|string|max:255`, `isbn` `nullable|string` + `Isbn`, `bookshelf_ids` `array`, `bookshelf_ids.*` `integer|distinct` + `Rule::exists('bookshelves','id')->where('user_id', …)`, `cover` `nullable` + `File::image()->types(jpeg,png,webp)->max(10 Mo)` + dimensions max 6000) ; `StoreBookRequest`, `UpdateBookRequest` (`Gate::inspect('update')` dans `authorize()` : 404 avant validation, + `remove_cover` booléen) ; `ListBooksRequest` (`bookshelfFilter()` : l'étagère du paramètre `bookshelf` parmi celles de l'utilisateur, sinon 404).
5. **Couverture** : `App\Services\Library\BookCoverService` : `store(UploadedFile): string` (GD natif : `imagecreatefromstring`, orientation EXIF des JPEG, réduction à 800 px de large maximum par `imagecopyresampled` sur fond blanc, encodage JPEG qualité 85, `covers/<40 caractères aléatoires>.jpg` sur le disque `public`) et `delete(?string)`. Aucun paquet ajouté : GD est présent en local, en CI (`setup-php` `gd`) et dans l'image Docker (`--with-jpeg --with-webp`).
6. **Service** `App\Services\Library\BookService` : `listFor(User, ?Bookshelf)` (avec `bookshelves`, tri titre puis id), `create`, `update`, `delete`. Le fichier est écrit avant la transaction et supprimé si elle échoue ; l'ancien fichier n'est supprimé qu'après la validation de la transaction.
7. **Policy** `BookPolicy` : `view`, `update`, `delete` → propriétaire, sinon `Response::denyAsNotFound()`.
8. **Contrôleur** `Library\BookController` (resource complète), `BookResource` (`cover_url` via `Storage::disk('public')->url()`), toasts. `LibraryController` redirige désormais vers `library.books.index` (« Bibliothèque » ouvre « Mes livres ») : les deux tests F05 qui vérifiaient la redirection vers les étagères sont ajustés.
9. **Ziggy** : `library.books.{index,create,store,show,edit,update,destroy}` dans le groupe `auth`.
10. **Front** : `types/library.ts` (`Book`, `BookshelfSummary`) ; pages `Library/Books/{Index,Create,Edit,Show}.vue` ; composants `components/Library/{BookCard,BookCover,BookForm,BookDeleteModal}.vue` ; `BookForm` : `UInput`, `USelectMenu multiple value-key="id" label-key="name"`, `UFileUpload` (aperçu intégré), envoi `useForm` (FormData automatique dès qu'il y a un fichier ; modification en `POST` + `_method: 'patch'`, PHP ne lisant pas le multipart en PATCH). Image neutre : bloc `UIcon` `i-lucide-book` (pas de fichier). Page des étagères : bouton « Mes livres » et lien « Voir les livres » par étagère.
11. **Playwright** : `tests/e2e/book.spec.ts`.

Hypothèses :
- Longueurs max : titre et auteur 255 caractères ; image 10 Mo et 6000 px de côté (au-delà, GD consommerait trop de mémoire ; `upload_max_filesize` est à 20 Mo dans l'image Docker).
- Couverture redimensionnée à 800 px de large maximum, toujours réencodée en JPEG (taille réduite, métadonnées EXIF dont la géolocalisation retirées).
- Le filtre par étagère inconnue ou d'un autre répond 404 (même règle que F05) plutôt qu'une liste vide.
- Après création ou modification, retour sur la fiche du livre ; après suppression, retour à la liste.
- Une étagère d'un autre dans `bookshelf_ids` est une erreur de validation (« Une des étagères choisies n'existe pas. »), sans révéler qu'elle existe.

## Status
- Branch: feature/F06-livres
- Tests written: yes (tests/Feature/Library/{BookAccess,BookIndex,BookPages,StoreBook,UpdateBook,DestroyBook,BookCoverService,BookshelfDeletionKeepsBooks}Test.php, tests/Unit/Library/{Isbn,BookPolicy}Test.php, resources/js/pages/Library/Books/{Index,Show}.test.ts, resources/js/components/Library/BookForm.test.ts, resources/js/pages/Library/Bookshelves/Index.test.ts, tests/e2e/book.spec.ts)
- Tests passing: yes en local (composer test, composer lint, npm run test, npm run lint, npm run build, Playwright avec Chrome local) ; AC22 en attente de la CI de la PR.

Notes d'implémentation :
- L'orientation EXIF des photos JPEG est appliquée **après** la réduction (une rotation copie l'image : sur l'original, la mémoire doublerait) ; testé avec un en-tête EXIF fabriqué (orientations 1, 3, 6, 8).
- Tests F05 ajustés (aucun retiré) : `BookshelfIndexTest` et `BookshelfAccessTest` attendent maintenant la redirection de `/library` vers `library.books.index` ; `LibraryRouteListTest` liste aussi les routes `library.books.*`.
- La suppression d'une étagère détache ses livres par la cascade de la clé étrangère du pivot (aucun code dans `BookshelfService`).
- Les covers s'affichent via `public/storage` : en développement local, `php artisan storage:link` est nécessaire (l'image prod le fait déjà).
