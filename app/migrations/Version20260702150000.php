<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * "Profil Etiketleri" (profile_stack_tags) site_settings'ten user.stack_tags'e
 * taşınır — kişisel bilgiler artık tamamen Hesabım (User) üzerinden yönetiliyor.
 */
final class Version20260702150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move profile_stack_tags from site_settings to user.stack_tags';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD stack_tags VARCHAR(255) DEFAULT NULL');

        $this->addSql(
            "UPDATE `user` SET stack_tags = (SELECT setting_value FROM site_settings WHERE setting_key = 'profile_stack_tags' AND setting_value <> '' LIMIT 1)
             WHERE id = (SELECT id FROM (SELECT MIN(id) AS id FROM `user`) owner)
               AND (stack_tags IS NULL OR stack_tags = '')
               AND EXISTS (SELECT 1 FROM site_settings WHERE setting_key = 'profile_stack_tags' AND setting_value <> '')"
        );

        $this->addSql("DELETE FROM site_settings WHERE setting_key = 'profile_stack_tags'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP stack_tags');
    }
}
