<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TerminalActivity extends Model
{
    protected $fillable = [
        'admin_id',
        'driver_id',
        'activity',
        'description',
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}