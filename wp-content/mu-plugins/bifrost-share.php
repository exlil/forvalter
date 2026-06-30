<?php
/**
 * Bifrost share proxy — auto-generated, safe to delete.
 */
define('BIFROST_SHARE_LOCAL', 'forvalter.ddev.site');
define('BIFROST_SHARE_PUBLIC', 'bifrost-3183-forvalter.maksimer.dev');

function bifrost_share_is_tunnel(): bool {
    return !empty($_SERVER['HTTP_CF_RAY']);
}

if (!bifrost_share_is_tunnel()) return;

// Pretend the request arrived with the public hostname so WordPress
// doesn't canonical-redirect from the (rewritten) local host to the
// public home URL — that loop is the cause of "too many redirects".
$_SERVER['HTTP_HOST'] = BIFROST_SHARE_PUBLIC;
$_SERVER['SERVER_NAME'] = BIFROST_SHARE_PUBLIC;
$_SERVER['HTTPS'] = 'on';

function bifrost_share_origin(): string {
    return 'https://' . BIFROST_SHARE_PUBLIC;
}

add_filter('option_home', fn($url) => preg_replace('#^https?://[^/]+#', bifrost_share_origin(), $url));
add_filter('option_siteurl', fn($url) => preg_replace('#^https?://[^/]+#', bifrost_share_origin(), $url));

// Rewrite local domain in all HTML output so absolute links don't escape the tunnel.
ob_start(function(string $html): string {
    return str_replace(BIFROST_SHARE_LOCAL, BIFROST_SHARE_PUBLIC, $html);
});
