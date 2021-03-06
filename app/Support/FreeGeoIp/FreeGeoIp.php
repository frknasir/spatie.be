<?php

namespace App\Support\FreeGeoIp;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FreeGeoIp
{
    public static function getCountryCodeForIp(string $ip): string
    {
        return Cache::remember("countryCodeIp{$ip}", 60 * 5, function () use ($ip) {
            $response = Http::get("https://freegeoip.app/json/{$ip}");

            if ($response->status() !== 200) {
                return 'US';
            }

            $countryCode = $response->json('country_code', 'US');

            if (empty($countryCode)) {
                return 'US';
            }

            return $countryCode;
        });
    }
}
