<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Hacker temalı takip paneli: tracker_log (loglanan olaylar) ve
 * tracker_message (admin tarafından düzenlenebilir mesaj şablonları) tabloları.
 */
final class Version20260702130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tracker_log and tracker_message tables for the front-end activity panel';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tracker_log (
            id INT AUTO_INCREMENT NOT NULL,
            event_type VARCHAR(20) NOT NULL,
            message LONGTEXT NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            masked_ip VARCHAR(45) NOT NULL,
            page_url VARCHAR(255) DEFAULT NULL,
            session_key VARCHAR(64) DEFAULT NULL,
            duration_seconds INT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_tracker_log_created_at (created_at),
            INDEX idx_tracker_log_session_key (session_key),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE tracker_message (
            id INT AUTO_INCREMENT NOT NULL,
            event_type VARCHAR(20) NOT NULL,
            template LONGTEXT NOT NULL,
            is_active TINYINT DEFAULT 1 NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $defaults = [
            ['visit', '[{ip}] {name} [{post}] sayfasına uğradı, göz gezdiriyor...'],
            ['visit', '[{ip}] {name} IP\'sini gizlemiş ama [{ip}] adresinden geldiğini biliyorum, takipteyim :)'],
            ['comment', '[{ip}] {name} [{post}] okudu ve yorum yazdı: incelemem lazım :)'],
            ['login', '[{ip}] {name} siteye giriş yaptı, hoşgelmişsiniz efenim.'],
        ];
        foreach ($defaults as [$type, $template]) {
            $this->addSql(sprintf(
                "INSERT INTO tracker_message (event_type, template, is_active, created_at) VALUES ('%s', '%s', 1, '%s')",
                $type,
                addslashes($template),
                $now
            ));
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tracker_log');
        $this->addSql('DROP TABLE tracker_message');
    }
}
