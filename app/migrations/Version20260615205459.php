<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260615205459 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE menu_item (id INT AUTO_INCREMENT NOT NULL, location VARCHAR(20) NOT NULL, label VARCHAR(100) NOT NULL, url VARCHAR(500) NOT NULL, icon VARCHAR(80) DEFAULT NULL, sort_order INT DEFAULT 0 NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, open_in_new_tab TINYINT DEFAULT 0 NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE sidebar_widget (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(100) NOT NULL, content LONGTEXT DEFAULT NULL, sort_order INT DEFAULT 0 NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE contact_message ADD ip_address VARCHAR(45) DEFAULT NULL, ADD user_agent VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE menu_item');
        $this->addSql('DROP TABLE sidebar_widget');
        $this->addSql('ALTER TABLE contact_message DROP ip_address, DROP user_agent');
    }
}
