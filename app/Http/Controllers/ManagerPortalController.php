<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkApproveTempRequestsRequest;
use App\Http\Requests\ExportRequestsCsvRequest;
use App\Http\Requests\FinalizeApprovedRequestsRequest;
use App\Http\Requests\ReviewTempRequestRequest;
use App\Http\Requests\UpdateFinalRequestRequest;
use App\Models\Invitation;
use App\Models\Request as FinalRequest;
use App\Models\TempRequest;
use App\Models\User;
use App\Models\AuditLog;
use App\Enums\UserRole;
use App\Services\AuditLogger;
use App\Services\RequestWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class ManagerPortalController extends Controller
{
    public function __construct(
        private readonly RequestWorkflowService $workflowService,
    ) {
    }

    public function index(): View
    {
        return $this->renderDashboard();
    }

    public function employees(): View
    {
        $currentUser = request()->user();
        $roles = $this->isNotAdmin($currentUser)
            ? [UserRole::Employee]
            : [UserRole::Employee, UserRole::Manager];

        $query = User::query()
            ->whereIn('role', $roles)
            ->whereNull('deleted_at');

        $search = trim((string) request()->query('q', ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('full_name', 'like', '%'.$search.'%')
                    ->orWhere('employee_number', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        return view('manager.employees', [
            'currentUser' => $currentUser,
            'invitations' => Invitation::query()
                ->latest()
                ->limit(20)
                ->get(),
            'employees' => $query
                ->with('latestAddress')
                ->orderBy('full_name')
                ->paginate(12)
                ->withQueryString(),
            'search' => $search,
        ]);
    }

    public function audit(): View
    {
        if ($this->isNotAdmin(request()->user())) {
            abort(403);
        }

        $search = trim((string) request()->query('q', ''));
        $action = trim((string) request()->query('action', ''));
        $entity = trim((string) request()->query('entity', ''));
        $from = request()->query('from');
        $to = request()->query('to');

        $query = AuditLog::query()
            ->with('user')
            ->latest('created_at');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('action', 'like', '%'.$search.'%')
                    ->orWhere('entity_type', 'like', '%'.$search.'%')
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery
                            ->where('full_name', 'like', '%'.$search.'%')
                            ->orWhere('employee_number', 'like', '%'.$search.'%');
                    });
            });

            if (ctype_digit($search)) {
                $query->orWhere('entity_id', (int) $search);
            }
        }

        if ($action !== '') {
            $query->where('action', $action);
        }

        if ($entity !== '') {
            $query->where('entity_type', $entity);
        }

        if ($from) {
            $query->where('created_at', '>=', Carbon::parse($from));
        }

        if ($to) {
            $query->where('created_at', '<=', Carbon::parse($to));
        }

        $actions = AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->limit(50)
            ->pluck('action')
            ->map(fn (string $actionItem) => [
                'value' => $actionItem,
                'label' => $this->actionLabel($actionItem),
            ]);

        $entities = AuditLog::query()
            ->select('entity_type')
            ->distinct()
            ->orderBy('entity_type')
            ->limit(50)
            ->pluck('entity_type');

        return view('manager.audit', [
            'currentUser' => request()->user(),
            'logs' => $query->paginate(20)->withQueryString(),
            'search' => $search,
            'actionFilter' => $action,
            'entityFilter' => $entity,
            'from' => $from,
            'to' => $to,
            'actions' => $actions,
            'entities' => $entities,
            'actionLabelMap' => $this->actionLabelMap(),
        ]);
    }

    public function exportAuditCsv(): StreamedResponse
    {
        if ($this->isNotAdmin(request()->user())) {
            abort(403);
        }

        $search = trim((string) request()->query('q', ''));
        $action = trim((string) request()->query('action', ''));
        $entity = trim((string) request()->query('entity', ''));
        $from = request()->query('from');
        $to = request()->query('to');

        $query = AuditLog::query()
            ->with('user')
            ->latest('created_at');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('action', 'like', '%'.$search.'%')
                    ->orWhere('entity_type', 'like', '%'.$search.'%')
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery
                            ->where('full_name', 'like', '%'.$search.'%')
                            ->orWhere('employee_number', 'like', '%'.$search.'%');
                    });
            });

            if (ctype_digit($search)) {
                $query->orWhere('entity_id', (int) $search);
            }
        }

        if ($action !== '') {
            $query->where('action', $action);
        }

        if ($entity !== '') {
            $query->where('entity_type', $entity);
        }

        if ($from) {
            $query->where('created_at', '>=', Carbon::parse($from));
        }

        if ($to) {
            $query->where('created_at', '<=', Carbon::parse($to));
        }

        $logs = $query->limit(2000)->get();
        $timestamp = now()->format('Ymd_His');
        $filename = "vtb_audit_{$timestamp}.csv";
        $content = $this->buildAuditCsvContent($logs);

        AuditLogger::logFor(request()->user(), 'export_audit_csv', 'AuditLog', null, [], [
            'count' => $logs->count(),
            'filename' => $filename,
        ]);

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename*=UTF-8''{$filename}",
        ]);
    }

    private function buildAuditCsvContent($logs): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            'Дата',
            'Пользователь',
            'Табельный',
            'Действие',
            'Сущность',
            'ID сущности',
            'IP',
            'Было',
            'Стало',
        ], ';');

        foreach ($logs as $log) {
            fputcsv($handle, [
                $log->created_at?->format('d.m.Y H:i'),
                $log->user?->full_name ?? '',
                $log->user?->employee_number ?? '',
                $this->actionLabel($log->action),
                $log->entity_type,
                $log->entity_id,
                $log->ip_address ?? '',
                $log->old_values ? json_encode($log->old_values, JSON_UNESCAPED_UNICODE) : '',
                $log->new_values ? json_encode($log->new_values, JSON_UNESCAPED_UNICODE) : '',
            ], ';');
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    private function actionLabel(string $action): string
    {
        $map = $this->actionLabelMap();

        return $map[$action] ?? $action;
    }

    private function actionLabelMap(): array
    {
        return [
            'temp_request_created' => 'Создана заявка (буфер)',
            'temp_request_reviewed' => 'Заявка рассмотрена',
            'temp_request_bulk_approved' => 'Заявка одобрена массово',
            'temp_request_cancelled' => 'Заявка отменена',
            'temp_request_finalized' => 'Заявка перенесена в финал',
            'temp_request_deleted' => 'Заявка удалена навсегда',
            'final_request_created' => 'Финальная заявка создана',
            'final_request_updated' => 'Финальная заявка изменена',
            'final_request_deleted' => 'Финальная заявка удалена',
            'invitation_created' => 'Создано приглашение',
            'invitation_used' => 'Приглашение использовано',
            'invitation_deleted' => 'Приглашение удалено',
            'user_registered' => 'Пользователь зарегистрирован',
            'user_soft_deleted' => 'Сотрудник скрыт',
            'user_force_deleted' => 'Сотрудник удалён навсегда',
            'export_csv' => 'Выгрузка заявок CSV',
            'export_audit_csv' => 'Выгрузка аудита CSV',
            'password_updated' => 'Смена пароля',
        ];
    }

    public function review(ReviewTempRequestRequest $request, TempRequest $tempRequest): RedirectResponse
    {
        $this->workflowService->reviewTempRequest(
            $tempRequest,
            $request->user(),
            $request->validated(),
        );

        return redirect()
            ->route('manager.requests.index')
            ->with('status', 'Статус заявки обновлён.');
    }

    public function finalize(FinalizeApprovedRequestsRequest $request): RedirectResponse
    {
        $requests = $this->workflowService->finalizeApprovedRequests(
            $request->user(),
            $request->validated('from'),
            $request->validated('to'),
        );

        return redirect()
            ->route('manager.requests.index')
            ->with('status', 'Перенесено заявок: '.$requests->count());
    }

    public function exportCsv(ExportRequestsCsvRequest $request): StreamedResponse
    {
        $requests = $this->workflowService->exportFinalRequests(
            $request->validated('from'),
            $request->validated('to'),
        );

        // Mark exported rows so we can safely auto-clean them later.
        try {
            $ids = $requests->pluck('id')->filter()->values()->all();
            if ($ids !== []) {
                FinalRequest::query()
                    ->whereIn('id', $ids)
                    ->update(['exported_at' => now()]);
            }
        } catch (\Throwable $exception) {
            // If DB schema is not up to date yet, still allow download.
        }

        $timestamp = now()->format('Ymd_His');
        $filename = "vtb_requests_{$timestamp}.csv";
        $content = $this->buildCsvContent($requests);

        try {
            $disk = Storage::disk('local');
            if (! $disk->exists('exports')) {
                $disk->makeDirectory('exports');
            }
            $disk->put("exports/{$filename}", $content);
            $this->pruneOldExports(14);
        } catch (\Throwable $exception) {
            // If storage is not writable inside container, still allow download.
        }

        AuditLogger::logFor($request->user(), 'export_csv', 'Request', null, [], [
            'from' => $request->validated('from'),
            'to' => $request->validated('to'),
            'count' => $requests->count(),
            'filename' => $filename,
        ]);

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename*=UTF-8''{$filename}",
        ]);
    }

    public function updateFinal(UpdateFinalRequestRequest $request, FinalRequest $finalRequest): RedirectResponse
    {
        $data = $request->validated();
        $addressChanged = $data['address_raw'] !== $finalRequest->address_raw;

        $oldValues = [
            'full_name' => $finalRequest->full_name,
            'phone' => $finalRequest->phone,
            'address_raw' => $finalRequest->address_raw,
            'date_time' => $finalRequest->date_time?->toDateTimeString(),
        ];

        $finalRequest->fill([
            'full_name' => $data['full_name'],
            'phone' => $data['phone'],
            'address_raw' => $data['address_raw'],
            'date_time' => $data['date_time'],
            'address_norm' => $addressChanged ? null : $finalRequest->address_norm,
        ])->save();

        AuditLogger::log($request->user(), 'final_request_updated', $finalRequest, $oldValues, [
            'full_name' => $finalRequest->full_name,
            'phone' => $finalRequest->phone,
            'address_raw' => $finalRequest->address_raw,
            'date_time' => $finalRequest->date_time?->toDateTimeString(),
        ]);

        $redirectUrl = route('manager.requests.index', [
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'export_page' => $request->input('export_page'),
        ]);

        return redirect()
            ->to($redirectUrl.'#export-preview')
            ->with('status', 'Финальная заявка обновлена.');
    }

    private function buildCsvContent($requests): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            'Номер',
            'Дата и время',
            'ФИО',
            'Адрес подачи',
            'Телефон',
        ], ';');

        $index = 1;
        foreach ($requests as $requestRow) {
            fputcsv($handle, [
                $index,
                $requestRow['date_time'],
                $requestRow['full_name'],
                $requestRow['address'],
                $requestRow['phone'],
            ], ';');
            $index++;
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    private function pruneOldExports(int $days): void
    {
        $disk = Storage::disk('local');
        $files = $disk->files('exports');
        $threshold = now()->subDays($days)->getTimestamp();

        foreach ($files as $file) {
            $lastModified = $disk->lastModified($file);
            if ($lastModified < $threshold) {
                $disk->delete($file);
            }
        }
    }
    public function bulkApprove(BulkApproveTempRequestsRequest $request): RedirectResponse
    {
        $approvedCount = $this->workflowService->approveTempRequests(
            $request->user(),
            $request->validated('request_ids') ?? [],
            $request->validated('approve_scope') ?? 'selected',
            $request->validated('manager_comment'),
        );

        return redirect()
            ->route('manager.requests.index')
            ->with('status', 'Массово одобрено заявок: '.$approvedCount);
    }
    public function destroyTemp(TempRequest $tempRequest): RedirectResponse
    {
        if ($this->isNotAdmin(request()->user())) {
            abort(403);
        }

        $oldValues = [
            'full_name' => $tempRequest->full_name,
            'phone' => $tempRequest->phone,
            'address_raw' => $tempRequest->address_raw,
            'date_time' => $tempRequest->date_time?->toDateTimeString(),
            'status' => $tempRequest->status->value,
        ];

        $tempRequest->forceDelete();

        AuditLogger::log(request()->user(), 'temp_request_deleted', $tempRequest, $oldValues, []);

        return redirect()
            ->route('manager.requests.index')
            ->with('status', 'Заявка удалена навсегда.');
    }

    public function destroyFinal(FinalRequest $finalRequest): RedirectResponse
    {
        if ($this->isNotAdmin(request()->user())) {
            abort(403);
        }

        $oldValues = [
            'full_name' => $finalRequest->full_name,
            'phone' => $finalRequest->phone,
            'address_raw' => $finalRequest->address_raw,
            'date_time' => $finalRequest->date_time?->toDateTimeString(),
            'status' => $finalRequest->status->value,
        ];

        $finalRequest->forceDelete();

        AuditLogger::log(request()->user(), 'final_request_deleted', $finalRequest, $oldValues, []);

        $redirectUrl = route('manager.requests.index', [
            'from' => request()->input('from'),
            'to' => request()->input('to'),
            'export_page' => request()->input('export_page'),
        ]);

        return redirect()
            ->to($redirectUrl.'#export-preview')
            ->with('status', 'Финальная запись удалена навсегда.');
    }

    public function destroyUser(User $user): RedirectResponse
    {
        if ($this->isNotManager(request()->user())) {
            abort(403);
        }

        $currentUser = request()->user();
        $allowedRoles = $this->isNotAdmin($currentUser)
            ? [UserRole::Employee]
            : [UserRole::Employee, UserRole::Manager];

        if (! in_array($user->role, $allowedRoles, true) || $user->role === UserRole::Admin) {
            abort(403);
        }

        if ($user->id === $currentUser?->id) {
            return redirect()
                ->route('manager.requests.index')
                ->with('status', 'Нельзя удалить свой аккаунт.');
        }

        $oldValues = [
            'full_name' => $user->full_name,
            'employee_number' => $user->employee_number,
            'role' => $user->role?->name,
        ];

        $user->delete();

        AuditLogger::log($currentUser, 'user_soft_deleted', $user, $oldValues, []);

        return redirect()
            ->route('manager.requests.index')
            ->with('status', $user->role === UserRole::Manager ? 'Руководитель удалён.' : 'Сотрудник удалён.');
    }

    public function destroyUserForce(User $user): RedirectResponse
    {
        if ($this->isNotAdmin(request()->user())) {
            abort(403);
        }

        if (! in_array($user->role, [UserRole::Employee, UserRole::Manager], true) || $user->role === UserRole::Admin) {
            abort(403);
        }

        if ($user->id === request()->user()?->id) {
            return redirect()
                ->route('manager.requests.index')
                ->with('status', 'Нельзя удалить свой аккаунт.');
        }

        $oldValues = [
            'full_name' => $user->full_name,
            'employee_number' => $user->employee_number,
            'role' => $user->role?->name,
        ];

        $user->forceDelete();

        AuditLogger::log(request()->user(), 'user_force_deleted', $user, $oldValues, []);

        return redirect()
            ->route('manager.requests.index')
            ->with('status', $user->role === UserRole::Manager ? 'Руководитель удалён навсегда.' : 'Сотрудник удалён навсегда.');
    }

    public function destroyInvitation(Invitation $invitation): RedirectResponse
    {
        if ($this->isNotManager(request()->user())) {
            abort(403);
        }

        $oldValues = [
            'employee_number' => $invitation->employee_number,
            'role' => $invitation->role?->name,
            'expires_at' => $invitation->expires_at?->toDateTimeString(),
            'is_used' => $invitation->is_used,
        ];

        $invitation->delete();

        AuditLogger::log(request()->user(), 'invitation_deleted', $invitation, $oldValues, []);

        return redirect()
            ->back()
            ->with('status', 'Приглашение удалено.');
    }

    private function isNotAdmin(?\App\Models\User $user): bool
    {
        return $user?->role?->name !== 'Admin';
    }

    private function isNotManager(?\App\Models\User $user): bool
    {
        return ! in_array($user?->role?->name, ['Manager', 'Admin'], true);
    }

    private function renderDashboard(?string $activeSection = null): View
    {
        $exportFrom = request()->query('from');
        $exportTo = request()->query('to');
        $exportPreview = $this->workflowService->previewFinalRequests($exportFrom, $exportTo, 5);
        $exportTotal = $exportPreview->total();

        return view('manager.dashboard-v3', [
            'dashboard' => $this->workflowService->managerDashboard(
                request()->query('buffer_status'),
                request()->query('buffer_sort'),
            ),
            'currentUser' => request()->user(),
            'invitations' => Invitation::query()
                ->latest()
                ->limit(20)
                ->get(),
            'export_preview' => $exportPreview,
            'export_total' => $exportTotal,
            'export_from' => $exportFrom,
            'export_to' => $exportTo,
        ]);
    }
}
