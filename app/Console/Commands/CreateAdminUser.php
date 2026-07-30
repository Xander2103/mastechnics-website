<?php

namespace App\Console\Commands;

use App\Models\AdminUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create {--update : Overwrite the password of an existing admin account}';

    protected $description = 'Interactively create (or with --update, update) an admin account with a securely hashed password';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->ask('E-mailadres')));

        $validator = Validator::make(
            ['email' => $email],
            ['email' => ['required', 'email:filter', 'max:255']]
        );

        if ($validator->fails()) {
            $this->error('Ongeldig e-mailadres.');

            return self::FAILURE;
        }

        $existing = AdminUser::where('email', $email)->first();

        if ($existing !== null && ! $this->option('update')) {
            $this->error('Er bestaat al een admin met dit e-mailadres. Gebruik --update om het wachtwoord bij te werken.');

            return self::FAILURE;
        }

        if ($existing === null && $this->option('update')) {
            $this->error('Geen bestaande admin met dit e-mailadres gevonden om bij te werken.');

            return self::FAILURE;
        }

        $name = $existing?->name
            ?? trim((string) $this->ask('Naam', ucfirst(strstr($email, '@', true) ?: 'Admin')));

        // secret() keeps the password out of the terminal echo and history;
        // it is never printed back and never logged.
        $password = (string) $this->secret('Wachtwoord (min. 12 tekens, met letters en cijfers)');
        $confirmation = (string) $this->secret('Bevestig wachtwoord');

        if ($password !== $confirmation) {
            $this->error('De wachtwoorden komen niet overeen.');

            return self::FAILURE;
        }

        if (! $this->passwordIsStrong($password)) {
            $this->error('Het wachtwoord moet minstens 12 tekens lang zijn en zowel letters als cijfers bevatten.');

            return self::FAILURE;
        }

        $action = $existing !== null ? 'bijwerken' : 'aanmaken';

        if (! $this->confirm("Admin-account voor {$email} {$action}?", true)) {
            $this->info('Geannuleerd. Er werd niets gewijzigd.');

            return self::SUCCESS;
        }

        AdminUser::updateOrCreate(
            ['email' => $email],
            ['name' => $name !== '' ? $name : 'Admin', 'password' => Hash::make($password)]
        );

        $this->info("Admin-account voor {$email} werd " . ($existing !== null ? 'bijgewerkt.' : 'aangemaakt.'));

        return self::SUCCESS;
    }

    private function passwordIsStrong(string $password): bool
    {
        return mb_strlen($password) >= 12
            && preg_match('/\p{L}/u', $password) === 1
            && preg_match('/\d/', $password) === 1;
    }
}
