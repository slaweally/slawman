<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616104500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Post revision and seo redirect tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE post_revision (id INT AUTO_INCREMENT NOT NULL, post_id INT NOT NULL, author_id INT DEFAULT NULL, action VARCHAR(30) NOT NULL, status VARCHAR(20) DEFAULT NULL, title VARCHAR(255) DEFAULT NULL, slug VARCHAR(255) DEFAULT NULL, content LONGTEXT NOT NULL, excerpt LONGTEXT DEFAULT NULL, meta JSON NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_POST_REVISION_POST (post_id), INDEX IDX_97B18C3E4B89032C (post_id), INDEX IDX_97B18C3EF675F31B (author_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE seo_redirect (id INT AUTO_INCREMENT NOT NULL, source_path VARCHAR(255) NOT NULL, target_path VARCHAR(255) NOT NULL, status_code INT NOT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_SEO_REDIRECT_SOURCE (source_path), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE post_revision ADD CONSTRAINT FK_97B18C3E4B89032C FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_revision ADD CONSTRAINT FK_97B18C3EF675F31B FOREIGN KEY (author_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE post_revision DROP FOREIGN KEY FK_97B18C3E4B89032C');
        $this->addSql('ALTER TABLE post_revision DROP FOREIGN KEY FK_97B18C3EF675F31B');
        $this->addSql('DROP TABLE post_revision');
        $this->addSql('DROP TABLE seo_redirect');
    }
}
