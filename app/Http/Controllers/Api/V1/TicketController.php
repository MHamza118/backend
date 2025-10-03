<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Requests\CreateTicketResponseRequest;
use App\Http\Resources\TicketResource;
use App\Http\Resources\TicketResponseResource;
use App\Models\Ticket;
use App\Models\TicketResponse;
use App\Models\Employee;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get all tickets (Admin view)
     * Supports filtering by status, category, priority, search, and archived status
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $query = Ticket::with(['employee', 'responses']);

            // Apply filters
            if ($request->has('status') && $request->status !== 'all') {
                $query->byStatus($request->status);
            }

            if ($request->has('category') && $request->category !== 'all') {
                $query->byCategory($request->category);
            }

            if ($request->has('priority') && $request->priority !== 'all') {
                $query->byPriority($request->priority);
            }

            if ($request->has('archived') && $request->archived === 'true') {
                $query->archived();
            } else {
                $query->active();
            }

            if ($request->has('search') && !empty($request->search)) {
                $query->search($request->search);
            }

            // Sort by priority (urgent first) then by created date (newest first)
            $query->orderByRaw("
                CASE priority 
                    WHEN 'urgent' THEN 4
                    WHEN 'high' THEN 3  
                    WHEN 'medium' THEN 2
                    WHEN 'low' THEN 1
                    ELSE 2
                END DESC, created_at DESC
            ");

            $tickets = $query->paginate($request->get('per_page', 50));

            return $this->successResponse([
                'tickets' => TicketResource::collection($tickets->items()),
                'pagination' => [
                    'current_page' => $tickets->currentPage(),
                    'last_page' => $tickets->lastPage(),
                    'per_page' => $tickets->perPage(),
                    'total' => $tickets->total(),
                    'from' => $tickets->firstItem(),
                    'to' => $tickets->lastItem(),
                ]
            ], 'Tickets retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve tickets: ' . $e->getMessage());
        }
    }

    /**
     * Get employee's own tickets
     */
    public function employeeTickets(Request $request): JsonResponse
    {
        try {
            $employee = $request->user();
            
            $query = Ticket::with(['publicResponses'])
                ->byEmployee($employee->id);

            // Apply filters
            if ($request->has('status') && $request->status !== 'all') {
                $query->byStatus($request->status);
            }

            if ($request->has('search') && !empty($request->search)) {
                $query->where(function($q) use ($request) {
                    $q->where('title', 'LIKE', "%{$request->search}%")
                      ->orWhere('description', 'LIKE', "%{$request->search}%");
                });
            }

            // Only show active tickets (non-archived)
            $query->active();

            // Sort by priority (urgent first) then by created date (newest first)
            $query->orderByRaw("
                CASE priority 
                    WHEN 'urgent' THEN 4
                    WHEN 'high' THEN 3  
                    WHEN 'medium' THEN 2
                    WHEN 'low' THEN 1
                    ELSE 2
                END DESC, created_at DESC
            ");

            $tickets = $query->get();

            return $this->successResponse([
                'tickets' => TicketResource::collection($tickets)
            ], 'Employee tickets retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve tickets: ' . $e->getMessage());
        }
    }

    /**
     * Create a new ticket (Employee only)
     */
    public function store(CreateTicketRequest $request): JsonResponse
    {
        try {
            $employee = $request->user();

            DB::beginTransaction();

            $ticket = Ticket::create([
                'employee_id' => $employee->id,
                'title' => $request->title,
                'description' => $request->description,
                'category' => $request->category,
                'priority' => $request->priority,
                'location' => $request->location,
                'status' => 'open'
            ]);

            $ticket->load(['employee']);

            DB::commit();

            return $this->successResponse(
                new TicketResource($ticket),
                'Ticket created successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to create ticket: ' . $e->getMessage());
        }
    }

    /**
     * Get a specific ticket
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();
            $isEmployee = $user instanceof Employee;

            if ($isEmployee) {
                // Employee can only see their own tickets with public responses
                $ticket = Ticket::with(['employee', 'publicResponses'])
                    ->byEmployee($user->id)
                    ->findOrFail($id);
            } else {
                // Admin can see all tickets with all responses
                $ticket = Ticket::with(['employee', 'responses'])
                    ->findOrFail($id);
            }

            return $this->successResponse(
                new TicketResource($ticket),
                'Ticket retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->notFoundResponse('Ticket not found');
        }
    }

    /**
     * Update a ticket (Admin only)
     */
    public function update(UpdateTicketRequest $request, $id): JsonResponse
    {
        try {
            $ticket = Ticket::findOrFail($id);

            DB::beginTransaction();

            $ticket->update($request->validated());
            $ticket->load(['employee', 'responses']);

            DB::commit();

            return $this->successResponse(
                new TicketResource($ticket),
                'Ticket updated successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return $this->notFoundResponse('Ticket not found');
            }
            return $this->errorResponse('Failed to update ticket: ' . $e->getMessage());
        }
    }

    /**
     * Archive a ticket (Admin only)
     */
    public function archive(Request $request, $id): JsonResponse
    {
        try {
            $ticket = Ticket::findOrFail($id);

            DB::beginTransaction();

            $ticket->archive();
            $ticket->load(['employee', 'responses']);

            DB::commit();

            return $this->successResponse(
                new TicketResource($ticket),
                'Ticket archived successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return $this->notFoundResponse('Ticket not found');
            }
            return $this->errorResponse('Failed to archive ticket: ' . $e->getMessage());
        }
    }

    /**
     * Delete a ticket (Admin only)
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        try {
            $ticket = Ticket::findOrFail($id);

            DB::beginTransaction();

            $ticket->delete();

            DB::commit();

            return $this->successResponse(
                null,
                'Ticket deleted successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return $this->notFoundResponse('Ticket not found');
            }
            return $this->errorResponse('Failed to delete ticket: ' . $e->getMessage());
        }
    }

    /**
     * Add a response to a ticket (Admin only)
     */
    public function addResponse(CreateTicketResponseRequest $request, $id): JsonResponse
    {
        try {
            $ticket = Ticket::findOrFail($id);

            DB::beginTransaction();

            $response = TicketResponse::create([
                'ticket_id' => $ticket->id,
                'message' => $request->message,
                'responded_by' => 'Admin', // Could be dynamic based on admin user
                'internal' => $request->get('internal', false)
            ]);

            // Update ticket status if requested
            if ($request->get('update_status', false) && $request->new_status) {
                $ticket->updateStatus($request->new_status);
            }

            $response->load(['ticket']);

            DB::commit();

            return $this->successResponse(
                new TicketResponseResource($response),
                'Response added successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return $this->notFoundResponse('Ticket not found');
            }
            return $this->errorResponse('Failed to add response: ' . $e->getMessage());
        }
    }

    /**
     * Get ticket statistics (Admin only)
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $stats = [
                'total_tickets' => Ticket::active()->count(),
                'by_status' => [
                    'open' => Ticket::active()->byStatus('open')->count(),
                    'in_progress' => Ticket::active()->byStatus('in-progress')->count(),
                    'resolved' => Ticket::active()->byStatus('resolved')->count(),
                    'closed' => Ticket::active()->byStatus('closed')->count(),
                ],
                'by_priority' => [
                    'urgent' => Ticket::active()->byPriority('urgent')->count(),
                    'high' => Ticket::active()->byPriority('high')->count(),
                    'medium' => Ticket::active()->byPriority('medium')->count(),
                    'low' => Ticket::active()->byPriority('low')->count(),
                ],
                'by_category' => [
                    'broken_equipment' => Ticket::active()->byCategory('broken-equipment')->count(),
                    'software_issue' => Ticket::active()->byCategory('software-issue')->count(),
                    'pos_problem' => Ticket::active()->byCategory('pos-problem')->count(),
                    'kitchen_equipment' => Ticket::active()->byCategory('kitchen-equipment')->count(),
                    'facility_issue' => Ticket::active()->byCategory('facility-issue')->count(),
                    'other' => Ticket::active()->byCategory('other')->count(),
                ],
                'archived_tickets' => Ticket::archived()->count(),
                'avg_response_time' => '2.3 hours', // This could be calculated from actual data
            ];

            return $this->successResponse($stats, 'Ticket statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve statistics: ' . $e->getMessage());
        }
    }

    /**
     * Get ticket configuration data (categories, priorities, statuses)
     */
    public function configuration(Request $request): JsonResponse
    {
        try {
            $config = [
                'categories' => collect(Ticket::CATEGORIES)->map(function ($label, $value) {
                    return [
                        'id' => $value,
                        'label' => $label,
                        'value' => $value
                    ];
                })->values(),
                'priorities' => collect(Ticket::PRIORITIES)->map(function ($label, $value) {
                    return [
                        'id' => $value,
                        'label' => $label,
                        'value' => $value
                    ];
                })->values(),
                'statuses' => collect(Ticket::STATUSES)->map(function ($label, $value) {
                    return [
                        'id' => $value,
                        'label' => $label,
                        'value' => $value
                    ];
                })->values(),
            ];

            return $this->successResponse($config, 'Ticket configuration retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve configuration: ' . $e->getMessage());
        }
    }
}
