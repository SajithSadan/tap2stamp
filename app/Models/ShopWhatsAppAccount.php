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

    protected $fillable = ['shop_id', 'mode', 'waba_id', 'phone_number_id', 'display_phone_number', 'verified_name', 'access_token', 'connected_by'];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return ['access_token' => 'encrypted'];
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
        ];
    }
}
