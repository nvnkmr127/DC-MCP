<?php

namespace App\Modules\Auth\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClientScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (auth()->check()) {
            $user = auth()->user();
            if ($user && method_exists($user, 'hasRoles') && $user->hasRoles('client')) {
                // Find client matching user's email within the same organization
                $client = DB::table('clients')
                    ->where('organization_id', $user->organization_id)
                    ->where('email', $user->email)
                    ->first();

                if ($client) {
                    $table = $model->getTable();
                    if ($table === 'clients') {
                        // For the clients table itself, restrict to their own record
                        $builder->where($table . '.id', $client->id);
                    } elseif (Schema::hasColumn($table, 'client_id')) {
                        // For tables with a client_id column (e.g. projects, tasks, reports), restrict to their client
                        $builder->where($table . '.client_id', $client->id);
                    } else {
                        // For tables that are completely internal (no client_id, and not the clients table),
                        // clients shouldn't see anything.
                        $builder->whereRaw('1 = 0');
                    }
                } else {
                    // If no client matches their email, restrict completely
                    $builder->whereRaw('1 = 0');
                }
            }
        }
    }
}
