<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220614171520 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE last_high ADD daily_higher_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE last_high ADD CONSTRAINT FK_672E2009EC40E6CC FOREIGN KEY (daily_higher_id) REFERENCES cac (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_672E2009EC40E6CC ON last_high (daily_higher_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE last_high DROP FOREIGN KEY FK_672E2009EC40E6CC');
        $this->addSql('DROP INDEX UNIQ_672E2009EC40E6CC ON last_high');
        $this->addSql('ALTER TABLE last_high DROP daily_higher_id');
    }
}
