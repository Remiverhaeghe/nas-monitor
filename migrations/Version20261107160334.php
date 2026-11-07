<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Security\SecretCipher;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261107160334 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_user ADD backup_code_hashes JSON DEFAULT \'[]\' NOT NULL');
        $this->addSql('ALTER TABLE app_user ALTER totp_secret TYPE VARCHAR(255)');

        // Secrets enregistrés en clair avant le chiffrement
        $vRows = $this->connection->fetchAllAssociative("SELECT id, totp_secret FROM app_user WHERE totp_secret IS NOT NULL AND totp_secret NOT LIKE 'v1:%'");
        foreach ($vRows as $vRow) {
            $this->addSql('UPDATE app_user SET totp_secret = ? WHERE id = ?', [$this->cipher()->encrypt((string) $vRow['totp_secret']), $vRow['id']]);
        }
    }

    public function down(Schema $schema): void
    {
        $vRows = $this->connection->fetchAllAssociative("SELECT id, totp_secret FROM app_user WHERE totp_secret LIKE 'v1:%'");
        foreach ($vRows as $vRow) {
            $this->addSql('UPDATE app_user SET totp_secret = ? WHERE id = ?', [$this->cipher()->decrypt((string) $vRow['totp_secret']), $vRow['id']]);
        }

        $this->addSql('ALTER TABLE app_user DROP backup_code_hashes');
        $this->addSql('ALTER TABLE app_user ALTER totp_secret TYPE VARCHAR(100)');
    }

    private function cipher(): SecretCipher
    {
        $rCipher = new SecretCipher((string) ($_SERVER['TOTP_ENCRYPTION_KEY'] ?? $_ENV['TOTP_ENCRYPTION_KEY'] ?? ''));

        return $rCipher;
    }
}
