<?php

return [
    'stamp_cooldown_hours' => (int) env('STAMP_COOLDOWN_HOURS', 8),
    // Shops with "Multiple stamps a day" (App\Support\Features): the gap between stamps instead -
    // just long enough that an accidental double scan doesn't count twice.
    'multi_stamp_gap_minutes' => (int) env('MULTI_STAMP_GAP_MINUTES', 2),
    'default_max_stamps' => 6,
    // A staff PIN sign-in on a shared device lasts roughly one shift.
    'staff_session_hours' => (int) env('STAFF_SESSION_HOURS', 12),
    // WhatsApp marketing: at most one campaign per shop in this many hours,
    // and how many messages one "send batch" request sends (no queue workers).
    'marketing_cooldown_hours' => (int) env('MARKETING_COOLDOWN_HOURS', 24),
    'marketing_batch_size' => 20,
];
