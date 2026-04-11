<?php

namespace App\Http\Controllers;

use App\Http\Requests\FinalizeApprovedRequestsRequest;
use App\Http\Requests\ReviewTempRequestRequest;
use App\Models\TempRequest;
use App\Services\RequestWorkflowService;
use Illuminate\Http\JsonResponse;

class ManagerRequestController extends Controller
{
    public function __construct(
        private readonly RequestWorkflowService $workflowService,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json(
            $this->workflowService->managerDashboard()
        );
    }

    public function review(ReviewTempRequestRequest $request, TempRequest $tempRequest): JsonResponse
    {
        $tempRequest = $this->workflowService->reviewTempRequest(
            $tempRequest,
            $request->user(),
            $request->validated(),
        );

        return response()->json($tempRequest);
    }

    public function finalize(FinalizeApprovedRequestsRequest $request): JsonResponse
    {
        $requests = $this->workflowService->finalizeApprovedRequests(
            $request->user(),
            $request->validated('from'),
            $request->validated('to'),
        );

        return response()->json([
            'count' => $requests->count(),
            'requests' => $requests,
        ]);
    }
}
