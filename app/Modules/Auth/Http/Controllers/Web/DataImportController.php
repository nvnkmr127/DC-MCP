<?php

namespace App\Modules\Auth\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\Task;
use App\Modules\ProjectManagement\Models\Client;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DataImportController extends Controller
{
    public function index()
    {
        return Inertia::render('Settings/DataImport');
    }

    public function downloadTemplate(Request $request)
    {
        $entityType = $request->query('entity_type');
        
        $headers = [
            'Content-type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename={$entityType}_template.csv",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0'
        ];

        $columns = [];
        switch ($entityType) {
            case 'projects':
                $columns = ['name', 'description', 'status', 'start_date', 'due_date'];
                break;
            case 'tasks':
                $columns = ['title', 'description', 'status', 'priority', 'project_name'];
                break;
            case 'clients':
                $columns = ['name', 'email', 'phone', 'company'];
                break;
            default:
                abort(400, 'Invalid entity type');
        }

        $callback = function() use($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:5120',
            'entity_type' => 'required|in:projects,tasks,clients',
        ]);

        $file = $request->file('csv_file');
        $csvData = file_get_contents($file->getRealPath());
        $lines = explode(PHP_EOL, $csvData);
        
        $imported = 0;
        $skipped = 0;
        $errors = [];
        $orgId = $request->user()->organization_id;

        $header = [];
        foreach ($lines as $index => $line) {
            $row = str_getcsv($line);
            if (empty(trim($line)) || count($row) === 0) continue;

            if ($index === 0) {
                $header = array_map('strtolower', array_map('trim', $row));
                continue;
            }

            // Map row values to header keys
            $data = [];
            foreach ($header as $colIndex => $colName) {
                $data[$colName] = $row[$colIndex] ?? null;
            }

            try {
                if ($request->entity_type === 'projects') {
                    if (empty($data['name'])) { $skipped++; continue; }
                    Project::create([
                        'organization_id' => $orgId,
                        'name' => $data['name'],
                        'slug' => Str::slug($data['name']) . '-' . uniqid(),
                        'description' => $data['description'] ?? null,
                        'status' => strtolower($data['status'] ?? 'active'),
                        'start_date' => !empty($data['start_date']) ? Carbon::parse($data['start_date']) : null,
                        'end_date' => !empty($data['due_date']) ? Carbon::parse($data['due_date']) : null,
                    ]);
                    $imported++;
                } elseif ($request->entity_type === 'tasks') {
                    if (empty($data['title'])) { $skipped++; continue; }
                    
                    $projectId = null;
                    if (!empty($data['project_name'])) {
                        $project = Project::where('organization_id', $orgId)
                            ->where('name', trim($data['project_name']))
                            ->first();
                        if ($project) {
                            $projectId = $project->id;
                        }
                    }

                    Task::create([
                        'organization_id' => $orgId,
                        'title' => $data['title'],
                        'description' => $data['description'] ?? null,
                        'status' => strtolower($data['status'] ?? 'todo'),
                        'priority' => strtolower($data['priority'] ?? 'medium'),
                        'project_id' => $projectId,
                    ]);
                    $imported++;
                } elseif ($request->entity_type === 'clients') {
                    if (empty($data['name'])) { $skipped++; continue; }
                    Client::create([
                        'organization_id' => $orgId,
                        'name' => $data['name'],
                        'email' => $data['email'] ?? null,
                        'phone' => $data['phone'] ?? null,
                        'company' => $data['company'] ?? null,
                    ]);
                    $imported++;
                }
            } catch (\Exception $e) {
                Log::error("Import Error: " . $e->getMessage());
                $skipped++;
                $errors[] = "Row {$index}: " . $e->getMessage();
            }
        }

        return back()->with('success', "Import completed. Imported: {$imported}, Skipped/Failed: {$skipped}.");
    }
}
