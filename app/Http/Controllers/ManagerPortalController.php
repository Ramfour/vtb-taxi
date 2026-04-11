<?php

namespace App\Http\Controllers;

use App\Http\Requests\FinalizeApprovedRequestsRequest;
use App\Http\Requests\ReviewTempRequestRequest;
use App\Models\TempRequest;
use App\Services\RequestWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ManagerPortalController extends Controller
{
    public function __construct(
        private readonly RequestWorkflowService $workflowService,
    ) {
    }

    public function index(): View
    {
        return view('manager.dashboard', [
            'dashboard' => $this->workflowService->managerDashboard(),
            'currentUser' => request()->user(),
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
            ->route('manager.requests.index', ['user_id' => $request->user()->id])
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
            ->route('manager.requests.index', ['user_id' => $request->user()->id])
            ->with('status', 'Перенесено заявок: '.$requests->count());
    }
}
