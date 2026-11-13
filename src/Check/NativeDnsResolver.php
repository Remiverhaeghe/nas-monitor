<?php

namespace App\Check;

final class NativeDnsResolver implements DnsResolver
{
    private readonly \Closure $aLookup;

    /**
     * @param \Closure(string, int): (array<int, array<string, mixed>>|false)|null $pLookup remplace dns_get_record (tests)
     */
    public function __construct(?\Closure $pLookup = null)
    {
        $this->aLookup = $pLookup ?? dns_get_record(...);
    }

    public function lookup(string $pHost): array
    {
        $rRecords = array_values(@($this->aLookup)($pHost, \DNS_A | \DNS_AAAA) ?: []);

        return $rRecords;
    }
}
