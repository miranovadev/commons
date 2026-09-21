<?php

return [
    // Counts inference attempts, including discarded stale actions.
    // Reaching this limit leaves the active Cycle and Phase untouched.
    'max_actions_per_wake' => (int) env('PARTICIPANT_MAX_ACTIONS_PER_WAKE', 64),
    'wake_timeout' => (int) env('PARTICIPANT_WAKE_TIMEOUT', 1800),
];
