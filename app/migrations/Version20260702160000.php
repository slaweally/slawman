<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * WAF/auth katmanının tespit ettiği şüpheli istekler için kalıcı kayıt tablosu.
 * Admin panelde "Güvenlik Alarmları" ekranını besler.
 */
final class Version20260702160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create security_alert table for WAF/auth alert tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE security_alert (
                id INT AUTO_INCREMENT NOT NULL,
                type VARCHAR(20) NOT NULL,
                severity VARCHAR(10) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                request_path VARCHAR(255) DEFAULT NULL,
                request_method VARCHAR(10) DEFAULT NULL,
                user_agent LONGTEXT DEFAULT NULL,
                reason LONGTEXT DEFAULT NULL,
                mail_sent TINYINT(1) NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_security_alert_created_at (created_at),
                INDEX idx_security_alert_severity (severity),
                INDEX idx_security_alert_ip (ip_address),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE security_alert');
    }
}
