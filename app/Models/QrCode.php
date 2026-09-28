<?php

namespace App\Models;

use Database\Factories\QrCodeFactory;
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

    /** The permanent link printed inside the QR image. */
    public function scanUrl(): string
    {
        return route('qr.show', $this->code);
    }
}
