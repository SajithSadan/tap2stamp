<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingMessage extends Model
{
    protected $fillable = ['marketing_campaign_id', 'customer_shop_card_id', 'status', 'wa_message_id', 'error', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'marketing_campaign_id');
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(CustomerShopCard::class, 'customer_shop_card_id');
    }
}
