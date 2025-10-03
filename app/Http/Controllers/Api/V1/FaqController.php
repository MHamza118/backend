<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class FaqController extends Controller
{
    /**
     * Get all FAQs for employees (active only)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = Faq::active()->ordered();

        // Filter by category if provided
        if ($request->has('category') && $request->category !== 'all') {
            $query->byCategory($request->category);
        }

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('question', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('answer', 'LIKE', "%{$searchTerm}%");
            });
        }

        $faqs = $query->get();

        return response()->json([
            'success' => true,
            'data' => $faqs,
            'categories' => Faq::getCategories()
        ]);
    }

    /**
     * Get all FAQs for admin (including inactive)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function adminIndex(Request $request)
    {
        $query = Faq::ordered();

        // Filter by category if provided
        if ($request->has('category') && $request->category !== 'all') {
            $query->byCategory($request->category);
        }

        // Filter by status if provided
        if ($request->has('active') && $request->active !== 'all') {
            $query->where('active', $request->active === 'true');
        }

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('question', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('answer', 'LIKE', "%{$searchTerm}%");
            });
        }

        $faqs = $query->with(['creator:id,first_name,last_name', 'updater:id,first_name,last_name'])->get();

        // Get statistics
        $stats = [
            'total' => Faq::count(),
            'active' => Faq::active()->count(),
            'inactive' => Faq::where('active', false)->count(),
            'categories' => Faq::select('category')
                ->groupBy('category')
                ->get()
                ->count()
        ];

        return response()->json([
            'success' => true,
            'data' => $faqs,
            'stats' => $stats,
            'categories' => Faq::getCategories()
        ]);
    }

    /**
     * Store a new FAQ
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'question' => 'required|string|max:1000',
            'answer' => 'required|string|max:5000',
            'category' => [
                'required',
                'string',
                Rule::in(array_keys(Faq::getCategories()))
            ],
            'order' => 'sometimes|integer|min:0',
            'active' => 'sometimes|boolean'
        ]);

        $faq = new Faq($request->only(['question', 'answer', 'category', 'order', 'active']));
        $faq->created_by = Auth::id();
        $faq->updated_by = Auth::id();
        $faq->save();

        return response()->json([
            'success' => true,
            'message' => 'FAQ created successfully',
            'data' => $faq->load(['creator:id,first_name,last_name', 'updater:id,first_name,last_name'])
        ], 201);
    }

    /**
     * Show a specific FAQ
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $faq = Faq::with(['creator:id,first_name,last_name', 'updater:id,first_name,last_name'])->find($id);

        if (!$faq) {
            return response()->json([
                'success' => false,
                'message' => 'FAQ not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $faq
        ]);
    }

    /**
     * Update a FAQ
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $faq = Faq::find($id);

        if (!$faq) {
            return response()->json([
                'success' => false,
                'message' => 'FAQ not found'
            ], 404);
        }

        $request->validate([
            'question' => 'sometimes|required|string|max:1000',
            'answer' => 'sometimes|required|string|max:5000',
            'category' => [
                'sometimes',
                'required',
                'string',
                Rule::in(array_keys(Faq::getCategories()))
            ],
            'order' => 'sometimes|integer|min:0',
            'active' => 'sometimes|boolean'
        ]);

        $faq->fill($request->only(['question', 'answer', 'category', 'order', 'active']));
        $faq->updated_by = Auth::id();
        $faq->save();

        return response()->json([
            'success' => true,
            'message' => 'FAQ updated successfully',
            'data' => $faq->load(['creator:id,first_name,last_name', 'updater:id,first_name,last_name'])
        ]);
    }

    /**
     * Delete a FAQ
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $faq = Faq::find($id);

        if (!$faq) {
            return response()->json([
                'success' => false,
                'message' => 'FAQ not found'
            ], 404);
        }

        $faq->delete();

        return response()->json([
            'success' => true,
            'message' => 'FAQ deleted successfully'
        ]);
    }

    /**
     * Toggle FAQ active status
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleActive($id)
    {
        $faq = Faq::find($id);

        if (!$faq) {
            return response()->json([
                'success' => false,
                'message' => 'FAQ not found'
            ], 404);
        }

        $faq->active = !$faq->active;
        $faq->updated_by = Auth::id();
        $faq->save();

        return response()->json([
            'success' => true,
            'message' => 'FAQ status updated successfully',
            'data' => $faq->load(['creator:id,first_name,last_name', 'updater:id,first_name,last_name'])
        ]);
    }

    /**
     * Get FAQ categories
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function categories()
    {
        return response()->json([
            'success' => true,
            'data' => Faq::getCategories()
        ]);
    }

    /**
     * Bulk update FAQ order
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateOrder(Request $request)
    {
        $request->validate([
            'faqs' => 'required|array',
            'faqs.*.id' => 'required|exists:faqs,id',
            'faqs.*.order' => 'required|integer|min:0'
        ]);

        foreach ($request->faqs as $faqData) {
            Faq::where('id', $faqData['id'])->update([
                'order' => $faqData['order'],
                'updated_by' => Auth::id(),
                'updated_at' => now()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'FAQ order updated successfully'
        ]);
    }
}
