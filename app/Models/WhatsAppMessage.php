<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message in a shop's WhatsApp chats (App\Services\WhatsAppInbox):
 * a customer's message to the shop's number (in, from Meta's webhook) or
 * the owner's reply (out). Offers themselves are MarketingMessage rows.
 */
class WhatsAppMessage extends Model
{
    public const IN = 'in';

    public const OUT = 'out';

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'shop_id', 'customer_shop_card_id', 'phone', 'name', 'direction', 'type', 'body',
        'wa_message_id', 'status', 'error', 'user_id', 'seen_at', 'delivered_at', 'read_at', 'created_at',
    ];

    protected function casts(): array
    {
        return ['seen_at' => 'datetime', 'delivered_at' => 'datetime', 'read_at' => 'datetime'];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(CustomerShopCard::class, 'customer_shop_card_id');
    }
}
