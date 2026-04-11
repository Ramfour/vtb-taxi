<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkApproveTempRequestsRequest;
use App\Http\Requests\FinalizeApprovedRequestsRequest;
use App\Http\Requests\ReviewTempRequestRequest;
use App\Models\Invitation;
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
        return view('manager.dashboard-v3', [
            'dashboard' => $this->workflowService->managerDashboard(),
            'currentUser' => request()->user(),
            'invitations' => Invitation::query()
                ->latest()
                ->limit(20)
                ->get(),
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
}
