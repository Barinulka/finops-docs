<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260701194547 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE client (id UUID NOT NULL, name VARCHAR(255) NOT NULL, legal_name VARCHAR(255) DEFAULT NULL, tax_id VARCHAR(64) DEFAULT NULL, registration_number VARCHAR(64) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, phone VARCHAR(64) DEFAULT NULL, notes TEXT DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_client_name ON client (name)');
        $this->addSql('CREATE INDEX idx_client_tax_id ON client (tax_id)');
        $this->addSql('CREATE INDEX idx_client_registration_number ON client (registration_number)');
        $this->addSql('CREATE INDEX idx_client_email ON client (email)');
        $this->addSql('CREATE INDEX idx_client_is_active_name ON client (is_active, name)');
        $this->addSql('CREATE TABLE document (id UUID NOT NULL, original_filename VARCHAR(255) NOT NULL, storage_path VARCHAR(512) NOT NULL, mime_type VARCHAR(128) NOT NULL, size_bytes INT NOT NULL, checksum_sha256 VARCHAR(64) DEFAULT NULL, parser_version VARCHAR(64) DEFAULT NULL, parse_error TEXT DEFAULT NULL, queued_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, parsed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, confirmed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(32) NOT NULL, type VARCHAR(64) NOT NULL, client_id UUID NOT NULL, uploaded_by_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_document_client ON document (client_id)');
        $this->addSql('CREATE INDEX idx_document_uploaded_by ON document (uploaded_by_id)');
        $this->addSql('CREATE INDEX idx_document_status ON document (status)');
        $this->addSql('CREATE INDEX idx_document_type ON document (type)');
        $this->addSql('CREATE INDEX idx_document_created_at ON document (created_at)');
        $this->addSql('CREATE INDEX idx_document_checksum_sha256 ON document (checksum_sha256)');
        $this->addSql('CREATE TABLE parsed_document (id UUID NOT NULL, confidence DOUBLE PRECISION DEFAULT NULL, fields JSON NOT NULL, raw_payload JSON NOT NULL, raw_text TEXT DEFAULT NULL, warnings JSON NOT NULL, reviewed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, document_type VARCHAR(64) NOT NULL, document_id UUID NOT NULL, reviewed_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A00E6817C33F7837 ON parsed_document (document_id)');
        $this->addSql('CREATE INDEX idx_parsed_document_document_type ON parsed_document (document_type)');
        $this->addSql('CREATE INDEX idx_parsed_document_reviewed_by ON parsed_document (reviewed_by_id)');
        $this->addSql('CREATE INDEX idx_parsed_document_reviewed_at ON parsed_document (reviewed_at)');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A7619EB6921 FOREIGN KEY (client_id) REFERENCES client (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76A2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE parsed_document ADD CONSTRAINT FK_A00E6817C33F7837 FOREIGN KEY (document_id) REFERENCES document (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE parsed_document ADD CONSTRAINT FK_A00E6817FC6B21F1 FOREIGN KEY (reviewed_by_id) REFERENCES "user" (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE document DROP CONSTRAINT FK_D8698A7619EB6921');
        $this->addSql('ALTER TABLE document DROP CONSTRAINT FK_D8698A76A2B28FE8');
        $this->addSql('ALTER TABLE parsed_document DROP CONSTRAINT FK_A00E6817C33F7837');
        $this->addSql('ALTER TABLE parsed_document DROP CONSTRAINT FK_A00E6817FC6B21F1');
        $this->addSql('DROP TABLE client');
        $this->addSql('DROP TABLE document');
        $this->addSql('DROP TABLE parsed_document');
    }
}
