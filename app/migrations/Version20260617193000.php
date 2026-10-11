<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260617193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional author website URL to comments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE comment ADD author_website VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE comment DROP author_website');
    }
}
