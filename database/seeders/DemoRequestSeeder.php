<?php

namespace Database\Seeders;

use App\Enums\RequestStatus;
use App\Models\Request as FinalRequest;
use App\Models\TempRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoRequestSeeder extends Seeder
{
    public function run(): void
    {
        $manager = User::query()->where('employee_number', 'MNG0001')->first();
        $employee = User::query()->where('employee_number', 'EMP0002')->first();

        if (! $manager || ! $employee) {
            return;
        }

        TempRequest::query()->updateOrCreate(
            [
                'user_id' => $employee->id,
                'date_time' => Carbon::tomorrow()->setTime(9, 30),
            ],
            [
                'full_name' => $employee->full_name,
                'phone' => $employee->phone,
                'address_raw' => 'г. Новосибирск, ул. Ленина, 12',
                'address_norm' => 'Россия, Новосибирск, улица Ленина, 12',
                'status' => RequestStatus::Pending,
            ],
        );

        FinalRequest::query()->updateOrCreate(
            [
                'user_id' => $employee->id,
                'date_time' => Carbon::tomorrow()->setTime(18, 0),
            ],
            [
                'full_name' => $employee->full_name,
                'phone' => $employee->phone,
                'address_raw' => 'г. Новосибирск, ул. Советская, 8',
                'address_norm' => 'Россия, Новосибирск, Советская улица, 8',
                'status' => RequestStatus::Approved,
                'approved_by' => $manager->id,
                'approved_at' => now(),
            ],
        );
    }
}
