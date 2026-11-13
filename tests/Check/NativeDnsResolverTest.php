<?php

namespace App\Tests\Check;

use App\Check\NativeDnsResolver;
use PHPUnit\Framework\TestCase;

class NativeDnsResolverTest extends TestCase
{
    public function testLookupAsksForIpv4AndIpv6RecordsAndReturnsAList(): void
    {
        $vCalls = [];
        $vResolver = new NativeDnsResolver(static function (string $pHost, int $pType) use (&$vCalls): array {
            $vCalls[] = [$pHost, $pType];

            return [3 => ['ip' => '1.2.3.4'], 7 => ['ipv6' => '::1']];
        });

        self::assertSame([['ip' => '1.2.3.4'], ['ipv6' => '::1']], $vResolver->lookup('nas.exemple.fr'));
        self::assertSame([['nas.exemple.fr', \DNS_A | \DNS_AAAA]], $vCalls);
    }

    public function testFailedLookupGivesAnEmptyList(): void
    {
        self::assertSame([], (new NativeDnsResolver(static fn (): bool => false))->lookup('introuvable.invalid'));
        self::assertSame([], (new NativeDnsResolver(static fn (): array => []))->lookup('vide.invalid'));
    }
}
