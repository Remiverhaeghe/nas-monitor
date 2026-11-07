<?php

namespace App\Security;

final class BackupCodeGenerator
{
    public const COUNT = 10;

    /**
     * Codes de 64 bits au format abcd-ef01-2345-6789.
     *
     * @return list<string>
     */
    public static function generate(): array
    {
        $rCodes = [];
        for ($vIndex = 0; $vIndex < self::COUNT; ++$vIndex) {
            $rCodes[] = implode('-', str_split(bin2hex(random_bytes(8)), 4));
        }

        return $rCodes;
    }
}
