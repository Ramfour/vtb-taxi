<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['employee_number' => 'EMP0001'],
            [
                'telegram_id' => '700000001',
                'full_name' => 'System Administrator',
                'phone' => '80000000000',
                'role' => UserRole::Admin,
                'is_active' => true,
                'password' => 'password',
            ],
        );

        UserAddress::query()->updateOrCreate(
            ['user_id' => $admin->id, 'address' => 'г. Новосибирск, ул. Кирова, 3'],
            ['address' => 'г. Новосибирск, ул. Кирова, 3'],
        );

        $manager = User::query()->updateOrCreate(
            ['employee_number' => 'MNG0001'],
            [
                'telegram_id' => '700000002',
                'full_name' => 'Анна Сергеевна Петрова',
                'phone' => '80000000001',
                'role' => UserRole::Manager,
                'is_active' => true,
                'password' => 'password',
            ],
        );

        UserAddress::query()->updateOrCreate(
            ['user_id' => $manager->id, 'address' => 'г. Новосибирск, ул. Фрунзе, 5'],
            ['address' => 'г. Новосибирск, ул. Фрунзе, 5'],
        );

        $employee1 = User::query()->updateOrCreate(
            ['employee_number' => 'EMP0002'],
            [
                'telegram_id' => '700000003',
                'full_name' => 'Иван Дмитриевич Смирнов',
                'phone' => '80000000002',
                'role' => UserRole::Employee,
                'is_active' => true,
                'password' => 'password',
            ],
        );

        UserAddress::query()->updateOrCreate(
            ['user_id' => $employee1->id, 'address' => 'г. Новосибирск, Красный проспект, 25'],
            ['address' => 'г. Новосибирск, Красный проспект, 25'],
        );

        $employee2 = User::query()->updateOrCreate(
            ['employee_number' => 'EMP0003'],
            [
                'telegram_id' => '700000004',
                'full_name' => 'Мария Алексеевна Кузнецова',
                'phone' => '80000000003',
                'role' => UserRole::Employee,
                'is_active' => true,
                'password' => 'password',
            ],
        );

        UserAddress::query()->updateOrCreate(
            ['user_id' => $employee2->id, 'address' => 'г. Новосибирск, ул. Депутатская, 15'],
            ['address' => 'г. Новосибирск, ул. Депутатская, 15'],
        );

        $this->call(DemoRequestSeeder::class);
    }
}
