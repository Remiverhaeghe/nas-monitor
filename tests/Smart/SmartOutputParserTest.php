<?php

namespace App\Tests\Smart;

use App\Smart\SmartDiskReport;
use App\Smart\SmartOutputParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Sorties de smartctl fictives, écrites à la main sur le modèle des formats SATA, NVMe et SAS.
 */
class SmartOutputParserTest extends TestCase
{
    /**
     * @return iterable<string, array{string, SmartDiskReport}>
     */
    public static function provideOutputs(): iterable
    {
        yield 'SATA sain' => ['sata-sain.txt', new SmartDiskReport(true, 37, 0, 0, 0)];
        yield 'SATA usé, température empaquetée' => ['sata-use.txt', new SmartDiskReport(true, 52, 24, 3, 1)];
        yield 'SATA en échec, température de flux d\'air' => ['sata-echec.txt', new SmartDiskReport(false, 41, 1720)];
        yield 'NVMe sain' => ['nvme.txt', new SmartDiskReport(true, 44, null, null, null, 0, 7)];
        yield 'NVMe avec avertissement critique' => ['nvme-alerte.txt', new SmartDiskReport(false, 71, null, null, null, 5, 96)];
        yield 'SAS' => ['sas.txt', new SmartDiskReport(true, 34, 2)];
        yield 'disque en veille' => ['veille.txt', new SmartDiskReport()];
    }

    #[DataProvider('provideOutputs')]
    public function testOutputIsParsed(string $pFile, SmartDiskReport $pExpected): void
    {
        $vReport = SmartOutputParser::parse((string) file_get_contents(__DIR__.'/fixtures/'.$pFile));

        self::assertEquals($pExpected, $vReport);
    }

    public function testTemperatureIsUnpackedOnlyAboveThePlainRange(): void
    {
        self::assertSame(150, SmartOutputParser::parse(self::temperatureLine(150))->temperatureC);
        self::assertSame(23, SmartOutputParser::parse(self::temperatureLine(0x1017))->temperatureC);
    }

    public function testSasHealthOtherThanOkIsAFailure(): void
    {
        self::assertFalse(SmartOutputParser::parse("SMART Health Status: FAILURE PREDICTION THRESHOLD EXCEEDED\n")->healthPassed);
    }

    public function testCriticalWarningAloneGivesNoHealth(): void
    {
        self::assertFalse(SmartOutputParser::parse("SMART overall-health self-assessment test result: PASSED\nCritical Warning:                   0x01\n")->healthPassed);
        self::assertNull(SmartOutputParser::parse("Critical Warning:                   0x02\n")->healthPassed);
    }

    private static function temperatureLine(int $pRawValue): string
    {
        $rLine = '194 Temperature_Celsius     0x0022   052   060   000    Old_age   Always       -       '.$pRawValue."\n";

        return $rLine;
    }
}
