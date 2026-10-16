<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A shop's own WhatsApp Business number (App\Services\WhatsAppConnect).
 * Its offers go out from here; nothing is sent while a shop has none.
 */
class ShopWhatsAppAccount extends Model
{
    use RecordsActivity;

    public const MODE_NEW = 'new';

    public const MODE_EXISTING = 'existing';

    public const MODE_KEYS = 'keys';

    protected $table = 'shop_whatsapp_accounts';

    protected array $activitySecret = ['access_token'];

    /** Template checks are bookkeeping, not something anyone did. */
    protected array $activityIgnore = ['templates', 'templates_checked_at'];

    protected $fillable = [
        'shop_id', 'mode', 'waba_id', 'phone_number_id', 'display_phone_number', 'verified_name', 'access_token', 'connected_by',
        'templates', 'templates_checked_at',
    ];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return ['access_token' => 'encrypted', 'templates' => 'array', 'templates_checked_at' => 'datetime'];
    }

    /** Meta's status of one of our templates on this account (WhatsAppTemplates), null = never checked. */
    public function templateStatus(string $name): ?string
    {
        return $this->templates[$name] ?? null;
    }

    /**
     * Meta sends this app's webhooks (delivery ticks, replies) only for numbers
     * connected through our popup; a "keys" number's webhooks go to the
     * owner's own Meta app.
     */
    public function receivesWebhooks(): bool
    {
        return $this->mode !== self::MODE_KEYS;
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function activityShopId(): ?int
    {
        return $this->shop_id;
    }

    public function activityLabel(): string
    {
        return 'WhatsApp number '.($this->display_phone_number ?? $this->phone_number_id);
    }

    /** What the owner / admin pages show (never the token). */
    public function summary(): array
    {
        return [
            'mode' => $this->mode,
            'phone' => $this->display_phone_number,
            'name' => $this->verified_name,
            'waba_id' => $this->waba_id,
            'phone_number_id' => $this->phone_number_id,
            'connected_at' => $this->created_at?->timezone(config('app.timezone'))->format('j M Y'),
            'templates' => [
                'offer' => $this->templateStatus(config('services.whatsapp.template')),
                'poster' => $this->templateStatus(config('services.whatsapp.template_image')),
            ],
            'webhooks' => $this->receivesWebhooks(),
        ];
    }
}
