<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220615085431 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE last_high (id INT AUTO_INCREMENT NOT NULL, daily_cac_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_672E2009AA5DF1C9 (daily_cac_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE last_high ADD CONSTRAINT FK_672E2009AA5DF1C9 FOREIGN KEY (daily_cac_id) REFERENCES cac (id)');
        $this->addSql('ALTER TABLE user ADD higher_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D6496D44FFD6 FOREIGN KEY (higher_id) REFERENCES last_high (id)');
        $this->addSql('CREATE INDEX IDX_8D93D6496D44FFD6 ON user (higher_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D6496D44FFD6');
        $this->addSql('DROP TABLE last_high');
        $this->addSql('DROP INDEX IDX_8D93D6496D44FFD6 ON user');
        $this->addSql('ALTER TABLE user DROP higher_id');
    }
}
