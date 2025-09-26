<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Http\Response;
use Throwable;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'], // cast 'hashed' in model will hash
        ]);

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();
        if (!$user || !Hash::check($credentials['password'], (string) $user->getAuthPassword())) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $user->currentAccessToken()?->delete();
        }
        return response()->json([], 204);
    }

    public function showUser(Request $request, int $id)
    {
        $authUser = $request->user();
        if (!$authUser) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if ((int) $authUser->id !== $id && !$authUser->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $user = User::query()->find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json($user);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        return response()->json($user);
    }

    public function redirectToFacebook(Request $request)
    {
        $driver = Socialite::driver('facebook')
            ->stateless()
            ->scopes(['email']);

        // Allow an optional callback_url override, but only for our public frontend domain
        $callbackUrl = (string) $request->query('callback_url', '');
        if ($callbackUrl !== '') {
            $parts = parse_url($callbackUrl);
            $host = $parts['host'] ?? '';
            $scheme = $parts['scheme'] ?? '';
            if ($scheme === 'https' && $host === 'ratemycoffee.ph') {
                // Note: The exact callback URL MUST be whitelisted in Facebook App settings
                $driver = $driver->redirectUrl($callbackUrl);
            }
        }

        $redirectUrl = $driver->redirect()->getTargetUrl();

        return response()->json([
            'url' => $redirectUrl,
        ]);
    }

    public function handleFacebookCallback(Request $request)
    {
        // Facebook returns a single-use authorization code. Ensure it's present.
        if (!$request->query('code')) {
            return response()->json(['message' => 'Missing authorization code'], 400);
        }

        try {
            $facebookUser = Socialite::driver('facebook')->stateless()->user();

            // Validate Facebook user data
            if (!$facebookUser->getId()) {
                return response()->json(['message' => 'Invalid Facebook user data'], 400);
            }
        } catch (Throwable $e) {
            Log::error('Facebook authentication failed', [
                'error' => $e->getMessage(),
                'code' => $request->query('code'),
                'state' => $request->query('state'),
            ]);
            return response()->json(['message' => 'Unable to authenticate with Facebook'], 401);
        }

        $user = User::query()->where('facebook_id', $facebookUser->getId())->first();

        $email = $facebookUser->getEmail();

        if (!$user && $email) {
            $userWithEmail = User::query()->where('email', $email)->first();

            if ($userWithEmail) {
                if ($userWithEmail->facebook_id && $userWithEmail->facebook_id !== $facebookUser->getId()) {
                    return response()->json([
                        'message' => 'Email already associated with another Facebook account',
                    ], 409);
                }

                if (!$userWithEmail->facebook_id) {
                    $userWithEmail->facebook_id = $facebookUser->getId();
                    $userWithEmail->save();
                }

                $user = $userWithEmail;
            }
        }

        if (!$user) {
            $emailForUser = $email ?: sprintf('fb_%s@facebook.local', $facebookUser->getId());

            if (!$email && User::query()->where('email', $emailForUser)->exists()) {
                $emailForUser = sprintf('fb_%s_%s@facebook.local', $facebookUser->getId(), Str::uuid()->toString());
            }

            $user = User::query()->create([
                'name' => $facebookUser->getName() ?: $facebookUser->getNickname() ?: 'Facebook User',
                'email' => $emailForUser,
                'password' => Hash::make(Str::random(40)),
                'facebook_id' => $facebookUser->getId(),
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }
}
