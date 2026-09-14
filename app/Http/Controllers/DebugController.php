<?php

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\TempRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class DebugController extends Controller
{
    public function index(): View
    {
        $employees = User::query()
            ->where('role', UserRole::Employee)
            ->whereNull('deleted_at')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_number', 'phone']);

        return view('admin.debug', [
            'currentUser' => request()->user(),
            'employees' => $employees,
            'employeeCount' => $employees->count(),
            'tempRequestCount' => TempRequest::query()->count(),
        ]);
    }

    public function generateEmployees(Request $request): RedirectResponse
    {
        $count = (int) $request->input('count', 5);
        $count = max(1, min(50, $count));

        $lastNames = ['Иванов', 'Петров', 'Сидоров', 'Козлов', 'Новиков', 'Морозов', 'Волков', 'Соколов', 'Лебедев', 'Попов'];
        $firstNames = ['Алексей', 'Дмитрий', 'Сергей', 'Андрей', 'Максим', 'Иван', 'Михаил', 'Артём', 'Николай', 'Владимир'];
        $patronymics = ['Александрович', 'Дмитриевич', 'Сергеевич', 'Андреевич', 'Владимирович', 'Николаевич', 'Михайлович'];

        $created = 0;
        for ($i = 0; $i < $count; $i++) {
            $ln = $lastNames[array_rand($lastNames)];
            $fn = $firstNames[array_rand($firstNames)];
            $pt = $patronymics[array_rand($patronymics)];
            $num = str_pad((string) random_int(10000, 99999), 5, '0', STR_PAD_LEFT);

            if (User::query()->where('employee_number', $num)->exists()) {
                continue;
            }

            User::query()->create([
                'employee_number' => $num,
                'full_name' => "$ln $fn $pt",
                'phone' => '89' . str_pad((string) random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT),
                'password' => Hash::make('password'),
                'role' => UserRole::Employee,
                'is_active' => true,
            ]);
            $created++;
        }

        return redirect()
            ->route('admin.debug.index')
            ->with('status', "Создано сотрудников: {$created}");
    }

    public function generateRequests(Request $request): RedirectResponse
    {
        $request->validate([
            'employee_ids'   => 'required|array|min:1',
            'employee_ids.*' => 'integer|exists:users,id',
            'dates'          => 'required|string',
            'time'           => 'required|string',
            'status'         => 'nullable|string',
        ]);

        $employeeIds = $request->input('employee_ids');
        $time = $request->input('time', '23:00');
        $statusInput = $request->input('status', 'pending');
        $addresses = [
            'Новосибирск, Красный проспект, 25',
            'Новосибирск, ул. Ленина, 14',
            'Новосибирск, ул. Советская, 33',
            'Новосибирск, Вокзальная магистраль, 1',
            'Новосибирск, ул. Депутатская, 46',
        ];

        $statusMap = [
            'pending'   => RequestStatus::Pending,
            'approved'  => RequestStatus::Approved,
            'rejected'  => RequestStatus::Rejected,
            'cancelled' => RequestStatus::Cancelled,
        ];
        $status = $statusMap[$statusInput] ?? RequestStatus::Pending;

        $dates = array_filter(array_map('trim', explode(',', $request->input('dates', ''))));
        $created = 0;

        foreach ($employeeIds as $employeeId) {
            $user = User::find($employeeId);
            if (! $user) {
                continue;
            }

            foreach ($dates as $dateStr) {
                try {
                    $dateTime = Carbon::createFromFormat('d.m.Y H:i', trim($dateStr) . ' ' . $time);
                } catch (\Throwable) {
                    continue;
                }

                $duplicate = TempRequest::query()
                    ->where('user_id', $user->id)
                    ->where('date_time', $dateTime)
                    ->whereIn('status', [RequestStatus::Pending->value, RequestStatus::Approved->value])
                    ->exists();

                if ($duplicate) {
                    continue;
                }

                TempRequest::query()->create([
                    'user_id'    => $user->id,
                    'full_name'  => $user->full_name,
                    'phone'      => $user->phone,
                    'address_raw' => $addresses[array_rand($addresses)],
                    'date_time'  => $dateTime,
                    'status'     => $status,
                ]);
                $created++;
            }
        }

        return redirect()
            ->route('admin.debug.index')
            ->with('status', "Создано заявок: {$created}");
    }

    public function clearTempRequests(): RedirectResponse
    {
        $count = TempRequest::query()->count();
        TempRequest::query()->forceDelete();

        return redirect()
            ->route('admin.debug.index')
            ->with('status', "Удалено заявок из буфера: {$count}");
    }
}
