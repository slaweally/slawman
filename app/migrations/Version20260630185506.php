<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260630185506 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sidebar_widget ADD type VARCHAR(50) DEFAULT \'html\' NOT NULL');
        
        // Data Migration: Seed built-in widgets based on current settings
        // Profile
        $this->addSql("INSERT INTO sidebar_widget (title, type, sort_order, is_active)
            SELECT 'Profil', 'profile', 0, IF(MAX(setting_value)='true', 1, 0) FROM site_settings WHERE setting_key = 'widget_profile_enabled'");
        // Recent Posts
        $this->addSql("INSERT INTO sidebar_widget (title, type, sort_order, is_active)
            SELECT 'Son İçerikler', 'recent', 10, IF(MAX(setting_value)='true', 1, 0) FROM site_settings WHERE setting_key = 'widget_recent_enabled'");
        // Categories
        $this->addSql("INSERT INTO sidebar_widget (title, type, sort_order, is_active)
            SELECT 'Kategoriler', 'categories', 20, IF(MAX(setting_value)='true', 1, 0) FROM site_settings WHERE setting_key = 'widget_categories_enabled'");
        // Comments
        $this->addSql("INSERT INTO sidebar_widget (title, type, sort_order, is_active)
            SELECT 'Son Yorumlar', 'comments', 30, IF(MAX(setting_value)='true', 1, 0) FROM site_settings WHERE setting_key = 'widget_comments_enabled'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sidebar_widget DROP type');
    }
}
