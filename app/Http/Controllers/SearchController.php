<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class SearchController extends BaseController
{
    protected $searchables = [
        [
            'model'    => \App\Modules\ProjectManagement\Models\Task::class,
            'group'    => 'Tasks',
            'title'    => 'title',
            'subtitle' => 'project.name',
            'url'      => '/tasks/{id}',
        ],
        [
            'model'    => \App\Modules\ProjectManagement\Models\Project::class,
            'group'    => 'Projects',
            'title'    => 'name',
            'subtitle' => 'client.name',
            'url'      => '/projects/{id}',
        ],
        [
            'model'    => \App\Modules\Auth\Models\User::class,
            'group'    => 'Users',
            'title'    => 'name',
            'subtitle' => 'email',
            'url'      => '/settings/team',
        ],
        [
            'model'    => \App\Modules\ProjectManagement\Models\Client::class,
            'group'    => 'Clients',
            'title'    => 'name',
            'subtitle' => 'email',
            'url'      => '/clients/{id}',
        ],
        [
            'model'    => \App\Modules\Revenue\Models\Invoice::class,
            'group'    => 'Invoices',
            'title'    => 'invoice_number',
            'subtitle' => 'amount',
            'url'      => '/financials',
        ],
        [
            'model'    => \App\Modules\Revenue\Models\Proposal::class,
            'group'    => 'Proposals',
            'title'    => 'title',
            'subtitle' => 'status',
            'url'      => '/proposals/{id}',
        ],
        [
            'model'    => \App\Modules\Revenue\Models\ClientSow::class,
            'group'    => 'SOWs',
            'title'    => 'title',
            'subtitle' => 'status',
            'url'      => '/sow',
        ],
        [
            'model'    => \App\Modules\ProjectManagement\Models\Sprint::class,
            'group'    => 'Sprints',
            'title'    => 'name',
            'subtitle' => 'status',
            'url'      => '/sprints',
        ],
        [
            'model'    => \App\Modules\Revenue\Models\Prospect::class,
            'group'    => 'Prospects',
            'title'    => 'company_name',
            'subtitle' => 'contact_name',
            'url'      => '/prospects/{id}',
        ],
        [
            'model'    => \App\Modules\Revenue\Models\Goal::class,
            'group'    => 'Goals',
            'title'    => 'title',
            'subtitle' => 'period',
            'url'      => '/goals',
        ],
        [
            'model'    => \App\Modules\HR\Models\Announcement::class,
            'group'    => 'Announcements',
            'title'    => 'title',
            'subtitle' => 'published_at',
            'url'      => '/announcements',
        ],
        [
            'model'    => \App\Modules\HR\Models\KnowledgeArticle::class,
            'group'    => 'Knowledge Base',
            'title'    => 'title',
            'subtitle' => 'category',
            'url'      => '/knowledge-base/{id}',
        ]
    ];

    public function __invoke(Request $request)
    {
        $query = $request->input('q');

        if (!$query || strlen($query) < 2) {
            return response()->json([]);
        }

        $organizationId = session('current_organization_id', $request->user()?->organization_id);
        $results = [];

        $isAdmin = $request->user() && $request->user()->hasRoles(['ceo', 'admin']);

        foreach ($this->searchables as $config) {
            try {
                $modelClass = $config['model'];
                $builder = $modelClass::search($query)->where('organization_id', $organizationId);

                $usesSoftDeletes = in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive($modelClass));
                if ($isAdmin && $usesSoftDeletes) {
                    $builder->withTrashed();
                }

                $items = $builder->take(5)
                    ->get()
                    ->map(function ($item) use ($config, $usesSoftDeletes) {
                        $titleField = $config['title'];
                        $subtitleField = $config['subtitle'];
                        
                        $subtitle = $item;
                        foreach (explode('.', $subtitleField) as $segment) {
                            $subtitle = is_object($subtitle) ? $subtitle->{$segment} : null;
                        }
                        
                        $url = str_replace('{id}', $item->id ?? '', $config['url']);

                        $title = $item->$titleField ?? 'Unknown';
                        if ($usesSoftDeletes && $item->trashed()) {
                            $title .= ' [Deleted]';
                        }

                        return [
                            'id'       => $item->id,
                            'title'    => $title,
                            'subtitle' => is_string($subtitle) || is_numeric($subtitle) ? (string) $subtitle : '',
                            'url'      => $url,
                        ];
                    });                if ($items->isNotEmpty()) {
                    $results[] = [
                        'group' => $config['group'],
                        'items' => $items,
                    ];
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Search failed for {$config['group']}: " . $e->getMessage());
            }
        }

        return response()->json($results);
    }
}
