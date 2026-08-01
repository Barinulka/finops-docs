<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260801111118 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_sheet_document_checksum_sha256');
        $this->addSql('CREATE UNIQUE INDEX uniq_sheet_document_checksum_sha256 ON sheet_document (checksum_sha256)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_sheet_document_checksum_sha256');
        $this->addSql('CREATE INDEX idx_sheet_document_checksum_sha256 ON sheet_document (checksum_sha256)');
    }
}
