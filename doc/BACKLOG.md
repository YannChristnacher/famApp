# Backlog — famApp

Source de vérité de l'avancement. Une ligne par feature, dans l'ordre de réalisation.
Statuts : `à faire` · `en cours` · `en relecture` · `terminée` · `bloquée`.

Référence fonctionnelle : document de cadrage (https://claude.ai/artifact/BXrka7SFLUnJgpQhyAmt6D).
Chaque feature a son plan dans `doc/features/<id>-<slug>.md` avant d'être codée.

| ID  | Feature | Module | Dépend de | Statut |
| --- | --- | --- | --- | --- |
| F00 | Socle : Laravel, Inertia, Vue + TS, Nuxt UI, Docker, CI, outils qualité | Core | — | terminée |
| F01 | Layout dashboard : sidebar (registre de modules), header (titre, fil d'Ariane, bouton recherche) | Core | F00 | terminée |
| F02 | Authentification : connexion, déconnexion, mot de passe oublié, rôles admin/user, seeder admin | Auth | F01 | terminée |
| F03 | Demande de création de compte : formulaire public, limite de débit, email à l'admin | Auth | F02 | terminée |
| F04 | Admin : liste des demandes, création du compte, email « définir mon mot de passe » | Admin | F03 | terminée |
| F05 | Bibliothèque : CRUD des étagères | Library | F02 | terminée |
| F06 | Bibliothèque : CRUD des livres en saisie manuelle (couverture uploadée, étagères multiples) | Library | F05 | terminée |
| F07 | Bibliothèque : préremplissage par ISBN via Google Books (service, données brutes, couverture stockée) | Library | F06 | en relecture |
| F08 | Bibliothèque : scan du code-barres EAN-13 à la caméra | Library | F07 | à faire |
| F09 | Garage : CRUD des étagères | Garage | F02 | à faire |
| F10 | Garage : CRUD des outils (marques partagées, photos multiples, accessoires) | Garage | F09 | à faire |
| F11 | Consultation des bibliothèques et garages des autres membres (lecture seule) | Library, Garage | F06, F10 | à faire |
| F12 | Recherche globale : endpoint agrégé par module + palette `UDashboardSearch` | Core | F11 | à faire |
| F13 | Page de contrôle admin : demandes, compteurs, derniers ajouts, connexions, jobs en échec, erreurs, appels Google Books | Admin | F12 | à faire |
| F14 | Déploiement production sur VPS (Docker Compose prod, guide, sauvegarde MySQL) ; configurer trustProxies pour le reverse proxy, sinon tous les visiteurs partagent la même IP dans les limiteurs ; les liens d'invitation signés exigent la bonne config proxy/HTTPS (sinon refusés) ; masquer le paramètre `signature` des URL dans les logs Nginx | Ops | F13 | à faire |
