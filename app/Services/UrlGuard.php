<?php

namespace App\Services;

/** SSRF protection: users give us URLs and we fetch them from our server. */
class UrlGuard
{
    public static function safe(string $url): bool
    {
        $p = parse_url($url);
        if (! $p || empty($p['host']) || ! in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true)) {
            return false;
        }
        if (isset($p['port']) && ! in_array((int) $p['port'], [80, 443], true)) {
            return false;
        }
        if (isset($p['user']) || isset($p['pass'])) {
            return false;
        }

        $host = $p['host'];
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if (! $ips) {
            return false;
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }
        return true;
    }
}
