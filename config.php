<?php
// Copy this file to config.local.php if you need to override these settings.
return [
    'db_host' => getenv('DB_HOST') ?: '127.0.0.1',
    'db_name' => getenv('DB_NAME') ?: 'gamesclubbd',
    'db_user' => getenv('DB_USER') ?: 'root',
    'db_pass' => getenv('DB_PASS') ?: '',
    'app_url' => rtrim(getenv('APP_URL') ?: '', '/'),
];
