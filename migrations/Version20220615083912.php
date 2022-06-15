<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220615083912 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE last_high');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE last_high (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, daily_higher_id INT DEFAULT NULL, higher DOUBLE PRECISION NOT NULL, buy_limit DOUBLE PRECISION NOT NULL, INDEX IDX_672E2009A76ED395 (user_id), UNIQUE INDEX UNIQ_672E2009EC40E6CC (daily_higher_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE last_high ADD CONSTRAINT FK_672E2009A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE last_high ADD CONSTRAINT FK_672E2009EC40E6CC FOREIGN KEY (daily_higher_id) REFERENCES cac (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
