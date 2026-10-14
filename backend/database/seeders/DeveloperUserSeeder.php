<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Cuenta con acceso al tablero de uso.
 *
 * A diferencia del administrador, esta no trae valores por defecto: si no se
 * configura DEVELOPER_EMAIL no se crea nada. Una cuenta de desarrollo con
 * correo y contrasenia conocidos, sembrada en cada despliegue, seria una
 * puerta abierta en produccion.
 */
class DeveloperUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('DEVELOPER_EMAIL');
        $password = env('DEVELOPER_PASSWORD');

        if (blank($email) || blank($password)) {
            $this->command?->warn(
                'DEVELOPER_EMAIL o DEVELOPER_PASSWORD sin configurar: no se creó la cuenta de desarrollo.',
            );

            return;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => env('DEVELOPER_NAME', 'Desarrollo'),
                'password' => Hash::make($password),
                'role' => User::ROLE_DEVELOPER,
                'email_verified_at' => now(),
            ],
        );

        $this->command?->info("Cuenta de desarrollo lista: {$user->email}");
    }
}
