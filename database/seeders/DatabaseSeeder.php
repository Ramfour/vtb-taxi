<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::query()->updateOrCreate(
            ['employee_number' => 'EMP0001'],
            [
                'telegram_id' => 700000001,
                'full_name' => 'System Administrator',
                'email' => 'admin@example.com',
                'phone' => '+70000000000',
                'default_address' => 'г. Новосибирск, ул. Кирова, 3',
                'role' => UserRole::Admin,
                'is_active' => true,
                'password' => 'password',
            ],
        );

        User::query()->updateOrCreate(
            ['employee_number' => 'MNG0001'],
            [
                'telegram_id' => 700000002,
                'full_name' => 'Анна Сергеевна Петрова',
                'email' => 'manager@example.com',
                'phone' => '+70000000001',
                'default_address' => 'г. Новосибирск, ул. Фрунзе, 5',
                'role' => UserRole::Manager,
                'is_active' => true,
                'password' => 'password',
            ],
        );

        User::query()->updateOrCreate(
            ['employee_number' => 'EMP0002'],
            [
                'telegram_id' => 700000003,
                'full_name' => 'Иван Дмитриевич Смирнов',
                'email' => 'employee1@example.com',
                'phone' => '+70000000002',
                'default_address' => 'г. Новосибирск, Красный проспект, 25',
                'role' => UserRole::Employee,
                'is_active' => true,
                'password' => 'password',
            ],
        );

        User::query()->updateOrCreate(
            ['employee_number' => 'EMP0003'],
            [
                'telegram_id' => 700000004,
                'full_name' => 'Мария Алексеевна Кузнецова',
                'email' => 'employee2@example.com',
                'phone' => '+70000000003',
                'default_address' => 'г. Новосибирск, ул. Депутатская, 15',
                'role' => UserRole::Employee,
                'is_active' => true,
                'password' => 'password',
            ],
        );

        $this->call(DemoRequestSeeder::class);
    }
}
