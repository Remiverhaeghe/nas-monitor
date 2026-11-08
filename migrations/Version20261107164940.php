<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261107164940 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE monitor ADD public_visible BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE monitor ADD metric_limits JSON DEFAULT \'[]\' NOT NULL');
        $this->addSql('ALTER TABLE monitor ADD parent_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE monitor ADD CONSTRAINT FK_E1159985727ACA70 FOREIGN KEY (parent_id) REFERENCES monitor (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_E1159985727ACA70 ON monitor (parent_id)');
        $this->addSql('ALTER TABLE ping ADD metrics JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE monitor DROP CONSTRAINT FK_E1159985727ACA70');
        $this->addSql('DROP INDEX IDX_E1159985727ACA70');
        $this->addSql('ALTER TABLE monitor DROP public_visible');
        $this->addSql('ALTER TABLE monitor DROP metric_limits');
        $this->addSql('ALTER TABLE monitor DROP parent_id');
        $this->addSql('ALTER TABLE ping DROP metrics');
    }
}
