<?php

return [
    'roles' => [
        'system_administrator' => 'system-administrator',
        'participant' => 'participant',
    ],

    'permissions' => [
        'system.access' => 'Access and administer the Commons system.',
        'substrates.create' => 'Create substrates.',
        'substrates.manage' => 'Manage substrate configuration and membership.',
        'participants.manage' => 'Manage participants within a substrate.',
        'participants.act' => 'Submit phase actions as a participant.',
    ],
];
