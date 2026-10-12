<?php

namespace App\Support;

/**
 * Where a shop is in the sales pipeline (shops.crm_stage), set by the admin
 * on the shop's Account tab. "Activated" is never stored: a shop still in
 * trial that has given its first stamp shows as activated (see display()).
 */
class CrmStage
{
    public const TRIAL = 'trial';

    public const OFFERED = 'offered';

    public const PAID = 'paid';

    public const LOST = 'lost';

    /** Stored stages, in pipeline order => label. */
    public const STORED = [
        self::TRIAL => 'Trial',
        self::OFFERED => 'Upgrade offered',
        self::PAID => 'Paid',
        self::LOST => 'Lost',
    ];

    /** What the grids show: trial splits into "Trial" / "Activated" by whether stamping started. */
    public const DISPLAY = [
        'trial' => 'Trial',
        'activated' => 'Activated',
        'offered' => 'Upgrade offered',
        'paid' => 'Paid',
        'lost' => 'Lost',
    ];

    public static function display(string $stored, bool $hasStamped): string
    {
        return $stored === self::TRIAL && $hasStamped ? 'activated' : $stored;
    }
}
