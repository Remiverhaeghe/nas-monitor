<?php

namespace App\Tests\Support;

use Psr\Log\AbstractLogger;

/**
 * Logger de test : garde les messages et leurs contextes pour les comparer exactement.
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<mixed>}> */
    public array $records = [];

    /**
     * @param array<mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => (string) $level, 'message' => (string) $message, 'context' => $context];
    }

    /**
     * @return list<array{level: string, message: string, context: array<mixed>}>
     */
    public function withLevel(string $pLevel): array
    {
        $rRecords = array_values(array_filter($this->records, static fn (array $pRecord): bool => $pRecord['level'] === $pLevel));

        return $rRecords;
    }
}
