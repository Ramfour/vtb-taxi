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
use App\Services\RequestWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
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
        $exportFrom = request()->query('from');
        $exportTo = request()->query('to');
        $exportPreview = $this->workflowService->exportFinalRequests($exportFrom, $exportTo, 50);
        $exportTotal = $this->workflowService->countFinalRequests($exportFrom, $exportTo);

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

        $finalRequest->fill([
            'full_name' => $data['full_name'],
            'phone' => $data['phone'],
            'address_raw' => $data['address_raw'],
            'date_time' => $data['date_time'],
            'address_norm' => $addressChanged ? null : $finalRequest->address_norm,
        ])->save();

        return redirect()
            ->route('manager.requests.index', [
                'from' => request()->query('from'),
                'to' => request()->query('to'),
            ])
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

        $tempRequest->forceDelete();

        return redirect()
            ->route('manager.requests.index')
            ->with('status', 'Заявка удалена навсегда.');
    }

    public function destroyFinal(FinalRequest $finalRequest): RedirectResponse
    {
        if ($this->isNotAdmin(request()->user())) {
            abort(403);
        }

        $finalRequest->forceDelete();

        return redirect()
            ->route('manager.requests.index')
            ->with('status', 'Финальная запись удалена навсегда.');
    }

    private function isNotAdmin(?\App\Models\User $user): bool
    {
        return $user?->role?->name !== 'Admin';
    }
}
