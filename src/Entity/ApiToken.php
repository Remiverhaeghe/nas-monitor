<?php

namespace App\Entity;

use App\Repository\ApiTokenRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ApiTokenRepository::class)]
class ApiToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    public function __construct(User $pUser, string $pName, string $pTokenHash, \DateTimeImmutable $pCreatedAt)
    {
        $this->user = $pUser;
        $this->name = $pName;
        $this->tokenHash = $pTokenHash;
        $this->createdAt = $pCreatedAt;
    }

    public static function hashToken(string $pToken): string
    {
        $rHash = hash('sha256', $pToken);

        return $rHash;
    }

    /**
     * Retourne le jeton en clair et l'entité ; seule l'empreinte est conservée.
     *
     * @return array{string, self}
     */
    public static function generate(User $pUser, string $pName, \DateTimeImmutable $pNow): array
    {
        $vToken = 'nm_'.bin2hex(random_bytes(32));
        $rGenerated = [$vToken, new self($pUser, $pName, self::hashToken($vToken), $pNow)];

        return $rGenerated;
    }

    public function markUsed(\DateTimeImmutable $pNow): void
    {
        $this->lastUsedAt = $pNow;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }
}
