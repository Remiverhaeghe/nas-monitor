<?php

namespace App\Tests\Security;

use App\Security\BackupCodeGenerator;
use PHPUnit\Framework\TestCase;

class BackupCodeGeneratorTest extends TestCase
{
    public function testGeneratesTenDistinctCodesInExpectedFormat(): void
    {
        $vCodes = BackupCodeGenerator::generate();

        self::assertCount(BackupCodeGenerator::COUNT, $vCodes);
        self::assertCount(BackupCodeGenerator::COUNT, array_unique($vCodes));
        foreach ($vCodes as $vCode) {
            self::assertMatchesRegularExpression('/^[0-9a-f]{4}(-[0-9a-f]{4}){3}$/', $vCode);
        }
    }
}
