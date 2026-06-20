<?php

namespace App\Modules\Reporting\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Reporting\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SharedReportWebController extends Controller
{
    /**
     * Display a shared report publicly.
     */
    public function show(string $token)
    {
        $report = Report::where('share_token', $token)
            ->where('is_public', true)
            ->firstOrFail();

        if ($report->status !== 'ready' || empty($report->generated_file_path)) {
            abort(404, 'Report is not ready or file is missing.');
        }

        if (!Storage::disk('public')->exists($report->generated_file_path)) {
            abort(404, 'Report file not found on disk.');
        }

        $filePath = Storage::disk('public')->path($report->generated_file_path);

        return response()->file($filePath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $report->title . '.pdf"',
        ]);
    }
}
