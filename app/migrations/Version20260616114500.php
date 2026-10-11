<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616114500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add performance indexes for post and post_meta';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX IDX_POST_STATUS_TYPE_PUBLISHED ON post (status, type, published_at)');
        $this->addSql('CREATE INDEX IDX_POST_META_KEY_VALUE ON post_meta (meta_key, meta_value(191))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_POST_STATUS_TYPE_PUBLISHED ON post');
        $this->addSql('DROP INDEX IDX_POST_META_KEY_VALUE ON post_meta');
    }
}
