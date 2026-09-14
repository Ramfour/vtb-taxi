<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelTempRequestRequest;
use App\Http\Requests\StoreTempRequestRequest;
use App\Models\TempRequest;
use App\Services\RequestWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EmployeePortalController extends Controller
{
    public function __construct(
        private readonly RequestWorkflowService $workflowService,
    ) {
    }

    public function index(): View
    {
        $currentUser = request()->user()->load('latestAddress', 'commuteSchedule');

        return view('employee.dashboard-v2', [
            'dashboard' => $this->workflowService->employeeDashboard($currentUser),
            'currentUser' => $currentUser,
        ]);
    }

    public function store(StoreTempRequestRequest $request): RedirectResponse
    {
        $this->workflowService->createTempRequest(
            $request->user(),
            $request->validated(),
        );

        return redirect()
            ->route('employee.requests.index')
            ->with('status', 'Заявка отправлена в буфер на согласование.');
    }

    public function cancel(CancelTempRequestRequest $request, TempRequest $tempRequest): RedirectResponse
    {
        $this->workflowService->cancelTempRequest(
            $tempRequest,
            $request->user(),
            $request->validated('reason'),
        );

        return redirect()
            ->route('employee.requests.index')
            ->with('status', 'Заявка отменена.');
    }
}
