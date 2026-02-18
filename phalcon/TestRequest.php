<?php

declare(strict_types=1);

namespace App;

use Exception;
use Phalcon\Filter\Filter;

use function container;

/**
 * The user provided trusted header takes priority before checking X-Forwarded_for header.
 *
 * Using trusted proxies list
 * ```
 * $request
 *     ->setTrustedProxies($trustedProxies)
 *     ->getClientAddress(true);
 * ```
 * Using user provided trusted header, header should only ever contain 1 IP address
 * ```
 * $request
 *     ->setTrustedProxyHeader('HTTP_CLIENT_IP')
 *     ->getClientAddress(true);
 * ```
 */
class TestRequest
{
    /**
     * @var array
     */
    protected array $trustedProxies = [];
    /**
     * @var string
     */
    protected string $trustedProxyHeader = '';

    /**
     * Get trusted proxies.
     *
     * @return array
     */
    public function getTrustedProxies(): array
    {
        return $this->trustedProxies;
    }

    /**
     * Set a trusted proxy list for X-Forwarded-For header.
     *
     * @param  array                     $trustedProxies
     * @return TestRequest
     * @throws \Phalcon\Filter\Exception
     */
    public function setTrustedProxies(array $trustedProxies): static
    {
        $filterService = $this->getFilterService();

        // sanitize IPs
        foreach ($trustedProxies as $trustedProxy) {
            $filtered = $filterService->sanitize($trustedProxy, 'ip');
            if ($filtered !== false) {
                $this->trustedProxies[] = $filtered;
            }
        }
        return $this;
    }

    /**
     * This header takes priority when parsing HTTP headers
     * The header should only contain 1 single IP to be returned eg. HTTP_CLIENT_IP.
     *
     * @param  string $trustedProxyHeader
     * @return $this
     */
    public function setTrustedProxyHeader(string $trustedProxyHeader): static
    {
        $this->trustedProxyHeader = strtoupper(str_replace('-', '_', $trustedProxyHeader));
        if (!str_starts_with($this->trustedProxyHeader, 'HTTP_')) {
            $this->trustedProxyHeader = 'HTTP_' . $this->trustedProxyHeader;
        }

        return $this;
    }

    /**
     * @param  bool                      $trustForwardedHeader
     * @return string|bool
     * @throws \Phalcon\Filter\Exception
     */
    public function getClientAddress(bool $trustForwardedHeader = false): string | bool
    {
        $address = $_SERVER['REMOTE_ADDR'] ?? false;

        // if trust forwarded header is true, meaning the $address is deemed a proxy IP, or we get it from a trusted header
        if ($trustForwardedHeader) {
            // 1. If trustedProxyHeader is not empty, it takes priority before we check for X-Forwarded-For
            if ($this->trustedProxyHeader !== '') {
                $trustedProxyHeaderIp = $_SERVER[$this->trustedProxyHeader] ?? false;
                if ($trustedProxyHeaderIp) {
                    return $trustedProxyHeaderIp;
                }
            }

            // 2. if $this->trustedProxies is not empty, we verify if the remote_addr is a trusted proxy,
            // if not, we don't parse the proxy header, and return back the remote_addr
            if (!empty($this->trustedProxies) && !$this->isProxyTrusted($address)) {
                return $address;
            }

            // 3. if either trustedProxies are empty or we trust the proxy, parse the header, default HTTP_X_FORWARDED_FOR
            $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;
            if (!empty($forwarded)) {
                /**
                 * The client address has multiples parts,
                 * only return the first non-private/non-reserved IP.
                 */
                $forwardedIps        = array_map('trim', explode(',', $forwarded));
                $reverseForwardedIps = array_reverse($forwardedIps);
                // '192.168.1.30, 1.15.134.14, 104.21.31.81' becomes '104.21.31.81, 1.15.134.14, 192.168.1.30'
                foreach ($reverseForwardedIps as $forwardedIp) {
                    // skip if the IP is one of our own trusted proxy
                    if ($this->isProxyTrusted($forwardedIp)) {
                        continue;
                    }
                    // Validate that it is a public, non-reserved IP
                    $filteredIp = $this->isValidPublicIp($forwardedIp);
                    if ($filteredIp) {
                        return $filteredIp;
                    }
                }
            }
        }

        return $address;
    }

    /**
     * Check if an IP address exists in CIDR range.
     *
     * @param  string $ip   the IP address to check
     * @param  string $cidr the CIDR range to compare against
     * @return bool   true if the IP is in range, false otherwise
     */
    protected function isIpAddressInCIDR(string $ip, string $cidr): bool
    {
        $parts      = explode('/', $cidr);
        $subnet     = $parts[0];
        $maskLength = $parts[1];

        $ipBin     = inet_pton($ip);
        $subnetBin = inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false; // Invalid IP
        }

        $ipBits     = unpack('H*', $ipBin);
        $subnetBits = unpack('H*', $subnetBin);

        $ipBits     = $ipBits[1];
        $subnetBits = $subnetBits[1];

        // Convert hex string to binary string
        $ipBits     = hex2bin(str_pad($ipBits, strlen($ipBits), '0'));
        $subnetBits = hex2bin(str_pad($subnetBits, strlen($subnetBits), '0'));

        $maskBytes     = (int)floor($maskLength / 8);
        $remainingBits = $maskLength % 8;

        // Compare full bytes
        if (strncmp($ipBits, $subnetBits, $maskBytes) !== 0) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $ipByte     = ord($ipBits[$maskBytes]);
        $subnetByte = ord($subnetBits[$maskBytes]);

        $tempMask = (1 << (8 - $remainingBits)) - 1;
        $mask     = 0xFF ^ $tempMask;

        return ($ipByte & $mask) === ($subnetByte & $mask);
    }

    private function isProxyTrusted(string $ip): bool
    {
        foreach ($this->trustedProxies as $trusted) {
            if (strpos($trusted, '/') !== false) {
                return $this->isIpAddressInCIDR($ip, $trusted);
            } else {
                return $ip === $trusted;
            }
        }

        return false;
    }

    /**
     * @param  string                    $forwardedIp
     * @return string|bool
     * @throws \Phalcon\Filter\Exception
     */
    private function isValidPublicIp(string $forwardedIp): string|bool
    {
        // retrieve the first valid IP that is not reserved or private
        $filterService = $this->getFilterService();
        $filtered      = $filterService->sanitize($forwardedIp, [
            'ip' => [
                'filter' => FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            ],
        ]);

        return $filtered;
    }

    /**
     * @return Filter
     */
    private function getFilterService(): Filter
    {
        return container()->get('filter');
    }
}
