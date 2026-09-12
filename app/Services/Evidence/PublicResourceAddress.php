<?php

namespace App\Services\Evidence;

use DomainException;

class PublicResourceAddress
{
    public function resolve(string $host): string
    {
        $host = trim($host, '[]');
        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host]
            : array_merge(array_column(dns_get_record($host, DNS_A) ?: [], 'ip'),
                array_column(dns_get_record($host, DNS_AAAA) ?: [], 'ipv6'));
        if ($addresses === [] || collect($addresses)->contains(fn ($ip) => ! $this->isPublic($ip))) {
            throw new DomainException('Hyperlink verification requires a public Internet address.');
        }

        return $addresses[0];
    }

    public function isPublic(string $ip): bool
    {
        // Reject mapped IPv4 and special IPv6 ranges by admitting global unicast only.
        if (str_contains($ip, ':') && ! preg_match('/^[23][0-9a-f]{3}:/i', $ip)) {
            return false;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) !== false;
    }
}
