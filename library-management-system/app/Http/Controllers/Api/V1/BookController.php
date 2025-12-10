<?php

namespace App\Http\Controllers\Api\V1;

use BaseController;
use Books;
use Issue;
use Input;
use Validator;
use Response;
use DB;

class BookController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/books",
     *     summary="Get all books",
     *     tags={"Books"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="category", in="query", schema=@OA\Schema(type="integer")),
     *     @OA\Parameter(name="search", in="query", schema=@OA\Schema(type="string")),
     *     @OA\Parameter(name="offset", in="query", schema=@OA\Schema(type="integer")),
     *     @OA\Parameter(name="limit", in="query", schema=@OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Books retrieved successfully")
     * )
     */
    public function index()
    {
        $query = Books::with(['category', 'issues']);

        // Apply filters
        if (Input::has('category')) {
            $query->where('category_id', Input::get('category'));
        }

        if (Input::has('search')) {
            $search = Input::get('search');
            $query->where(function($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('author', 'LIKE', "%{$search}%");
            });
        }

        // Pagination
        $offset = Input::get('offset', 0);
        $limit = Input::get('limit', 30);

        $total = $query->count();
        $books = $query->skip($offset)->take($limit)->get();

        // Transform data
        $booksData = $books->map(function($book) {
            return $this->transformBook($book);
        });

        return Response::json([
            'success' => true,
            'data' => $booksData,
            'meta' => [
                'total' => $total,
                'offset' => $offset,
                'limit' => $limit
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/books/{id}",
     *     summary="Get single book",
     *     tags={"Books"},
     *     @OA\Parameter(name="id", in="path", required=true, schema=@OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Book retrieved successfully"),
     *     @OA\Response(response=404, description="Book not found")
     * )
     */
    public function show($id)
    {
        $book = Books::with(['category', 'issues', 'addedBy'])->find($id);

        if (!$book) {
            return Response::json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Book not found'
                ]
            ], 404);
        }

        return Response::json([
            'success' => true,
            'data' => $this->transformBook($book)
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/books/search",
     *     summary="Search books (public)",
     *     tags={"Books"},
     *     @OA\Parameter(name="q", in="query", required=true, schema=@OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Search results")
     * )
     */
    public function search()
    {
        $query = Input::get('q', '');

        if (empty($query)) {
            return Response::json([
                'success' => true,
                'data' => []
            ]);
        }

        $books = Books::with(['category', 'issues'])
            ->where(function($q) use ($query) {
                $q->where('title', 'LIKE', "%{$query}%")
                  ->orWhere('author', 'LIKE', "%{$query}%");
            })
            ->limit(20)
            ->get();

        $booksData = $books->map(function($book) {
            return $this->transformBook($book);
        });

        return Response::json([
            'success' => true,
            'data' => $booksData
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/books",
     *     summary="Add new book",
     *     tags={"Books"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title","author","category_id","copies"},
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="author", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="category_id", type="integer"),
     *             @OA\Property(property="copies", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Book created successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store()
    {
        $input = Input::all();

        $validator = Validator::make($input, [
            'title' => 'required|max:1000',
            'author' => 'required|max:1000',
            'description' => 'max:5000',
            'category_id' => 'required|exists:book_categories,id',
            'copies' => 'required|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return Response::json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => $validator->errors()
                ]
            ], 422);
        }

        DB::beginTransaction();

        try {
            $book = new Books;
            $book->title = $input['title'];
            $book->author = $input['author'];
            $book->description = isset($input['description']) ? $input['description'] : '';
            $book->category_id = $input['category_id'];
            $book->added_by = auth()->id();
            $book->save();

            // Create issues (copies)
            for ($i = 0; $i < $input['copies']; $i++) {
                $issue = new Issue;
                $issue->book_id = $book->book_id;
                $issue->available_status = 1;
                $issue->added_by = auth()->id();
                $issue->save();
            }

            DB::commit();

            $book->load(['category', 'issues']);

            return Response::json([
                'success' => true,
                'data' => $this->transformBook($book),
                'message' => 'Book added successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();

            return Response::json([
                'success' => false,
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => 'Failed to add book'
                ]
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/v1/books/{id}",
     *     summary="Update book",
     *     tags={"Books"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, schema=@OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="author", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="category_id", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Book updated successfully"),
     *     @OA\Response(response=404, description="Book not found")
     * )
     */
    public function update($id)
    {
        $book = Books::find($id);

        if (!$book) {
            return Response::json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Book not found'
                ]
            ], 404);
        }

        $input = Input::all();

        $validator = Validator::make($input, [
            'title' => 'max:1000',
            'author' => 'max:1000',
            'description' => 'max:5000',
            'category_id' => 'exists:book_categories,id'
        ]);

        if ($validator->fails()) {
            return Response::json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => $validator->errors()
                ]
            ], 422);
        }

        if (isset($input['title'])) $book->title = $input['title'];
        if (isset($input['author'])) $book->author = $input['author'];
        if (isset($input['description'])) $book->description = $input['description'];
        if (isset($input['category_id'])) $book->category_id = $input['category_id'];

        $book->save();
        $book->load(['category', 'issues']);

        return Response::json([
            'success' => true,
            'data' => $this->transformBook($book),
            'message' => 'Book updated successfully'
        ]);
    }

    /**
     * Transform book data
     */
    protected function transformBook($book)
    {
        return [
            'id' => $book->book_id,
            'title' => $book->title,
            'author' => $book->author,
            'description' => $book->description,
            'category' => $book->category ? [
                'id' => $book->category->id,
                'name' => $book->category->category
            ] : null,
            'available_copies' => $book->availableCopies(),
            'total_copies' => $book->totalCopies(),
            'is_available' => $book->isAvailable(),
            'added_at' => $book->added_at_timestamp
        ];
    }
}
