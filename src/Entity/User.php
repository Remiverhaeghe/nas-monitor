<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Scheb\TwoFactorBundle\Model\BackupCodeInterface;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[UniqueEntity('email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, TwoFactorInterface, BackupCodeInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email;

    /**
     * @var list<string>
     */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private string $password = '';

    /**
     * Chiffré par SecretCipher ; la valeur en clair n'existe qu'en mémoire, renseignée au chargement.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $totpSecret = null;

    private ?string $plainTotpSecret = null;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $backupCodeHashes = [];

    public function __construct(string $pEmail)
    {
        $this->email = mb_strtolower(trim($pEmail));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getUserIdentifier(): string
    {
        if ('' === $this->email) {
            throw new \LogicException('Utilisateur sans adresse e-mail.');
        }

        return $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $rRoles = array_values(array_unique([...$this->roles, 'ROLE_USER']));

        return $rRoles;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $pHashedPassword): void
    {
        $this->password = $pHashedPassword;
    }

    public function enableTotp(string $pPlainSecret, string $pEncryptedSecret): void
    {
        $this->plainTotpSecret = $pPlainSecret;
        $this->totpSecret = $pEncryptedSecret;
    }

    public function disableTotp(): void
    {
        $this->plainTotpSecret = null;
        $this->totpSecret = null;
        $this->backupCodeHashes = [];
    }

    public function getEncryptedTotpSecret(): ?string
    {
        return $this->totpSecret;
    }

    public function revealTotpSecret(string $pPlainSecret): void
    {
        $this->plainTotpSecret = $pPlainSecret;
    }

    /**
     * Seules les empreintes sont conservées : les codes en clair ne sont affichés qu'à leur création.
     *
     * @param list<string> $pCodes
     */
    public function replaceBackupCodes(array $pCodes): void
    {
        $this->backupCodeHashes = array_map(self::hashBackupCode(...), $pCodes);
    }

    public function countBackupCodes(): int
    {
        return \count($this->backupCodeHashes);
    }

    public function isBackupCode(string $pCode): bool
    {
        $rValid = \in_array(self::hashBackupCode($pCode), $this->backupCodeHashes, true);

        return $rValid;
    }

    public function invalidateBackupCode(string $pCode): void
    {
        $vHash = self::hashBackupCode($pCode);
        $this->backupCodeHashes = array_values(array_filter(
            $this->backupCodeHashes,
            static fn (string $pHash): bool => $pHash !== $vHash,
        ));
    }

    public function isTotpAuthenticationEnabled(): bool
    {
        return null !== $this->totpSecret;
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->email;
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        if (null === $this->totpSecret) {
            return null;
        }
        if (null === $this->plainTotpSecret) {
            // Secret illisible (clé de chiffrement changée) : la connexion échoue plutôt que de contourner le second facteur
            throw new \LogicException('Secret TOTP non déchiffré pour '.$this->email);
        }

        $rConfiguration = new TotpConfiguration($this->plainTotpSecret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);

        return $rConfiguration;
    }

    /**
     * Les codes de 64 bits rendent une empreinte SHA-256 sans sel suffisante, comme pour les jetons.
     */
    private static function hashBackupCode(string $pCode): string
    {
        $rHash = hash('sha256', strtolower(str_replace(['-', ' '], '', $pCode)));

        return $rHash;
    }
}
