<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Dynamic Engagement & Relevance Score (DERS) Weights
    |--------------------------------------------------------------------------
    |
    | These weights control the importance of different factors in the comment
    | scoring algorithm. Fine-tuning these values will change how comments
    | are ranked.
    |
    */

    'weights' => [
        // Engagement Factors
        'like' => 0.4,       // Points per like
        'reply' => 0.7,      // Points per direct reply

        // Contextual & Social Boosts
        'post_author' => 2.5, // Strong boost if comment is by the Post's author
        'verified_user' => 1.5, // Boost for verified users
        'moderator' => 2.0,     // Boost for moderators (if different from verified)

        // Time Decay Factor
        'time_multiplier' => 1.8,  // Overall importance of freshness
        'decay_rate_lambda' => 0.05, // How quickly a comment's score "decays" per hour. Higher = faster decay.
    ],

    // special roles
    'roles' => [
        'verified' => ['umarov', 'goat'],
        'moderators' => ['umarov', 'goat'],
    ],
];
