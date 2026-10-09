<?php

return [
    'enabled' => (bool) env('PROMOTION_ENABLED', env('APP_ENV') === 'production'),

    // social posts per day (question of the day + milestones together)
    'daily_cap' => (int) env('PROMOTION_DAILY_CAP', 2),

    // a question is not promoted again within this many days
    'cooldown_days' => (int) env('PROMOTION_COOLDOWN_DAYS', 45),

    'milestones' => [50, 100, 250, 500, 1000],

    // win-back emails to people who stopped visiting; off until you have looked at a sample
    'winback' => [
        'enabled' => (bool) env('WINBACK_ENABLED', false),
        'per_day' => (int) env('WINBACK_PER_DAY', 5),
        'inactive_min_days' => 30,
        'inactive_max_days' => 180,
    ],
];
