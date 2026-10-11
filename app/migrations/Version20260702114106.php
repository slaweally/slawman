<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Pre-existing schema drift fix: SidebarWidget entity has had a `settings`
 * (JSON) property since its introduction, but the original table creation
 * migration never added the column, breaking any query against sidebar_widget.
 */
final class Version20260702114106 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing settings column to sidebar_widget (entity/schema drift)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sidebar_widget ADD settings JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sidebar_widget DROP settings');
    }
}
