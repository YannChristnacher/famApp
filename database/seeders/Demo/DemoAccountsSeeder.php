<?php

namespace Database\Seeders\Demo;

use App\Models\Auth\AccountRequest;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Demo accounts (activated members, one of them administrator, and a pending
 * invitation) and account requests in every status.
 */
class DemoAccountsSeeder extends DemoDataSeeder
{
    public function run(): void
    {
        $this->activatedMembers();
        $invited = $this->invitedMember();
        $this->accountRequests($invited);
    }

    private function activatedMembers(): void
    {
        $lastLogins = [
            DemoMembers::ADMIN_EMAIL => now()->subHours(2),
            DemoMembers::JULIEN_EMAIL => now()->subDay()->subHours(3),
            DemoMembers::SOPHIE_EMAIL => now()->subDays(5),
            DemoMembers::THOMAS_EMAIL => null,
        ];

        foreach (DemoMembers::ACTIVATED as $email => $name) {
            $factory = User::factory()
                ->activated()
                ->loggedInAt($lastLogins[$email])
                ->state(['name' => $name, 'email' => $email, 'password' => DemoMembers::PASSWORD]);

            ($email === DemoMembers::ADMIN_EMAIL ? $factory->admin() : $factory)->create();
        }
    }

    /**
     * Created by an administrator from an account request: random password
     * nobody knows, until the invitation is used.
     */
    private function invitedMember(): User
    {
        return User::factory()
            ->invited()
            ->loggedInAt(null)
            ->create([
                'name' => DemoMembers::INVITED_NAME,
                'email' => DemoMembers::INVITED_EMAIL,
                'password' => Str::random(64),
                'created_at' => now()->subDays(2),
            ]);
    }

    private function accountRequests(User $invited): void
    {
        AccountRequest::factory()->create([
            'name' => 'Marc Dubois',
            'email' => 'marc.dubois@famapp.local',
            'message' => 'Bonjour, je suis le cousin de Julien, il m\'a parlé de votre inventaire.',
            'created_at' => now()->subHours(5),
        ]);
        AccountRequest::factory()->create([
            'name' => 'Nathalie Roux',
            'email' => 'nathalie.roux@famapp.local',
            'message' => null,
            'created_at' => now()->subDay(),
        ]);
        AccountRequest::factory()->approvedFor($invited)->create([
            'message' => 'Coucou, c\'est Léa ! Je voudrais voir les livres de tout le monde.',
            'created_at' => now()->subDays(3),
        ]);
        AccountRequest::factory()->rejected()->create([
            'name' => 'Kevin Durand',
            'email' => 'kevin.durand@famapp.local',
            'message' => 'Offre spéciale sur nos perceuses, contactez-nous.',
            'created_at' => now()->subDays(6),
        ]);
    }
}
