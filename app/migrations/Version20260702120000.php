<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Site sahibi kişisel bilgilerini (profil resmi, bio, sosyal linkler, yazar adı)
 * SiteSettings'ten User (Hesabım) tablosuna taşır; admin panel ve site artık
 * tek bir kaynaktan (owner User) besleniyor.
 */
final class Version20260702120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move site owner profile fields (author, avatar, bio, social links) from site_settings to user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD display_name VARCHAR(255) DEFAULT NULL');

        $fieldMap = [
            'display_name' => 'site_author',
            'avatar' => 'profile_image',
            'bio' => 'profile_bio',
            'github' => 'social_github',
            'twitter' => 'social_twitter',
            'linkedin' => 'social_linkedin',
        ];

        foreach ($fieldMap as $userColumn => $settingKey) {
            $this->addSql(sprintf(
                "UPDATE `user` SET %s = (SELECT setting_value FROM site_settings WHERE setting_key = '%s' AND setting_value <> '' LIMIT 1)
                 WHERE id = (SELECT id FROM (SELECT MIN(id) AS id FROM `user`) owner)
                   AND (%s IS NULL OR %s = '')
                   AND EXISTS (SELECT 1 FROM site_settings WHERE setting_key = '%s' AND setting_value <> '')",
                $userColumn,
                $settingKey,
                $userColumn,
                $userColumn,
                $settingKey
            ));
        }

        $this->addSql("DELETE FROM site_settings WHERE setting_key IN ('site_author', 'profile_image', 'profile_bio', 'social_github', 'social_twitter', 'social_linkedin')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP display_name');
    }
}
