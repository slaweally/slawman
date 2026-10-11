<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260703110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_read flag to security_alert for admin notification management';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE security_alert ADD is_read TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE security_alert DROP is_read');
    }
}
