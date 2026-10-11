<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ziyaretçi istatistik tabloları';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE visit_daily (id INT AUTO_INCREMENT NOT NULL, visit_date DATE NOT NULL, page_views INT DEFAULT 0 NOT NULL, unique_visitors INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_VISIT_DATE (visit_date), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE visit_fingerprint (id INT AUTO_INCREMENT NOT NULL, visit_date DATE NOT NULL, fingerprint VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_VISIT_FINGERPRINT (visit_date, fingerprint), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE visit_fingerprint');
        $this->addSql('DROP TABLE visit_daily');
    }
}
