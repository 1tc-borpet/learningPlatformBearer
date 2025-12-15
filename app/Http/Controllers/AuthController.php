<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|confirmed|min:8',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Failed to register user',
                'errors' => $e->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'student',
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (!$token = auth('api')->attempt($credentials)) {
            return response()->json(['message' => 'Invalid email or password'], 401);
        }

        $user = auth('api')->user();

        return response()->json([
            'message' => 'Login successful',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'access' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60
            ]
        ]);
    }

    public function logout()
    {
        auth('api')->logout();
        return response()->json(['message' => 'Logout successful']);
    }

    public function refresh()
    {
        return response()->json([
            'access' => [
                'token' => auth('api')->refresh(),
                'token_type' => 'Bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60
            ]
        ]);
    }

    public function me()
    {
        return response()->json(auth('api')->user());
    }
}