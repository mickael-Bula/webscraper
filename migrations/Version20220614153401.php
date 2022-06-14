<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220614153401 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE last_high ADD user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE last_high ADD CONSTRAINT FK_672E2009A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_672E2009A76ED395 ON last_high (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE last_high DROP FOREIGN KEY FK_672E2009A76ED395');
        $this->addSql('DROP INDEX IDX_672E2009A76ED395 ON last_high');
        $this->addSql('ALTER TABLE last_high DROP user_id');
    }
}
