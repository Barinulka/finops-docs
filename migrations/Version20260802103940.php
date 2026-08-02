<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260802103940 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE google_sheet_append_log ADD sheet_document_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE google_sheet_append_log ADD requested_by_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE google_sheet_append_log ALTER telegram_document_id DROP NOT NULL');
        $this->addSql('ALTER TABLE google_sheet_append_log ADD CONSTRAINT FK_D255871D514E761A FOREIGN KEY (sheet_document_id) REFERENCES sheet_document (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE google_sheet_append_log ADD CONSTRAINT FK_D255871D4DA1E751 FOREIGN KEY (requested_by_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX idx_google_sheet_append_log_sheet_document ON google_sheet_append_log (sheet_document_id)');
        $this->addSql('CREATE INDEX idx_google_sheet_append_log_requested_by ON google_sheet_append_log (requested_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE google_sheet_append_log DROP CONSTRAINT FK_D255871D514E761A');
        $this->addSql('ALTER TABLE google_sheet_append_log DROP CONSTRAINT FK_D255871D4DA1E751');
        $this->addSql('DROP INDEX idx_google_sheet_append_log_sheet_document');
        $this->addSql('DROP INDEX idx_google_sheet_append_log_requested_by');
        $this->addSql('ALTER TABLE google_sheet_append_log DROP sheet_document_id');
        $this->addSql('ALTER TABLE google_sheet_append_log DROP requested_by_id');
        $this->addSql('ALTER TABLE google_sheet_append_log ALTER telegram_document_id SET NOT NULL');
    }
}
