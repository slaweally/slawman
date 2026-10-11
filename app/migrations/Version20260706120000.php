<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * security_alert tablosunu "her istek = yeni satır" modelinden
 * aggregation modeline taşır: hit_count + last_seen_at eklenir, aynı
 * IP+type için tek satır kalacak şekilde mevcut veriler birleştirilir,
 * ardından (ip_address, type) üzerinde UNIQUE kısıtı eklenir.
 */
final class Version20260706120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aggregate security_alert rows per IP+type (hit_count/last_seen_at) to prevent table bloat';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE security_alert ADD hit_count INT NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE security_alert ADD last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('UPDATE security_alert SET last_seen_at = created_at');

        // Mevcut duplicate (ip_address, type) satırlarını tek satırda birleştir:
        // en yeni satırı tut (en yüksek id), hit sayısını topla, en eski created_at /
        // en yeni last_seen_at bilgisini koru, sonra fazlalıkları sil.
        $this->addSql(<<<'SQL'
            UPDATE security_alert a
            INNER JOIN (
                SELECT ip_address, type, MAX(id) AS keep_id, SUM(hit_count) AS total_hits,
                       MIN(created_at) AS first_seen, MAX(created_at) AS last_seen
                FROM security_alert
                GROUP BY ip_address, type
            ) grp ON a.id = grp.keep_id
            SET a.hit_count = grp.total_hits,
                a.created_at = grp.first_seen,
                a.last_seen_at = grp.last_seen
        SQL);

        $this->addSql(<<<'SQL'
            DELETE a FROM security_alert a
            LEFT JOIN (
                SELECT ip_address, type, MAX(id) AS keep_id
                FROM security_alert
                GROUP BY ip_address, type
            ) grp ON a.ip_address = grp.ip_address AND a.type = grp.type AND a.id = grp.keep_id
            WHERE grp.keep_id IS NULL
        SQL);

        $this->addSql('ALTER TABLE security_alert ADD CONSTRAINT uniq_security_alert_ip_type UNIQUE (ip_address, type)');
        $this->addSql('ALTER TABLE security_alert CHANGE last_seen_at last_seen_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE security_alert DROP INDEX uniq_security_alert_ip_type');
        $this->addSql('ALTER TABLE security_alert DROP hit_count');
        $this->addSql('ALTER TABLE security_alert DROP last_seen_at');
    }
}
