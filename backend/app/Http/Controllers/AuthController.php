<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // Redirect to provider (Google/Facebook)
    public function redirectToProvider($provider)
    {
        return Socialite::driver($provider)->stateless()->redirect();
    }

    // Handle callback from provider
    public function handleProviderCallback($provider)
    {
        $socialUser = Socialite::driver($provider)->stateless()->user();

        // Basic example: find or create local user by provider id or email
        $user = User::firstOrCreate(
            ['email' => $socialUser->getEmail()],
            [
                'name' => $socialUser->getName() ?: $socialUser->getNickname() ?: 'User',
                'password' => bcrypt(Str::random(32)),
            ]
        );

        // Here you would typically log the user in and redirect to SPA
        // For SPA using OAuth code flow, you may exchange for a token or set a session cookie

        // Example: redirect back to frontend with a temporary token (implement securely in production)
        $token = $user->createToken('oauth-token')->plainTextToken;

        $frontend = config('app.frontend_url', env('FRONTEND_URL', ''));
        $redirect = $frontend ? rtrim($frontend, '/') . '/?token=' . $token : '/';

        return redirect($redirect);
    }
}
