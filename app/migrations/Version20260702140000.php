<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * tracker_identity: IP -> sabit takma ad eşleştirmesi (ham IP saklanmaz).
 * tracker_log.template_id: aynı şablonun art arda tekrarını engellemek için.
 */
final class Version20260702140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tracker_identity table and tracker_log.template_id column';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tracker_identity (
            id INT AUTO_INCREMENT NOT NULL,
            ip_hash VARCHAR(64) NOT NULL,
            display_name VARCHAR(100) NOT NULL,
            first_seen_at DATETIME NOT NULL,
            last_seen_at DATETIME NOT NULL,
            UNIQUE INDEX uniq_tracker_identity_ip_hash (ip_hash),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE tracker_log ADD template_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tracker_identity');
        $this->addSql('ALTER TABLE tracker_log DROP template_id');
    }
}
