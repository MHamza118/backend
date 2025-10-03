<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateTimeOffRequest;
use App\Http\Requests\UpdateTimeOffStatusRequest;
use App\Http\Resources\TimeOffRequestResource;
use App\Models\Employee;
use App\Models\TimeOffRequest;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TimeOffController extends Controller
{
    use ApiResponseTrait;

    // Employee: list own time-off requests
    public function employeeIndex(Request $request): JsonResponse
    {
        try {
            $employee = $request->user();

            $query = TimeOffRequest::query()
                ->byEmployee($employee->id);

            if ($request->filled('status') && $request->status !== 'all') {
                $query->byStatus($request->status);
            }

            if ($request->filled('start_date') || $request->filled('end_date')) {
                $query->betweenDates($request->start_date, $request->end_date);
            }

            $query->orderByDesc('created_at');

            $requests = $query->get();

            return $this->successResponse([
                'requests' => TimeOffRequestResource::collection($requests)
            ], 'Time-off requests retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve time-off requests: ' . $e->getMessage());
        }
    }

    // Employee: submit a new request
    public function store(CreateTimeOffRequest $request): JsonResponse
    {
        try {
            $employee = $request->user();

            DB::beginTransaction();

            $timeOff = TimeOffRequest::create([
                'employee_id' => $employee->id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'type' => $request->type,
                'reason' => $request->reason,
                'notes' => $request->notes,
                'status' => 'pending',
                'submitted_at' => now(),
            ]);

            DB::commit();

            return $this->successResponse(new TimeOffRequestResource($timeOff), 'Time-off request submitted', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to submit time-off request: ' . $e->getMessage());
        }
    }

    // Employee: view one request
    public function show(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();
            $isEmployee = $user instanceof Employee;

            if ($isEmployee) {
                $record = TimeOffRequest::byEmployee($user->id)->findOrFail($id);
            } else {
                $record = TimeOffRequest::with('employee')->findOrFail($id);
            }

            return $this->successResponse(new TimeOffRequestResource($record), 'Time-off request retrieved');
        } catch (\Exception $e) {
            return $this->notFoundResponse('Time-off request not found');
        }
    }

    // Employee: cancel a pending request
    public function cancel(Request $request, $id): JsonResponse
    {
        try {
            $employee = $request->user();
            $record = TimeOffRequest::byEmployee($employee->id)->findOrFail($id);

            if ($record->status !== 'pending') {
                return $this->errorResponse('Only pending requests can be cancelled', 422);
            }

            $record->update([
                'status' => 'cancelled',
                'decided_at' => now(),
                'approved_by' => null,
                'decision_notes' => 'Cancelled by employee',
            ]);

            return $this->successResponse(new TimeOffRequestResource($record), 'Time-off request cancelled');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to cancel time-off request: ' . $e->getMessage());
        }
    }

    // Admin: list requests with filters
    public function adminIndex(Request $request): JsonResponse
    {
        try {
            $query = TimeOffRequest::with('employee');

            if ($request->filled('status') && $request->status !== 'all') {
                $query->byStatus($request->status);
            }

            if ($request->filled('type') && $request->type !== 'all') {
                $query->where('type', $request->type);
            }

            if ($request->filled('start_date') || $request->filled('end_date')) {
                $query->betweenDates($request->start_date, $request->end_date);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('reason', 'LIKE', "%{$search}%")
                      ->orWhereHas('employee', function($eq) use ($search) {
                          $eq->where('first_name', 'LIKE', "%{$search}%")
                             ->orWhere('last_name', 'LIKE', "%{$search}%")
                             ->orWhere('email', 'LIKE', "%{$search}%");
                      });
                });
            }

            $query->orderByDesc('created_at');

            $perPage = (int) $request->get('per_page', 50);
            $paginated = $query->paginate($perPage);

            return $this->successResponse([
                'requests' => TimeOffRequestResource::collection($paginated->items()),
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'from' => $paginated->firstItem(),
                    'to' => $paginated->lastItem(),
                ]
            ], 'Time-off requests retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve time-off requests: ' . $e->getMessage());
        }
    }

    // Admin: update status (approve/reject/cancel)
    public function updateStatus(UpdateTimeOffStatusRequest $request, $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $record = TimeOffRequest::findOrFail($id);
            $record->update([
                'status' => $request->status,
                'approved_by' => $request->user()->id,
                'decision_notes' => $request->decision_notes,
                'decided_at' => now(),
            ]);

            $record->load('employee');

            DB::commit();

            return $this->successResponse(new TimeOffRequestResource($record), 'Time-off status updated');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to update status: ' . $e->getMessage());
        }
    }
}
