<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260702172909 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE operation (id UUID NOT NULL, status VARCHAR(32) NOT NULL, type VARCHAR(32) NOT NULL, operation_date DATE DEFAULT NULL, external_reference VARCHAR(128) DEFAULT NULL, contract_number VARCHAR(128) DEFAULT NULL, purpose TEXT DEFAULT NULL, payment_amount NUMERIC(20, 6) DEFAULT NULL, payment_currency VARCHAR(3) DEFAULT NULL, exchange_rate NUMERIC(20, 8) DEFAULT NULL, exchange_rate_raw VARCHAR(255) DEFAULT NULL, agency_fee_amount_rub NUMERIC(20, 2) DEFAULT NULL, total_amount_rub NUMERIC(20, 2) DEFAULT NULL, execution_term_raw VARCHAR(255) DEFAULT NULL, execution_due_date DATE DEFAULT NULL, beneficiary_name VARCHAR(255) DEFAULT NULL, beneficiary_bank_name VARCHAR(255) DEFAULT NULL, beneficiary_swift VARCHAR(32) DEFAULT NULL, beneficiary_account VARCHAR(128) DEFAULT NULL, beneficiary_raw_details TEXT DEFAULT NULL, metadata JSON NOT NULL, confirmed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, client_id UUID NOT NULL, source_document_id UUID DEFAULT NULL, created_by_id UUID NOT NULL, confirmed_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_operation_client ON operation (client_id)');
        $this->addSql('CREATE INDEX idx_operation_source_document ON operation (source_document_id)');
        $this->addSql('CREATE INDEX idx_operation_created_by ON operation (created_by_id)');
        $this->addSql('CREATE INDEX idx_operation_confirmed_by ON operation (confirmed_by_id)');
        $this->addSql('CREATE INDEX idx_operation_status ON operation (status)');
        $this->addSql('CREATE INDEX idx_operation_type ON operation (type)');
        $this->addSql('CREATE INDEX idx_operation_operation_date ON operation (operation_date)');
        $this->addSql('CREATE INDEX idx_operation_external_reference ON operation (external_reference)');
        $this->addSql('CREATE INDEX idx_operation_created_at ON operation (created_at)');
        $this->addSql('ALTER TABLE operation ADD CONSTRAINT FK_1981A66D19EB6921 FOREIGN KEY (client_id) REFERENCES client (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE operation ADD CONSTRAINT FK_1981A66DFF402897 FOREIGN KEY (source_document_id) REFERENCES document (id)');
        $this->addSql('ALTER TABLE operation ADD CONSTRAINT FK_1981A66DB03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE operation ADD CONSTRAINT FK_1981A66D6F45385D FOREIGN KEY (confirmed_by_id) REFERENCES "user" (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE operation DROP CONSTRAINT FK_1981A66D19EB6921');
        $this->addSql('ALTER TABLE operation DROP CONSTRAINT FK_1981A66DFF402897');
        $this->addSql('ALTER TABLE operation DROP CONSTRAINT FK_1981A66DB03A8386');
        $this->addSql('ALTER TABLE operation DROP CONSTRAINT FK_1981A66D6F45385D');
        $this->addSql('DROP TABLE operation');
    }
}
