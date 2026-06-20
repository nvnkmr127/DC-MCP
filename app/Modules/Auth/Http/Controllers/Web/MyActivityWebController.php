<?php

namespace App\Modules\Auth\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MyActivityWebController extends Controller
{
    public function index(Request $request)
    {
        $activities = Activity::with(['subject'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(50);

        // Group activities by date
        $grouped = collect($activities->items())->groupBy(function ($activity) {
            if ($activity->created_at->isToday()) {
                return 'Today';
            } elseif ($activity->created_at->isYesterday()) {
                return 'Yesterday';
            } else {
                return $activity->created_at->format('M d, Y');
            }
        });

        return Inertia::render('Activity/Index', [
            'groupedActivities' => $grouped,
            'pagination' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'total' => $activities->total(),
            ]
        ]);
    }
}
