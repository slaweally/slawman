<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616121000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sync schema defaults and indexes with ORM mapping';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_POST_STATUS_TYPE_PUBLISHED ON post');
        $this->addSql('DROP INDEX IDX_POST_META_KEY_VALUE ON post_meta');
        $this->addSql('DROP INDEX IDX_97B18C3E4B89032C ON post_revision');
        $this->addSql('ALTER TABLE post_revision RENAME INDEX idx_97b18c3ef675f31b TO IDX_D28E9C6DF675F31B');
        $this->addSql('ALTER TABLE seo_redirect CHANGE status_code status_code INT DEFAULT 301 NOT NULL, CHANGE is_active is_active TINYINT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE visit_fingerprint CHANGE created_at created_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE INDEX IDX_POST_STATUS_TYPE_PUBLISHED ON post (status, type, published_at)');
        $this->addSql('CREATE INDEX IDX_POST_META_KEY_VALUE ON post_meta (meta_key, meta_value(191))');
        $this->addSql('CREATE INDEX IDX_97B18C3E4B89032C ON post_revision (post_id)');
        $this->addSql('ALTER TABLE post_revision RENAME INDEX IDX_D28E9C6DF675F31B TO idx_97b18c3ef675f31b');
        $this->addSql('ALTER TABLE seo_redirect CHANGE status_code status_code INT NOT NULL, CHANGE is_active is_active TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE visit_fingerprint CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
