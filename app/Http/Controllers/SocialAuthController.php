<?php

namespace App\Http\Controllers;

use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SocialAuthController extends Controller
{
    public function googleLogin()
    {
        return Socialite::driver('google')->redirect();
    }

    public function googleAuthentication(Request $request)
    {
        try {
            // Get the token from request
            $token = $request->input('credential');
            if (!$token) {
                return response()->json(['message' => 'No token provided'], 400);
            }

            // Get the Google user details using the token
            $googleUser = Socialite::driver('google')->stateless()->userFromToken($token);

            // Find existing user
            $existingUser = User::where('google_id', $googleUser->id)
                ->orWhere('email', $googleUser->email)
                ->first();

            if ($existingUser) {
                // Update Google ID if user exists but doesn't have it set
                if (!$existingUser->google_id) {
                    $existingUser->update(['google_id' => $googleUser->id]);
                }

                // Generate Sanctum token
                $token = $existingUser->createToken('google-auth')->plainTextToken;

                return response()->json([
                    'user' => $existingUser,
                    'token' => $token
                ]);
            }

            // Create new user
            $newUser = User::create([
                'name' => $googleUser->name,
                'email' => $googleUser->email,
                'password' => Hash::make(Str::random(24)),
                'google_id' => $googleUser->id,
            ]);

            // Generate Sanctum token
            $token = $newUser->createToken('google-auth')->plainTextToken;

            return response()->json([
                'user' => $newUser,
                'token' => $token
            ], 201);
        } catch (Exception $e) {
            \Log::error('Google authentication error: ' . $e->getMessage());
            return response()->json(['message' => 'Authentication failed'], 500);
        }
    }
}
