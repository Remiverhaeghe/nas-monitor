<?php

namespace App\Check;

final class CheckResult
{
    public function __construct(
        public readonly bool $success,
        public readonly int $durationMs,
        public readonly string $message,
    ) {
    }
}
