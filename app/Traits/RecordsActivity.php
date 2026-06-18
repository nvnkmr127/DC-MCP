<?php

namespace App\Traits;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Model;

trait RecordsActivity
{
    protected static function bootRecordsActivity()
    {
        foreach (static::recordableEvents() as $event) {
            static::$event(function (Model $model) use ($event) {
                $model->recordActivity($model->activityDescription($event));
            });
        }
    }

    protected function activityDescription($description)
    {
        return "{$description}_" . strtolower(class_basename($this));
    }

    protected static function recordableEvents()
    {
        if (isset(static::$recordableEvents)) {
            return static::$recordableEvents;
        }

        return ['created', 'updated', 'deleted'];
    }

    public function recordActivity($description)
    {
        $this->activities()->create([
            'user_id' => auth()->id(),
            'description' => $description,
            'changes' => $this->activityChanges(),
        ]);
    }

    public function activities()
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    protected function activityChanges()
    {
        if ($this->wasChanged()) {
            return [
                'before' => array_intersect_key($this->getOriginal(), $this->getChanges()),
                'after' => $this->getChanges()
            ];
        }

        return null;
    }
}
