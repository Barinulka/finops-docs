<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260702173825 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE audit_log (id UUID NOT NULL, entity_type VARCHAR(128) NOT NULL, entity_id VARCHAR(64) DEFAULT NULL, message VARCHAR(512) DEFAULT NULL, old_values JSON NOT NULL, new_values JSON NOT NULL, context JSON NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(512) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, action VARCHAR(32) NOT NULL, actor_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_audit_log_actor ON audit_log (actor_id)');
        $this->addSql('CREATE INDEX idx_audit_log_entity ON audit_log (entity_type, entity_id)');
        $this->addSql('CREATE INDEX idx_audit_log_action ON audit_log (action)');
        $this->addSql('CREATE INDEX idx_audit_log_created_at ON audit_log (created_at)');
        $this->addSql('ALTER TABLE audit_log ADD CONSTRAINT FK_F6E1C0F510DAF24A FOREIGN KEY (actor_id) REFERENCES "user" (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE audit_log DROP CONSTRAINT FK_F6E1C0F510DAF24A');
        $this->addSql('DROP TABLE audit_log');
    }
}
