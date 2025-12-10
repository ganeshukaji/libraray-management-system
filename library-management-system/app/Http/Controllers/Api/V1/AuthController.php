<?php

namespace App\Http\Controllers\Api\V1;

use BaseController;
use User;
use Input;
use Hash;
use Validator;
use Response;

class AuthController extends BaseController
{
    /**
     * @OA\Post(
     *     path="/api/v1/auth/login",
     *     summary="User login",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"username","password"},
     *             @OA\Property(property="username", type="string"),
     *             @OA\Property(property="password", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Login successful"),
     *     @OA\Response(response=401, description="Invalid credentials")
     * )
     */
    public function login()
    {
        $credentials = Input::only('username', 'password');

        $validator = Validator::make($credentials, [
            'username' => 'required',
            'password' => 'required',
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

        $user = User::where('username', $credentials['username'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return Response::json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_CREDENTIALS',
                    'message' => 'Invalid username or password.'
                ]
            ], 401);
        }

        // Create Sanctum token
        $token = $user->createToken('api-token')->plainTextToken;

        return Response::json([
            'success' => true,
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'role' => $user->role,
                ]
            ],
            'message' => 'Login successful'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/logout",
     *     summary="User logout",
     *     tags={"Authentication"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Logout successful")
     * )
     */
    public function logout()
    {
        auth()->user()->currentAccessToken()->delete();

        return Response::json([
            'success' => true,
            'message' => 'Logout successful'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/auth/user",
     *     summary="Get authenticated user",
     *     tags={"Authentication"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="User retrieved")
     * )
     */
    public function user()
    {
        $user = auth()->user();

        return Response::json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'role' => $user->role,
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/register",
     *     summary="Register new librarian account",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","username","password"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="username", type="string"),
     *             @OA\Property(property="password", type="string"),
     *             @OA\Property(property="role", type="string", enum={"librarian", "admin"})
     *         )
     *     ),
     *     @OA\Response(response=201, description="Account created"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function register()
    {
        $input = Input::all();

        $validator = Validator::make($input, [
            'name' => 'required|max:255',
            'username' => 'required|unique:users,username|max:255',
            'password' => 'required|min:6',
            'role' => 'in:librarian,admin'
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

        $user = new User;
        $user->name = $input['name'];
        $user->username = $input['username'];
        $user->password = Hash::make($input['password']);
        $user->role = isset($input['role']) ? $input['role'] : 'librarian';
        $user->verification_status = 1;
        $user->save();

        $token = $user->createToken('api-token')->plainTextToken;

        return Response::json([
            'success' => true,
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'role' => $user->role,
                ]
            ],
            'message' => 'Account created successfully'
        ], 201);
    }
}
