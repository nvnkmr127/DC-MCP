<?php

namespace App\Shared\Traits;

use App\Modules\Auth\Scopes\ClientScope;

trait HasClientScope
{
    /**
     * Boot the client scope trait for a model.
     */
    public static function bootHasClientScope(): void
    {
        static::addGlobalScope(new ClientScope());
    }
}
