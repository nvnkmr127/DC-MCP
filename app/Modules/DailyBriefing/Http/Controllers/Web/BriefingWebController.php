<?php

namespace App\Modules\DailyBriefing\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Modules\DailyBriefing\Models\DailyBriefing;
use App\Modules\DailyBriefing\Jobs\GenerateDailyBriefingJob;

class BriefingWebController extends Controller
{
    public function index(Request $request)
    {
        $briefings = DailyBriefing::where('user_id', $request->user()->id)
            ->orderByDesc('date')
            ->paginate(20)
            ->through(fn($b) => [
                'id'          => $b->id,
                'date'        => $b->date->toDateString(),
                'status'      => $b->status,
                'digest_text' => $b->digest_text ? substr($b->digest_text, 0, 200) : null,
                'digest_html' => null, // don't send full HTML in list
                'delivered_at' => $b->delivered_at?->toISOString(),
            ]);

        return Inertia::render('Briefings/Index', [
            'briefings' => $briefings,
        ]);
    }

    public function show(DailyBriefing $briefing)
    {
        if ($briefing->user_id !== auth()->id()) {
            abort(403);
        }

        return Inertia::render('Briefings/Show', [
            'briefing' => [
                'id'          => $briefing->id,
                'date'        => $briefing->date->toDateString(),
                'status'      => $briefing->status,
                'digest_text' => $briefing->digest_text,
                'digest_html' => $briefing->digest_html,
                'delivered_at' => $briefing->delivered_at?->toISOString(),
            ],
        ]);
    }

    public function generate(Request $request)
    {
        $user = $request->user();
        $today = now()->toDateString();

        $briefing = DailyBriefing::firstOrCreate(
            ['user_id' => $user->id, 'date' => $today],
            [
                'organization_id' => $user->organization_id,
                'status'          => 'pending',
            ]
        );

        if (in_array($briefing->status, ['generating', 'ready', 'delivered'])) {
            return back()->with('success', 'Briefing already ' . $briefing->status . '.');
        }

        $briefing->update(['status' => 'generating']);
        GenerateDailyBriefingJob::dispatch($user, $today);

        return back()->with('success', 'Briefing generation started.');
    }
}
