<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Database\Factories\QrCodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class QrCode extends Model
{
    /** @use HasFactory<QrCodeFactory> */
    use HasFactory, RecordsActivity;

    /** Codes are made in bulk (logged once per batch), so only edits and single deletes. */
    protected array $activityEvents = ['updated', 'deleted'];

    public function activityLabel(): string
    {
        return $this->code;
    }

    protected $fillable = [
        'qr_batch_id',
        'shop_id',
        'code',
        'serial',
        'destination_url',
        'mapped_at',
    ];

    protected function casts(): array
    {
        return [
            'serial' => 'integer',
            'mapped_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(QrBatch::class, 'qr_batch_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function designs(): BelongsToMany
    {
        return $this->belongsToMany(QrDesign::class, 'qr_code_design')->withTimestamps();
    }

    /**
     * `serial` is the code's number in its batch (1, 2, 3 …, in generation
     * order), printed as 001, 002 … on designs that show it. Stored, not
     * counted, so deleting a code never renumbers the stickers after it.
     * The generator sets it; one-off creates (tests) get the next free one.
     */
    protected static function booted(): void
    {
        static::creating(function (QrCode $qr) {
            $qr->serial ??= (int) static::where('qr_batch_id', $qr->qr_batch_id)->max('serial') + 1;
        });
    }

    /** The permanent link printed inside the QR image. */
    public function scanUrl(): string
    {
        return route('qr.show', $this->code);
    }
}
