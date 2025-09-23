<?php

return [
    'store_full_payload' => false,
    'redact_keys' => ['password', 'token', 'authorization', 'secret'],
    'retention_days' => 14,
    'notify_on_failure' => true,
    'notification_channels' => ['mail'],
    'routes' => false,
];
