<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if ($user->status === 'Pending Invitation') {
            throw ValidationException::withMessages([
                'email' => ['Your invitation has not been accepted yet. Check your email for the invitation link.'],
            ]);
        }

        if ($user->status === 'Suspended') {
            throw ValidationException::withMessages([
                'email' => ['Your account has been suspended. Contact HR for assistance.'],
            ]);
        }

        if ($user->status === 'Inactive') {
            throw ValidationException::withMessages([
                'email' => ['This account is inactive. Contact HR for assistance.'],
            ]);
        }

        $token = $user->createToken('portal')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
