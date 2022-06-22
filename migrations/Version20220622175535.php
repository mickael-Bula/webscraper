<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220622175535 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE lvc ADD tracker_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE lvc ADD CONSTRAINT FK_135205C4FB5230B FOREIGN KEY (tracker_id) REFERENCES cac (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_135205C4FB5230B ON lvc (tracker_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE lvc DROP FOREIGN KEY FK_135205C4FB5230B');
        $this->addSql('DROP INDEX UNIQ_135205C4FB5230B ON lvc');
        $this->addSql('ALTER TABLE lvc DROP tracker_id');
    }
}
