<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class MarketingCampaign extends Model
{
    use RecordsActivity;

    /** Sending progress isn't logged - only that the offer was sent. */
    protected array $activityEvents = ['created'];

    public function activityLabel(): string
    {
        return '';
    }

    protected $fillable = [
        'shop_id', 'user_id', 'audience', 'message', 'image_path', 'recipients_count',
        'sent_count', 'failed_count', 'status', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MarketingMessage::class);
    }

    /** The poster's full public URL - WhatsApp fetches it from here, so it must be absolute. */
    public function posterUrl(): ?string
    {
        return $this->image_path ? url(Storage::disk('uploads')->url($this->image_path)) : null;
    }

    public function isSending(): bool
    {
        return $this->status === 'sending';
    }

    /** The owner's view of it: no phone numbers, just counts. */
    public function summary(): array
    {
        return [
            'id' => $this->id,
            'audience' => $this->audience,
            'message' => $this->message,
            'poster_url' => $this->posterUrl(),
            'recipients' => $this->recipients_count,
            'sent' => $this->sent_count,
            'failed' => $this->failed_count,
            'sending' => $this->isSending(),
            'date' => $this->created_at->timezone('Europe/London')->format('j M Y, g:i A'),
        ];
    }
}
