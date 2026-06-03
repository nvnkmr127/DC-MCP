<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Illuminate\Support\Str;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Models\Organization;
use App\Modules\Auth\Models\Role;

class AuthWebController extends Controller
{
    /** Seeded demo accounts — only available in non-production */
    private const DEMO_ACCOUNTS = [
        ['role' => 'ceo',             'email' => 'ceo@digicloudify.com',       'label' => 'CEO',            'color' => 'bg-purple-600'],
        ['role' => 'project_manager', 'email' => 'pm@digicloudify.com',        'label' => 'Project Manager','color' => 'bg-indigo-600'],
        ['role' => 'developer',       'email' => 'developer@digicloudify.com', 'label' => 'Developer',      'color' => 'bg-blue-600'],
        ['role' => 'analyst',         'email' => 'analyst@digicloudify.com',   'label' => 'Analyst',        'color' => 'bg-cyan-600'],
        ['role' => 'marketer',        'email' => 'marketer@digicloudify.com',  'label' => 'Marketer',       'color' => 'bg-pink-600'],
        ['role' => 'client',          'email' => 'client@digicloudify.com',    'label' => 'Client',         'color' => 'bg-gray-600'],
    ];

    public function showLogin()
    {
        return Inertia::render('Auth/Login', [
            'demo_accounts' => app()->isProduction() ? [] : self::DEMO_ACCOUNTS,
        ]);
    }

    public function showRegister()
    {
        return Inertia::render('Auth/Register');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            Log::warning('Failed web login attempt', [
                'email' => $request->email,
                'ip'    => $request->ip(),
            ]);
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();
        Log::info('User web login', [
            'user_id'         => $user->id,
            'organization_id' => $user->organization_id,
            'ip'              => $request->ip(),
        ]);

        return redirect()->intended('/dashboard');
    }

    /**
     * Instantly log in as a seeded demo account.
     * Disabled in production — returns 404.
     */
    public function quickLogin(Request $request, string $role)
    {
        abort_if(app()->isProduction(), 404);

        $account = collect(self::DEMO_ACCOUNTS)->firstWhere('role', $role);
        abort_if(!$account, 404, "Unknown demo role: {$role}");

        $user = User::where('email', $account['email'])->first();
        abort_if(!$user, 404, "Demo user not found. Run: php artisan db:seed");

        Auth::login($user, true); // remember=true for convenience
        $request->session()->regenerate();

        return redirect('/dashboard');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name'              => 'required|string|max:120',
            'email'             => 'required|email|unique:users,email',
            'password'          => 'required|string|min:8|confirmed',
            'organization_name' => 'required|string|max:120',
        ]);

        $org = Organization::create([
            'name'     => $data['organization_name'],
            'slug'     => Str::slug($data['organization_name']) . '-' . substr(uniqid(), -5),
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
        ]);

        $user = User::create([
            'name'            => $data['name'],
            'email'           => $data['email'],
            'password'        => Hash::make($data['password']),
            'organization_id' => $org->id,
        ]);

        $ceoRole = Role::where('slug', 'ceo')
            ->where('organization_id', $org->id)
            ->first();
        if ($ceoRole) {
            $user->roles()->attach($ceoRole->id);
        }

        Auth::login($user);
        $request->session()->regenerate();

        Log::info('New organization registered', [
            'user_id'         => $user->id,
            'organization_id' => $org->id,
            'ip'              => $request->ip(),
        ]);

        return redirect('/dashboard');
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            Log::info('User web logout', [
                'user_id'         => $user->id,
                'organization_id' => $user->organization_id,
                'ip'              => $request->ip(),
            ]);
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}
