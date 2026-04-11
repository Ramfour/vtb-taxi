<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelTempRequestRequest;
use App\Http\Requests\StoreTempRequestRequest;
use App\Models\TempRequest;
use App\Services\RequestWorkflowService;
use Illuminate\Http\JsonResponse;

class EmployeeRequestController extends Controller
{
    public function __construct(
        private readonly RequestWorkflowService $workflowService,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json(
            $this->workflowService->employeeDashboard(request()->user())
        );
    }

    public function store(StoreTempRequestRequest $request): JsonResponse
    {
        $tempRequest = $this->workflowService->createTempRequest(
            $request->user(),
            $request->validated(),
        );

        return response()->json($tempRequest, 201);
    }

    public function cancel(CancelTempRequestRequest $request, TempRequest $tempRequest): JsonResponse
    {
        $tempRequest = $this->workflowService->cancelTempRequest(
            $tempRequest,
            $request->user(),
            $request->validated('reason'),
        );

        return response()->json($tempRequest);
    }
}
