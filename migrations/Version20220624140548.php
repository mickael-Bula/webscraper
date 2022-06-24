<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220624140548 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE last_high ADD daily_lvc_id INT DEFAULT NULL, ADD lvc_higher DOUBLE PRECISION NOT NULL, ADD lvc_buy_limit DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE last_high ADD CONSTRAINT FK_672E200989CB088E FOREIGN KEY (daily_lvc_id) REFERENCES lvc (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_672E200989CB088E ON last_high (daily_lvc_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE last_high DROP FOREIGN KEY FK_672E200989CB088E');
        $this->addSql('DROP INDEX UNIQ_672E200989CB088E ON last_high');
        $this->addSql('ALTER TABLE last_high DROP daily_lvc_id, DROP lvc_higher, DROP lvc_buy_limit');
    }
}
