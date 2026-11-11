<?php

namespace App\Tests\Dto;

use App\Dto\MaintenanceInput;
use App\Dto\MonitorInput;
use App\Entity\Monitor;
use App\Enum\MonitorType;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class InputValidationTest extends KernelTestCase
{
    private ValidatorInterface $vValidator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->vValidator = static::getContainer()->get('validator');
    }

    private function messages(object $pInput): string
    {
        $vMessages = [];
        foreach ($this->vValidator->validate($pInput) as $vViolation) {
            $vMessages[] = $vViolation->getMessage();
        }

        return implode(' | ', $vMessages);
    }

    public function testMaintenanceWithoutDatesIsLeftToOtherConstraints(): void
    {
        $vInput = new MaintenanceInput();

        self::assertStringNotContainsString('postérieure', $this->messages($vInput));
    }

    public function testMaintenanceCannotLastForever(): void
    {
        $vInput = new MaintenanceInput();
        $vInput->startsAt = new \DateTimeImmutable('2026-11-11 12:00:00');
        $vInput->endsAt = new \DateTimeImmutable('2030-11-11 12:00:00');

        self::assertStringContainsString('ne peut pas dépasser', $this->messages($vInput));
    }

    public function testMonitorWithChildrenCannotHaveAParent(): void
    {
        $vInput = new MonitorInput();
        $vInput->name = 'Enfant';
        $vInput->parent = new Monitor('Parent', MonitorType::Heartbeat, 3600, 600, new \DateTimeImmutable('2026-11-11 12:00:00'));
        $vInput->hasChildren = true;

        self::assertStringContainsString('dépendent de celui-ci', $this->messages($vInput));
    }

    /**
     * @return iterable<string, array{MonitorType, string, string}>
     */
    public static function invalidTargets(): iterable
    {
        yield 'tcp' => [MonitorType::Tcp, 'sans-port', 'hôte:port'];
        yield 'dns' => [MonitorType::Dns, 'pas un nom!', 'Nom attendu'];
        yield 'icmp' => [MonitorType::Icmp, 'http://x', 'Nom d\'hôte'];
    }

    #[DataProvider('invalidTargets')]
    public function testInvalidTargetsAreExplained(MonitorType $pType, string $pTarget, string $pExpected): void
    {
        $vInput = new MonitorInput();
        $vInput->name = 'Cible';
        $vInput->type = $pType;
        $vInput->target = $pTarget;

        self::assertStringContainsString($pExpected, $this->messages($vInput));
    }
}
