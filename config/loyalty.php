<?php

return [
    'stamp_cooldown_hours' => (int) env('STAMP_COOLDOWN_HOURS', 8),
    'default_max_stamps' => 6,
    // A staff PIN sign-in on a shared device lasts roughly one shift.
    'staff_session_hours' => (int) env('STAFF_SESSION_HOURS', 12),
];
