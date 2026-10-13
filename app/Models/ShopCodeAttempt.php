<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One shop code typed on a staff phone that isn't set up (App\Services\ShopCodeJoin). */
class ShopCodeAttempt extends Model
{
    protected $fillable = ['browser_id', 'ip', 'success'];

    protected function casts(): array
    {
        return ['success' => 'boolean'];
    }
}
