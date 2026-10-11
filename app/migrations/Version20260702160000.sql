-- Migration: Version20260702160000
-- Açıklama: WAF/auth katmanının tespit ettiği şüpheli istekler için security_alert tablosu.
-- Manuel çalıştırma: veritabanı adını/bağlantısını kendi ortamına göre seç, sonra bu dosyayı uygula.

START TRANSACTION;

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
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

-- Doctrine migration takip tablosuna manuel uygulamayı işaretle
-- (bu satır olmadan `doctrine:migrations:migrate` bir sonraki çalıştırmada
--  tabloyu tekrar oluşturmaya çalışıp "already exists" hatası verir).
INSERT INTO doctrine_migration_versions (version, executed_at, execution_time)
VALUES ('DoctrineMigrations\\Version20260702160000', NOW(), 0);

COMMIT;

-- Geri almak (rollback) istersen:
-- START TRANSACTION;
-- DROP TABLE security_alert;
-- DELETE FROM doctrine_migration_versions WHERE version = 'DoctrineMigrations\\Version20260702160000';
-- COMMIT;
