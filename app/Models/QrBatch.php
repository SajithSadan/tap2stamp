<?php

namespace App\Models;

use Database\Factories\QrBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QrBatch extends Model
{
    /** @use HasFactory<QrBatchFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function codes(): HasMany
    {
        return $this->hasMany(QrCode::class);
    }

    /** "Batch #3", or "Batch #3 · Summer stickers" when it was given a name. */
    public function label(): string
    {
        return 'Batch #'.$this->id.($this->name ? ' · '.$this->name : '');
    }
}
