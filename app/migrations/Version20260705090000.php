<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260705090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add composite index (status, published_at) to post for virtual-cron scheduled publishing';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_post_status_published_at ON post (status, published_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_post_status_published_at ON post');
    }
}
