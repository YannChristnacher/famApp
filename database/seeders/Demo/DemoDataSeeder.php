<?php

namespace Database\Seeders\Demo;

use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Base of every demo seeder: the demo data set creates accounts with a known
 * password, so it only runs in the allowed environments, whichever demo
 * seeder is launched (DemoSeeder or one of its parts).
 */
abstract class DemoDataSeeder extends Seeder
{
    /** Environments where the demo data set may be loaded. */
    public const ALLOWED_ENVIRONMENTS = ['local', 'testing'];

    /**
     * @param  array<array-key, mixed>  $parameters
     *
     * @throws RuntimeException outside the allowed environments
     */
    public function __invoke(array $parameters = [])
    {
        $environment = (string) app()->environment();

        if (! in_array($environment, self::ALLOWED_ENVIRONMENTS, true)) {
            throw new RuntimeException(
                "Les données de démonstration ne peuvent pas être chargées dans l'environnement « {$environment} » "
                .'(autorisés : '.implode(', ', self::ALLOWED_ENVIRONMENTS).') : elles créent des comptes au mot de passe connu.'
            );
        }

        return parent::__invoke($parameters);
    }
}
