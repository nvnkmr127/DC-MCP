<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeatureFlag;
use App\Modules\Auth\Models\Organization;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FeatureFlagController extends Controller
{
    public function index()
    {
        $flags = FeatureFlag::with('organization')->orderBy('feature')->get();
        $organizations = Organization::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/FeatureFlags', [
            'flags' => $flags,
            'organizations' => $organizations,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'feature' => 'required|string|max:255',
            'description' => 'nullable|string',
            'organization_id' => 'nullable|exists:organizations,id',
            'is_enabled' => 'boolean',
        ]);

        if (!isset($data['is_enabled'])) {
            $data['is_enabled'] = false;
        }

        FeatureFlag::create($data);

        return back()->with('success', 'Feature flag created.');
    }

    public function toggle(FeatureFlag $featureFlag)
    {
        $featureFlag->update([
            'is_enabled' => !$featureFlag->is_enabled,
        ]);

        return back()->with('success', 'Feature flag toggled.');
    }

    public function destroy(FeatureFlag $featureFlag)
    {
        $featureFlag->delete();
        return back()->with('success', 'Feature flag deleted.');
    }
}
