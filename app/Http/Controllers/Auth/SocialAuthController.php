<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Hash;

class SocialAuthController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(Request $request)
    {
        try {
            $socialUser = Socialite::driver('google')->stateless()->user();
        } catch (\Exception $e) {
            Log::error('Google OAuth error: '.$e->getMessage());
            return redirect(config('app.url'));
        }

        if (!$socialUser || !isset($socialUser->email)) {
            return redirect(config('app.url'));
        }

        // Trouver ou créer l'utilisateur
        $user = User::where('email', $socialUser->email)->first();
        if (!$user) {
            $nameParts = explode(' ', trim($socialUser->name ?? ''));
            $first = $nameParts[0] ?? null;
            $last = count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : null;
            $defaultRoleId = Role::where('name', 'assistant')->value('id');

            $user = User::create([
                'username' => $socialUser->email,
                'first_name' => $first,
                'last_name' => $last,
                'email' => $socialUser->email,
                // password nullable for social accounts
                'password' => Hash::make(Str::random(40)),
                'role_id' => $defaultRoleId,
                'is_active' => true,
                'profile_image' => $socialUser->avatar ?? null,
            ]);
        }

        $user->load('role');

        // Créer un token API (Sanctum)
        $token = $user->createToken('frontend')->plainTextToken;

        // Préparer les données utilisateur minimales
        $userData = [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'role' => $user->role ? $user->role->name : 'user',
            'role_name' => $user->role ? $user->role->name : 'user',
            'profile_image' => $user->profile_image_url ?? null,
            'profile_image_url' => $user->profile_image_url ?? null,
        ];

        $frontend = env('FRONTEND_URL', config('app.url'));

        // Rediriger vers le frontend avec token + user encodés dans le fragment
        $payload = '#token=' . $token . '&user=' . urlencode(json_encode($userData));
        return redirect($frontend . '/auth/google/success' . $payload);
    }
}
