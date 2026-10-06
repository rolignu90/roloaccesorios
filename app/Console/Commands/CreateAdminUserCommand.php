<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateAdminUserCommand extends Command
{
    protected $signature = 'users:create-admin
        {username : Usuario para iniciar sesión}
        {--name= : Nombre visible}
        {--password= : Contraseña (si no, se genera una temporal)}';

    protected $description = 'Crea (o restablece) un usuario administrador';

    public function handle(): int
    {
        $role = Role::query()->where('is_admin', true)->orderBy('id')->first();
        if (! $role) {
            $this->error('No existe un rol administrador. Corre las migraciones primero.');

            return self::FAILURE;
        }

        $username = Str::lower(trim((string) $this->argument('username')));
        $password = (string) ($this->option('password') ?: Str::password(12, symbols: false));
        $generated = ! $this->option('password');

        $user = User::query()->firstOrNew(['username' => $username]);
        $user->fill([
            'name' => $this->option('name') ?: ($user->name ?: Str::title($username)),
            'password' => $password,
            'role_id' => $role->id,
            'is_active' => true,
            'must_change_password' => $generated,
        ])->save();

        $this->info("Administrador '{$username}' listo.");
        if ($generated) {
            $this->line("Contraseña temporal: {$password}");
            $this->line('Se pedirá cambiarla al iniciar sesión.');
        }

        return self::SUCCESS;
    }
}
