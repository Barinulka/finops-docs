<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260712081139 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE google_sheet_append_log (id UUID NOT NULL, spreadsheet_id VARCHAR(255) NOT NULL, sheet_name VARCHAR(255) NOT NULL, appended_range VARCHAR(255) DEFAULT NULL, payload JSON NOT NULL, status VARCHAR(32) NOT NULL, error_message TEXT DEFAULT NULL, written_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, telegram_document_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_google_sheet_append_log_document ON google_sheet_append_log (telegram_document_id)');
        $this->addSql('CREATE INDEX idx_google_sheet_append_log_status ON google_sheet_append_log (status)');
        $this->addSql('CREATE INDEX idx_google_sheet_append_log_created_at ON google_sheet_append_log (created_at)');
        $this->addSql('CREATE TABLE telegram_document (id UUID NOT NULL, chat_id BIGINT NOT NULL, message_id BIGINT DEFAULT NULL, telegram_file_id VARCHAR(255) DEFAULT NULL, telegram_file_unique_id VARCHAR(255) DEFAULT NULL, original_filename VARCHAR(255) DEFAULT NULL, mime_type VARCHAR(128) DEFAULT NULL, size_bytes INT DEFAULT NULL, checksum_sha256 VARCHAR(64) DEFAULT NULL, status VARCHAR(32) NOT NULL, source VARCHAR(32) NOT NULL, parser_confidence DOUBLE PRECISION DEFAULT NULL, auto_write_allowed BOOLEAN DEFAULT NULL, parsed_fields JSON NOT NULL, validation_errors JSON NOT NULL, parser_warnings JSON NOT NULL, raw_text TEXT DEFAULT NULL, error_message TEXT DEFAULT NULL, received_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, queued_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, parsed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, written_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, failed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, telegram_user_id UUID NOT NULL, document_id UUID DEFAULT NULL, duplicate_of_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8CEC9B3EC33F7837 ON telegram_document (document_id)');
        $this->addSql('CREATE INDEX IDX_8CEC9B3E2CC33300 ON telegram_document (duplicate_of_id)');
        $this->addSql('CREATE INDEX idx_telegram_document_user ON telegram_document (telegram_user_id)');
        $this->addSql('CREATE INDEX idx_telegram_document_status ON telegram_document (status)');
        $this->addSql('CREATE INDEX idx_telegram_document_source ON telegram_document (source)');
        $this->addSql('CREATE INDEX idx_telegram_document_chat_id ON telegram_document (chat_id)');
        $this->addSql('CREATE INDEX idx_telegram_document_checksum_sha256 ON telegram_document (checksum_sha256)');
        $this->addSql('CREATE INDEX idx_telegram_document_created_at ON telegram_document (created_at)');
        $this->addSql('CREATE TABLE telegram_message_log (id UUID NOT NULL, chat_id BIGINT NOT NULL, message_id BIGINT DEFAULT NULL, direction VARCHAR(32) NOT NULL, status VARCHAR(32) NOT NULL, text TEXT DEFAULT NULL, payload JSON NOT NULL, response_payload JSON NOT NULL, error_message TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, telegram_user_id UUID DEFAULT NULL, telegram_document_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_telegram_message_log_user ON telegram_message_log (telegram_user_id)');
        $this->addSql('CREATE INDEX idx_telegram_message_log_document ON telegram_message_log (telegram_document_id)');
        $this->addSql('CREATE INDEX idx_telegram_message_log_chat_id ON telegram_message_log (chat_id)');
        $this->addSql('CREATE INDEX idx_telegram_message_log_status ON telegram_message_log (status)');
        $this->addSql('CREATE INDEX idx_telegram_message_log_created_at ON telegram_message_log (created_at)');
        $this->addSql('CREATE TABLE telegram_user (id UUID NOT NULL, telegram_id BIGINT NOT NULL, username VARCHAR(255) DEFAULT NULL, first_name VARCHAR(255) DEFAULT NULL, last_name VARCHAR(255) DEFAULT NULL, language_code VARCHAR(16) DEFAULT NULL, is_active BOOLEAN NOT NULL, role VARCHAR(32) NOT NULL, last_seen_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, linked_user_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_F180F059CC26EB02 ON telegram_user (linked_user_id)');
        $this->addSql('CREATE INDEX idx_telegram_user_username ON telegram_user (username)');
        $this->addSql('CREATE INDEX idx_telegram_user_is_active ON telegram_user (is_active)');
        $this->addSql('CREATE INDEX idx_telegram_user_role ON telegram_user (role)');
        $this->addSql('CREATE UNIQUE INDEX uniq_telegram_user_telegram_id ON telegram_user (telegram_id)');
        $this->addSql('ALTER TABLE google_sheet_append_log ADD CONSTRAINT FK_D255871D73BDF0A4 FOREIGN KEY (telegram_document_id) REFERENCES telegram_document (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE telegram_document ADD CONSTRAINT FK_8CEC9B3EFC28B263 FOREIGN KEY (telegram_user_id) REFERENCES telegram_user (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE telegram_document ADD CONSTRAINT FK_8CEC9B3EC33F7837 FOREIGN KEY (document_id) REFERENCES document (id)');
        $this->addSql('ALTER TABLE telegram_document ADD CONSTRAINT FK_8CEC9B3E2CC33300 FOREIGN KEY (duplicate_of_id) REFERENCES telegram_document (id)');
        $this->addSql('ALTER TABLE telegram_message_log ADD CONSTRAINT FK_16886ABAFC28B263 FOREIGN KEY (telegram_user_id) REFERENCES telegram_user (id)');
        $this->addSql('ALTER TABLE telegram_message_log ADD CONSTRAINT FK_16886ABA73BDF0A4 FOREIGN KEY (telegram_document_id) REFERENCES telegram_document (id)');
        $this->addSql('ALTER TABLE telegram_user ADD CONSTRAINT FK_F180F059CC26EB02 FOREIGN KEY (linked_user_id) REFERENCES "user" (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE google_sheet_append_log DROP CONSTRAINT FK_D255871D73BDF0A4');
        $this->addSql('ALTER TABLE telegram_document DROP CONSTRAINT FK_8CEC9B3EFC28B263');
        $this->addSql('ALTER TABLE telegram_document DROP CONSTRAINT FK_8CEC9B3EC33F7837');
        $this->addSql('ALTER TABLE telegram_document DROP CONSTRAINT FK_8CEC9B3E2CC33300');
        $this->addSql('ALTER TABLE telegram_message_log DROP CONSTRAINT FK_16886ABAFC28B263');
        $this->addSql('ALTER TABLE telegram_message_log DROP CONSTRAINT FK_16886ABA73BDF0A4');
        $this->addSql('ALTER TABLE telegram_user DROP CONSTRAINT FK_F180F059CC26EB02');
        $this->addSql('DROP TABLE google_sheet_append_log');
        $this->addSql('DROP TABLE telegram_document');
        $this->addSql('DROP TABLE telegram_message_log');
        $this->addSql('DROP TABLE telegram_user');
    }
}
