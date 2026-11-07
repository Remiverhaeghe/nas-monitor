<?php

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final class PingPayload
{
    #[SerializedName('duration_ms')]
    #[Assert\PositiveOrZero]
    public ?int $durationMs = null;

    #[SerializedName('size_bytes')]
    #[Assert\PositiveOrZero]
    public ?int $sizeBytes = null;

    public ?string $message = null;
}
