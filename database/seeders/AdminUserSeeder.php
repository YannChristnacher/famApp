<?php

namespace Database\Seeders;

use App\Services\Auth\AdminAccountService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(AdminAccountService $adminAccounts): void
    {
        $email = $this->requiredSetting('famapp.admin.email', 'ADMIN_EMAIL');
        $password = $this->requiredSetting('famapp.admin.password', 'ADMIN_PASSWORD');
        $this->ensureStrongPassword($password);

        $adminAccounts->ensureAdmin((string) config('famapp.admin.name'), $email, $password);
    }

    private function requiredSetting(string $configKey, string $environmentVariable): string
    {
        $value = config($configKey);

        if (! is_string($value) || $value === '') {
            throw new RuntimeException(
                "Impossible de créer le compte administrateur : renseignez {$environmentVariable} dans le fichier .env."
            );
        }

        return $value;
    }

    private function ensureStrongPassword(string $password): void
    {
        $validator = Validator::make(['password' => $password], ['password' => [Password::default()]]);

        if ($validator->fails()) {
            throw new RuntimeException(
                'Impossible de créer le compte administrateur : ADMIN_PASSWORD est trop faible ('
                .implode(' ', $validator->errors()->all()).')'
            );
        }
    }
}
