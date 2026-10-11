<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * visit_fingerprint tablosu her gün her benzersiz ziyaretçi için satır biriktirip
 * sınırsız büyüyordu. Benzersiz ziyaretçi tespiti artık çerez (vid) ile yapılıyor;
 * visit_daily.unique_visitors int kolonu tek kaynak olarak kalıyor.
 */
final class Version20260707090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop visit_fingerprint table (unique visitor detection moved to cookie-based tracking)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE visit_fingerprint');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE visit_fingerprint (id INT AUTO_INCREMENT NOT NULL, visit_date DATE NOT NULL, fingerprint VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_VISIT_FINGERPRINT (visit_date, fingerprint), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }
}
