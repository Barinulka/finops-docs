<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260730154007 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE sheet_document (id UUID NOT NULL, original_filename VARCHAR(255) NOT NULL, storage_path VARCHAR(512) NOT NULL, mime_type VARCHAR(128) NOT NULL, size_bytes INT NOT NULL, checksum_sha256 VARCHAR(64) DEFAULT NULL, status VARCHAR(32) NOT NULL, parser_version VARCHAR(64) DEFAULT NULL, parser_confidence DOUBLE PRECISION DEFAULT NULL, parsed_fields JSON NOT NULL, parser_warnings JSON NOT NULL, validation_errors JSON NOT NULL, raw_text TEXT DEFAULT NULL, error_message TEXT DEFAULT NULL, uploaded_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, queued_for_parsing_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, parsed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, queued_for_write_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, written_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, failed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, uploaded_by_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_sheet_document_uploaded_by ON sheet_document (uploaded_by_id)');
        $this->addSql('CREATE INDEX idx_sheet_document_status ON sheet_document (status)');
        $this->addSql('CREATE INDEX idx_sheet_document_checksum_sha256 ON sheet_document (checksum_sha256)');
        $this->addSql('CREATE INDEX idx_sheet_document_uploaded_at ON sheet_document (uploaded_at)');
        $this->addSql('ALTER TABLE sheet_document ADD CONSTRAINT FK_6D353952A2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES "user" (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sheet_document DROP CONSTRAINT FK_6D353952A2B28FE8');
        $this->addSql('DROP TABLE sheet_document');
    }
}
