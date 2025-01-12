<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Ressource;
use App\Models\User;
use App\Traits\FieldMappingTrait;
use App\Utils\Utils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CategoryController extends Controller {

    use FieldMappingTrait;

    /**
     * @OA\Get(
     *     path="/allCategories",
     *     tags={"Categories"},
     *     summary="Fetch all categories",
     *     description="Fetches all categories including inactive ones. This endpoint is restricted to admin users.",
     *     operationId="getAllCategories",
     *     security={{ "BearerAuth": {} }},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="categories",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/CategoryDetail")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Admin access required"
     *     )
     * )
     */
    public function getAllCategories() {
        $categories = Category::all();
        $categoriesTransformed = $categories->map(function ($category) {
            return Utils::getCategoryDetail($category, true);
        });

        return response()->json(['categories' => $categoriesTransformed]);
    }

    /**
     * @OA\Schema(
     *       schema="Category",
     *       type="object",
     *       description="Detailed information about a category",
     *       @OA\Property(property="id", type="integer", description="Category ID"),
     *       @OA\Property(property="title", type="string", description="Title of the category"),
     *       @OA\Property(property="description", type="string", description="Description of the category"),
     *       @OA\Property(property="icon", type="string", description="Icon representing the category"),
     *       @OA\Property(property="color", type="string", description="Color associated with the category. Hexadecimal format with '#': #000000"),
     *       @OA\Property(property="createdAt", type="string", format="date-time", description="Creation date of the category"),
     *       @OA\Property(property="updatedAt", type="string", format="date-time", description="Last update date of the category"),
     *   )
     * /
     * @OA\Get(
     *     path="/categories",
     *     tags={"Categories"},
     *     summary="Fetch active categories",
     *     description="Fetches only active categories. This endpoint is available to all users.",
     *     operationId="getActiveCategories",
     *     security={{ "BearerAuth": {} }},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="categories",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Category")
     *             )
     *         )
     *     )
     * )
     */
    public function getActiveCategories() {
        // throw new Exception('My first Sentry error!');
        $activeCategories = Category::where('is_active', 1)->get();
        $activeCategoriesTransformed = $activeCategories->map(function ($category) {
            return Utils::getCategoryDetail($category, false);
        });

        return response()->json(['categories' => $activeCategoriesTransformed]);
    }

    /**
     * @OA\Get(
     *     path="/category/{id}",
     *     tags={"Categories"},
     *     summary="Fetch a category",
     *     description="Fetches a category by its ID. This endpoint is available to all users.",
     *     operationId="getCategory",
     *     security={{ "BearerAuth": {} }},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the category to fetch",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="category",
     *                 ref="#/components/schemas/CategoryDetailWithRessources"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Category not found"
     *     )
     * )
     */
    public function getCategory($id) {
        $category = Category::find($id);

        // Exist and is active
        if (!$category || !$category->is_active) {
            return response()->json(['message' => 'Catégorie non trouvée'], 404);
        }

        return response()->json(['category' => Utils::getCategoryDetailWithRessources($category)]);
    }

    /**
     * @OA\Post(
     *     path="/category/create",
     *     tags={"Categories"},
     *     summary="Create a category",
     *     description="Creates a new category. This endpoint is restricted to admin users.",
     *     operationId="createCategory",
     *     security={{ "BearerAuth": {} }},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title", "description", "icon", "color"},
     *             @OA\Property(property="title", type="string", description="Title of the category"),
     *             @OA\Property(property="description", type="string", description="Description of the category"),
     *             @OA\Property(property="icon", type="string", description="Icon representing the category"),
     *             @OA\Property(property="color", type="string", description="Color associated with the category. Hexadecimal format with or without '#': #000000"),
     *             @OA\Property(property="isActive", type="boolean", description="Whether the category is active. Visible to admins only.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Category created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="category",
     *                 ref="#/components/schemas/CategoryDetail"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 additionalProperties={"type": "array", "items": {"string"}}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Admin access required"
     *    )
     * )
     */
    public function createCategory(Request $request) {
        // Convert isActive to boolean
        if ($request->has('isActive')) $request['isActive'] = filter_var($request['isActive'], FILTER_VALIDATE_BOOLEAN);

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|unique:categories',
            'description' => 'required|string',
            'icon' => 'required|string',
            'color' => ['required', 'string', 'regex:/^#?([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/'],
            'isActive' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Champ(s) incorrect(s)', 'errors' => $validator->errors()], 422);
        }


        if (!isset($request['isActive'])) $request['isActive'] = true;
        if (!str_starts_with($request['color'], '#')) $request['color'] = '#' . $request['color'];

        $category = new Category();
        $category->title = $request['title'];
        $category->description = $request['description'];
        $category->icon = $request['icon'];
        $category->color = $request['color'];
        $category->is_active = $request['isActive'];
        $category->created_by = auth()->user()->id_user;
        $category->save();

        return response()->json(['category' => Utils::getCategoryDetail($category)], 201);
    }

    /**
     * @OA\Post(
     *     path="/category/edit/{id}",
     *     tags={"Categories"},
     *     summary="Edit a category",
     *     description="Edit an existing category. This endpoint is restricted to admin users.",
     *     operationId="editCategory",
     *     security={{ "BearerAuth": {} }},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the category to edit",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", description="Title of the category"),
     *             @OA\Property(property="description", type="string", description="Description of the category"),
     *             @OA\Property(property="icon", type="string", description="Icon representing the category"),
     *             @OA\Property(property="color", type="string", description="Color associated with the category. Hexadecimal format with or without '#': #000000"),
     *             @OA\Property(property="isActive", type="boolean", description="Whether the category is active. Visible to admins only.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category edited successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="category",
     *                 ref="#/components/schemas/CategoryDetail"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 additionalProperties={"type": "array", "items": {"string"}}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Category not found"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Admin access required"
     *     )
     * )
     */
    public function editCategory($id, Request $request) {
        $category = Category::find($id);
        if (!$category) {
            return response()->json(['message' => 'Catégorie non trouvée'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => [
                'string',
                Rule::unique('categories')->ignore($category->id_category, 'id_category')
            ],
            'description' => 'string',
            'icon' => 'string',
            'color' => ['string', 'regex:/^#?([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/'],
            'isActive' => 'boolean'
        ]);


        if ($validator->fails()) {
            return response()->json(['message' => 'Champ(s) incorrect(s)', 'errors' => $validator->errors()], 422);
        }

        if ($request->has('color') && !str_starts_with($request['color'], '#')) $request['color'] = '#' . $request['color'];

        $category->title = $request->input('title', $category->title);
        $category->description = $request->input('description', $category->description);
        $category->icon = $request->input('icon', $category->icon);
        $category->color = $request->input('color', $category->color);
        $category->is_active = $request->input('isActive', $category->is_active);
        $category->save();

        return response()->json(['category' => Utils::getCategoryDetail($category,true)], 200);
    }

    /**
     * @OA\Delete(
     *     path="/category/delete/{id}",
     *     tags={"Categories"},
     *     summary="Delete a category",
     *     description="Deletes a category by its ID. This endpoint is restricted to admin users.",
     *     operationId="deleteCategory",
     *     security={{ "BearerAuth": {} }},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the category to delete",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Category not found"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error while deleting the category"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Admin access required"
     *     )
     * )
     */
    public function deleteCategory($id) {
        try {
            $category = Category::find($id);
            if (!$category) {
                return response()->json(['message' => 'Catégorie non trouvée'], 404);
            }

            $category->delete();
            return response()->json(['message' => 'Catégorie supprimée'], 200);
        }catch (\Exception $e){
            return response()->json(['message' => 'Erreur lors de la suppression de la catégorie'], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/stats/categories",
     *     tags={"Statistics"},
     *     summary="Retrieve category statistics",
     *     description="Fetches statistics for categories, including the count of resources in each category. Accessible only to admins.",
     *     operationId="getCategoriesStatsCount",
     *     security={{ "BearerAuth": {} }},
     *     @OA\Response(
     *         response=200,
     *         description="Category statistics",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="categories",
     *                 type="array",
     *                 description="An array of categories with their resource counts and details",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", description="The unique identifier of the category"),
     *                     @OA\Property(property="title", type="string", description="The title of the category"),
     *                     @OA\Property(property="color", type="string", description="The color associated with the category"),
     *                     @OA\Property(property="icon", type="string", description="The icon representing the category"),
     *                     @OA\Property(property="ressourcesCount", type="integer", description="Number of resources in this category"),
     *                     @OA\Property(property="isActive", type="boolean", description="Whether the category is active")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Access restricted to admins",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized - Access restricted to admins")
     *         )
     *     )
     * )
     */
    public function getCategoriesStatsCount()
    {
        $categories = Category::select('id_category', 'title', 'color', 'icon', 'is_active')->get();
        $categories = $categories->map(function ($category) {
            $ressourceCount = Ressource::where('id_category', $category->id_category)->count();
            return [
                'id' => $category->id_category,
                'title' => $category->title,
                'color' => $category->color,
                'icon' => $category->icon,
                'ressourcesCount' => $ressourceCount,
                'isActive' => $category->is_active
            ];
        });
        return response()->json(['categories' => $categories]);
    }

}
