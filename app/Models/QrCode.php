<?php

namespace App\Models;

use Database\Factories\QrCodeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrCode extends Model
{
    /** @use HasFactory<QrCodeFactory> */
    use HasFactory;

    protected $fillable = [
        'qr_batch_id',
        'code',
        'destination_url',
        'mapped_at',
    ];

    protected function casts(): array
    {
        return [
            'mapped_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(QrBatch::class, 'qr_batch_id');
    }

    /**
     * Adds `serial`: the code's position in its batch (1, 2, 3 …, in generation
     * order), printed as 001, 002 … on designs that show it. Stable, so a
     * reprint of one code carries the same number as the original.
     */
    public function scopeWithSerial(Builder $query): void
    {
        $query->select('qr_codes.*')->selectSub(
            static::query()->from('qr_codes as siblings')
                ->selectRaw('count(*)')
                ->whereColumn('siblings.qr_batch_id', 'qr_codes.qr_batch_id')
                ->whereColumn('siblings.id', '<=', 'qr_codes.id'),
            'serial',
        );
    }

    /** The permanent link printed inside the QR image. */
    public function scanUrl(): string
    {
        return route('qr.show', $this->code);
    }
}
