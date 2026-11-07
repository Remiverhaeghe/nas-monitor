<?php

namespace App\Notification;

final class NtfyMessage
{
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly bool $urgent,
    ) {
    }
}
