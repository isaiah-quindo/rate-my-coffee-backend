<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Str;

class SocialAuthController extends Controller
{
    public function redirectToFacebook()
    {
        // stateless() is convenient for API usage (no server session state)
        return Socialite::driver('facebook')->stateless()->redirect();
    }

    public function handleFacebookCallback(Request $request)
    {
        // Retrieve user info from Facebook
        try {
            $fbUser = Socialite::driver('facebook')->stateless()->user();
        } catch (\Exception $e) {
            return redirect(env('FRONTEND_URL') . '/auth/error?msg=fb_error');
        }

        // Find or create user
        $user = User::where('facebook_id', $fbUser->getId())
            ->first();

        if (!$user) {
            // if same email exists, link accounts
            $user = User::where('email', $fbUser->getEmail())->first();
            if ($user) {
                $user->update([
                    'facebook_id' => $fbUser->getId(),
                ]);
            } else {
                $user = User::create([
                    'name' => $fbUser->getName() ?? $fbUser->getNickname() ?? 'Facebook User',
                    'email' => $fbUser->getEmail(),
                    'facebook_id' => $fbUser->getId(),
                    'password' => bcrypt(Str::random(24)), // random password
                ]);
            }
        }

        // create a Sanctum personal access token
        $token = $user->createToken('facebook')->plainTextToken;

        // Option A (recommended if frontend and api share cookie domain):
        // set token as HTTP-only cookie and redirect to frontend
        $frontend = env('FRONTEND_URL', 'http://localhost:3000');
        $cookie = cookie(
            'api_token',        // name
            $token,             // value
            60 * 24 * 30,       // minutes (30 days)
            '/',                // path
            env('COOKIE_DOMAIN', null), // domain (e.g. .yourdomain.com) OR null for current host
            true,               // secure
            true,               // httpOnly
            false,              // raw
            'None'              // sameSite: 'None' to allow cross-site (when using secure)
        );

        return redirect($frontend . '/auth/success')->withCookie($cookie);

        // Option B (if you can't set cross-site cookies): redirect with token in fragment
        // return redirect($frontend . '/auth/success#token=' . $token);
    }
}
