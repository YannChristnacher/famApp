# famApp

Inventaire familial partagé : chacun montre aux autres les livres et les outils qu'il possède.

- Cadrage : https://claude.ai/artifact/BXrka7SFLUnJgpQhyAmt6D
- Avancement : [doc/BACKLOG.md](doc/BACKLOG.md)
- Stack : Laravel 13 · Inertia · Vue 3 + TypeScript · Nuxt UI v4 · MySQL · Docker

## Lancer le projet en local (Docker)

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

- Application : http://localhost:8080
- Vite (rechargement à chaud) : http://localhost:5173 — pour tester depuis un téléphone du réseau local, mettre l'IP du PC dans `VITE_HMR_HOST` (`.env`) puis `docker compose up -d vite`
- Mailpit (emails envoyés) : http://localhost:8025

## Données de démonstration (local uniquement)

Pour remplir la base et tout tester à la main : 4 membres activés (dont un second administrateur), une invitation en attente, des demandes de compte, des étagères, 30 vrais livres (ISBN réels, couvertures générées), 20 outils (marques, accessoires, photos générées), un journal Google Books sur 7 jours et 2 jobs en échec relançables.

```bash
docker compose exec app php artisan db:seed --class=DemoSeeder
# ou, pour repartir d'une base vide :
docker compose exec app php artisan migrate:fresh --seed --seeder=DemoSeeder
docker compose exec app php artisan storage:link   # une fois, pour voir les images
```

| Compte | Rôle | Mot de passe |
| --- | --- | --- |
| camille@famapp.local | administrateur | `password` |
| julien@famapp.local | membre | `password` |
| sophie@famapp.local | membre | `password` |
| thomas@famapp.local | membre (jamais connecté) | `password` |
| lea@famapp.local | invitation en attente | aucun (pas de connexion possible) |

- Le compte administrateur de `.env` (`ADMIN_EMAIL`, `ADMIN_PASSWORD`) est créé au passage s'il n'existe pas encore.
- Relancer la commande ne recrée rien ; pour repartir de zéro, utiliser `migrate:fresh` (les anciennes images restent dans `storage/app/public/covers` et `storage/app/public/tools`, à supprimer à la main si besoin).
- Les images sont dessinées localement (aucun téléchargement). Relancer un job en échec depuis « Contrôle » enregistre une demande de compte fictive et envoie un email visible dans Mailpit (worker `queue` démarré).
- **Jamais en production** : chaque seeder de démonstration (`DemoSeeder` comme ses parties `Database\Seeders\Demo\*`) ne tourne que si `APP_ENV` vaut `local` ou `testing` ; tout autre environnement (`production`, `staging`…) est refusé avec un message, sans rien écrire (comptes au mot de passe connu).

## Qualité

```bash
composer test   # Pest
composer lint   # Pint + Larastan
npm run test    # Vitest
npm run lint    # ESLint + vue-tsc
npm run build
```
