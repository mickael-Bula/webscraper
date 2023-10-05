<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231002185551 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée la base de travail après modification de la relation One To Many entre les tables cac et last_high';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cac (id INT AUTO_INCREMENT NOT NULL, created_at DATE NOT NULL, closing DOUBLE PRECISION NOT NULL, opening DOUBLE PRECISION NOT NULL, higher DOUBLE PRECISION NOT NULL, lower DOUBLE PRECISION NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE last_high (id INT AUTO_INCREMENT NOT NULL, daily_cac_id INT DEFAULT NULL, daily_lvc_id INT DEFAULT NULL, higher DOUBLE PRECISION NOT NULL, buy_limit DOUBLE PRECISION NOT NULL, lvc_higher DOUBLE PRECISION NOT NULL, lvc_buy_limit DOUBLE PRECISION NOT NULL, INDEX IDX_672E2009AA5DF1C9 (daily_cac_id), INDEX IDX_672E200989CB088E (daily_lvc_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE lvc (id INT AUTO_INCREMENT NOT NULL, created_at DATE NOT NULL, closing DOUBLE PRECISION NOT NULL, opening DOUBLE PRECISION NOT NULL, higher DOUBLE PRECISION NOT NULL, lower DOUBLE PRECISION NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE position (id INT AUTO_INCREMENT NOT NULL, buy_limit_id INT DEFAULT NULL, user_id INT DEFAULT NULL, buy_target DOUBLE PRECISION NOT NULL, sell_target DOUBLE PRECISION NOT NULL, buy_date DATE DEFAULT NULL, sell_date DATE DEFAULT NULL, is_active TINYINT(1) DEFAULT 0 NOT NULL, is_closed TINYINT(1) DEFAULT 0 NOT NULL, is_waiting TINYINT(1) DEFAULT 0 NOT NULL, is_running TINYINT(1) DEFAULT 0 NOT NULL, lvc_buy_target DOUBLE PRECISION NOT NULL, lvc_sell_target DOUBLE PRECISION DEFAULT NULL, quantity INT NOT NULL, INDEX IDX_462CE4F55301742F (buy_limit_id), INDEX IDX_462CE4F5A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, higher_id INT DEFAULT NULL, last_cac_updated_id INT DEFAULT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), INDEX IDX_8D93D6496D44FFD6 (higher_id), UNIQUE INDEX UNIQ_8D93D6495D69E1F5 (last_cac_updated_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE last_high ADD CONSTRAINT FK_672E2009AA5DF1C9 FOREIGN KEY (daily_cac_id) REFERENCES cac (id)');
        $this->addSql('ALTER TABLE last_high ADD CONSTRAINT FK_672E200989CB088E FOREIGN KEY (daily_lvc_id) REFERENCES lvc (id)');
        $this->addSql('ALTER TABLE position ADD CONSTRAINT FK_462CE4F55301742F FOREIGN KEY (buy_limit_id) REFERENCES last_high (id)');
        $this->addSql('ALTER TABLE position ADD CONSTRAINT FK_462CE4F5A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D6496D44FFD6 FOREIGN KEY (higher_id) REFERENCES last_high (id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D6495D69E1F5 FOREIGN KEY (last_cac_updated_id) REFERENCES cac (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE last_high DROP FOREIGN KEY FK_672E2009AA5DF1C9');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D6495D69E1F5');
        $this->addSql('ALTER TABLE position DROP FOREIGN KEY FK_462CE4F55301742F');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D6496D44FFD6');
        $this->addSql('ALTER TABLE last_high DROP FOREIGN KEY FK_672E200989CB088E');
        $this->addSql('ALTER TABLE position DROP FOREIGN KEY FK_462CE4F5A76ED395');
        $this->addSql('DROP TABLE cac');
        $this->addSql('DROP TABLE last_high');
        $this->addSql('DROP TABLE lvc');
        $this->addSql('DROP TABLE position');
        $this->addSql('DROP TABLE user');
    }
}
