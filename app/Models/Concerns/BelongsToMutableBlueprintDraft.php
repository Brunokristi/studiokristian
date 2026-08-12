<?php

namespace App\Models\Concerns;

use LogicException;

trait BelongsToMutableBlueprintDraft
{
    protected static function bootBelongsToMutableBlueprintDraft(): void
    {
        $guard = function (self $model): void {
            $version = $model->blueprintVersion()->first();
            if ($version && $version->status !== 'draft') {
                throw new LogicException('Published service blueprint definitions are immutable.');
            }
        };

        static::creating($guard);
        static::updating($guard);
        static::deleting($guard);
    }
}