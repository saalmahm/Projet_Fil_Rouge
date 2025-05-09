<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
    /**
     * Get all categories
     */
    public function index()
    {
        $categories = Category::withCount('items')->get();
        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * Create a new category (Admin-only)
     */
    public function store(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories',
            'description' => 'nullable|string',
            'icon' => 'nullable|image|max:2048',
        ]);

        // Handle image upload if present
        if ($request->hasFile('icon')) {
            $iconPath = $request->file('icon')->store('categories', 'public');
            $validated['icon'] = $iconPath;
        }

        $category = Category::create($validated);

        return response()->json([
            'success' => true,
            'data' => $category
        ], 201);
    }

    /**
     * Get a single category with its items
     */
    public function show(Category $category)
    {
        return response()->json([
            'success' => true,
            'data' => $category->load('items.seller')
        ]);
    }

    /**
     * Update a category (Admin-only)
     */
    public function update(Request $request, Category $category)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:categories,name,' . $category->id,
            'description' => 'nullable|string',
            'icon' => 'nullable|image|max:2048'
        ]);

        if ($request->hasFile('icon')) {
            // Delete old image if it exists
            if ($category->icon) {
                Storage::disk('public')->delete($category->icon);
            }
            
            // Store new image
            $iconPath = $request->file('icon')->store('categories', 'public');
            $validated['icon'] = $iconPath;
        }

        $category->update($validated);

        return response()->json([
            'success' => true,
            'data' => $category
        ]);
    }

    /**
     * Delete a category (Admin-only)
     */
    public function destroy(Category $category)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Delete image if it exists
        if ($category->icon) {
            Storage::disk('public')->delete($category->icon);
        }

        $uncategorized = Category::firstOrCreate(
            ['name' => 'Uncategorized'],
            [
                'description' => 'Items without a category'
            ]
        );

        $category->items()->update(['category_id' => $uncategorized->id]);

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted. Items moved to Uncategorized.'
        ]);
    }
}