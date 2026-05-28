<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     * These are available on every Inertia page via usePage().props
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        $permissions = [];
        $roles = [];

        if ($user) {
            $user->loadMissing('roles');
            $roles = $user->roles->pluck('slug')->toArray();
            foreach ($user->roles as $role) {
                foreach ($role->permissions ?? [] as $resource => $actions) {
                    if (!isset($permissions[$resource])) {
                        $permissions[$resource] = [];
                    }
                    $permissions[$resource] = array_values(
                        array_unique(array_merge($permissions[$resource], $actions))
                    );
                }
            }
        }

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id'              => $user->id,
                    'name'            => $user->name,
                    'email'           => $user->email,
                    'avatar_url'      => $user->avatar_url,
                    'organization_id' => $user->organization_id,
                    'timezone'        => $user->timezone ?? 'Asia/Kolkata',
                    'preferences'     => $user->preferences ?? [],
                    'roles'           => $roles,
                ] : null,
                'permissions' => $permissions,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],
            'app' => [
                'name'     => config('app.name', 'Digicloudify'),
                'currency' => '₹',
                'timezone' => 'Asia/Kolkata',
            ],
        ]);
    }
}
