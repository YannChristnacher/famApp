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
- Vite (rechargement à chaud) : http://localhost:5173
- Mailpit (emails envoyés) : http://localhost:8025

## Qualité

```bash
composer test   # Pest
composer lint   # Pint + Larastan
npm run test    # Vitest
npm run lint    # ESLint + vue-tsc
npm run build
```
