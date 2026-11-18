<?php

namespace Database\Seeders\Demo;

/**
 * Accounts of the demo data set: names, emails and the known password,
 * shared by the demo seeders, the output of the command and the tests.
 */
final class DemoMembers
{
    /** Known password of the activated demo accounts: local use only. */
    public const PASSWORD = 'password';

    public const ADMIN_EMAIL = 'camille@famapp.local';

    public const JULIEN_EMAIL = 'julien@famapp.local';

    public const SOPHIE_EMAIL = 'sophie@famapp.local';

    public const THOMAS_EMAIL = 'thomas@famapp.local';

    public const INVITED_EMAIL = 'lea@famapp.local';

    /**
     * Activated accounts: email => name.
     *
     * @var array<string, string>
     */
    public const ACTIVATED = [
        self::ADMIN_EMAIL => 'Camille Martin',
        self::JULIEN_EMAIL => 'Julien Martin',
        self::SOPHIE_EMAIL => 'Sophie Lefèvre',
        self::THOMAS_EMAIL => 'Thomas Martin',
    ];

    public const INVITED_NAME = 'Léa Martin';

    /**
     * @return list<string>
     */
    public static function activatedEmails(): array
    {
        return array_keys(self::ACTIVATED);
    }
}
