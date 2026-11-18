<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\Demo\DemoAccountsSeeder;
use Database\Seeders\Demo\DemoActivitySeeder;
use Database\Seeders\Demo\DemoGarageSeeder;
use Database\Seeders\Demo\DemoLibrarySeeder;
use Database\Seeders\Demo\DemoMembers;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Demo data set for local use: php artisan db:seed --class=DemoSeeder
 * (or migrate:fresh --seed --seeder=DemoSeeder to start again). Refuses to
 * run in production; does nothing once the demo data is loaded.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException(
                'Les données de démonstration ne peuvent pas être chargées en production (APP_ENV=production) : '
                .'elles créent des comptes au mot de passe connu.'
            );
        }

        if (User::query()->where('email', DemoMembers::ADMIN_EMAIL)->exists()) {
            $this->command?->warn(
                'Les données de démonstration sont déjà chargées : rien n\'a été recréé. '
                .'Pour repartir de zéro : php artisan migrate:fresh --seed --seeder=DemoSeeder'
            );

            return;
        }

        $this->ensureConfiguredAdmin();

        DB::transaction(fn () => $this->call([
            DemoAccountsSeeder::class,
            DemoLibrarySeeder::class,
            DemoGarageSeeder::class,
            DemoActivitySeeder::class,
        ]));

        $this->printAccounts();
    }

    /**
     * The administrator account of the configuration (DatabaseSeeder), when
     * there is no administrator yet and ADMIN_EMAIL is filled in.
     */
    private function ensureConfiguredAdmin(): void
    {
        if (User::query()->where('role', Role::Admin)->exists()) {
            return;
        }

        if (blank(config('famapp.admin.email'))) {
            $this->command?->warn(
                'ADMIN_EMAIL n\'est pas renseigné : le compte administrateur de la configuration n\'est pas créé '
                .'(le compte de démonstration '.DemoMembers::ADMIN_EMAIL.' est administrateur).'
            );

            return;
        }

        $this->call(DatabaseSeeder::class);
    }

    private function printAccounts(): void
    {
        $this->command?->info('Données de démonstration chargées. Comptes (mot de passe : '.DemoMembers::PASSWORD.') :');

        $rows = User::query()
            ->whereIn('email', [...DemoMembers::activatedEmails(), DemoMembers::INVITED_EMAIL])
            ->orderBy('id')
            ->get()
            ->map(fn (User $user) => [
                $user->name,
                $user->email,
                $user->isAdmin() ? 'administrateur' : 'membre',
                $user->hasSetPassword() ? DemoMembers::PASSWORD : 'invitation en attente (pas de connexion)',
            ])
            ->all();

        $this->command?->table(['Nom', 'Email', 'Rôle', 'Mot de passe'], $rows);
        $this->command?->line('Pour voir les images : php artisan storage:link. Jamais en production.');
    }
}
