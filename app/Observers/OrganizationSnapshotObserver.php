<?php

namespace App\Observers;

use App\Models\Driver;
use Illuminate\Database\Eloquent\Model;

class OrganizationSnapshotObserver
{
    public function creating(Model $record): void
    {
        $record->organization_id = Driver::whereKey($record->driver_id)->value('organization_id');
    }
}
