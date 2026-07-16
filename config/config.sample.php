<?php
/**
 * CyberBlogs configuration — COPY THIS FILE TO config.php
 * install.php generates config.php automatically; this sample is a fallback
 * for manual setup. Never commit config.php anywhere public.
 */
return [
    // InfinityFree MySQL credentials (from control panel)
    'db_host'    => 'sqlXXX.infinityfree.com',
    'db_name'    => 'if0_XXXXXXXX_cyberblogs',
    'db_user'    => 'if0_XXXXXXXX',
    'db_pass'    => 'CHANGE_ME',

    // Canonical site URL, no trailing slash
    'site_url'   => 'https://byte.pro.bd',

    // 64 hex chars. Generate: php -r "echo bin2hex(random_bytes(32));"
    // Used for TOTP-secret encryption + IP hashing. Changing it invalidates 2FA.
    'secret_key' => '',
];
