<?php

namespace App\Traits;

use App\Models\UserActivityLog;
use Illuminate\Database\Eloquent\Model;

trait LogsModelChanges
{
    protected static function bootLogsModelChanges(): void
    {
        static::created(function (Model $model) {
            static::logActivity($model, 'created', $model->getLoggableSnapshot());
        });

        static::updated(function (Model $model) {
            $changes = $model->getRelevantChanges();
            if (!empty($changes)) {
                static::logActivity($model, 'updated', $changes);
            }
        });
    }

    protected function getLoggableAttributes(): array
    {
        return property_exists($this, 'loggableAttributes')
            ? $this->loggableAttributes
            : [];
    }

    protected function getRelevantChanges(): array
    {
        $changes = [];
        $attrs = $this->getLoggableAttributes();

        foreach ($attrs as $attr) {
            if ($this->isDirty($attr)) {
                $changes[$attr] = [
                    'from' => $this->getOriginal($attr),
                    'to' => $this->{$attr},
                ];
            }
        }

        return $changes;
    }

    protected function getLoggableSnapshot(): array
    {
        $snapshot = [];
        foreach ($this->getLoggableAttributes() as $attr) {
            $snapshot[$attr] = $this->{$attr};
        }
        return $snapshot;
    }

    protected static function logActivity(Model $model, string $action, array $metadata = []): void
    {
        try {
            UserActivityLog::create([
                'user_id' => auth()->id(),
                'action' => sprintf('%s.%s', class_basename($model), $action),
                'route_name' => request()->route()?->getName(),
                'method' => request()->method(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'path' => request()->path(),
                'metadata' => [
                    'model_id' => $model->getKey(),
                    'changes' => $metadata,
                ],
            ]);
        } catch (\Throwable $e) {
            // Silencioso para não impactar fluxo
        }
    }
}
