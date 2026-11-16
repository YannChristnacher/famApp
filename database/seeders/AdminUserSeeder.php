<?php

namespace Database\Seeders;

use App\Services\Auth\AdminAccountService;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(AdminAccountService $adminAccounts): void
    {
        $adminAccounts->ensureAdmin(
            (string) config('famapp.admin.name'),
            $this->requiredSetting('famapp.admin.email', 'ADMIN_EMAIL'),
            $this->requiredSetting('famapp.admin.password', 'ADMIN_PASSWORD'),
        );
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
}
