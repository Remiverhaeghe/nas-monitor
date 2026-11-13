<?php

namespace App\Check;

interface DnsResolver
{
    /**
     * Enregistrements A et AAAA d'un nom, liste vide si le nom ne se résout pas.
     *
     * @return list<array<string, mixed>>
     */
    public function lookup(string $pHost): array;
}
