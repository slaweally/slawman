<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260615190846 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE audit_log (id INT AUTO_INCREMENT NOT NULL, action VARCHAR(100) NOT NULL, entity_type VARCHAR(100) NOT NULL, entity_id INT NOT NULL, old_value JSON DEFAULT NULL, new_value JSON DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(500) DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_F6E1C0F5A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, slug VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, icon VARCHAR(50) DEFAULT NULL, color VARCHAR(20) DEFAULT NULL, sort_order INT DEFAULT 0 NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, parent_id INT DEFAULT NULL, INDEX IDX_64C19C1727ACA70 (parent_id), UNIQUE INDEX UNIQ_CATEGORY_SLUG (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE comment (id INT AUTO_INCREMENT NOT NULL, author_name VARCHAR(100) NOT NULL, author_email VARCHAR(180) NOT NULL, content LONGTEXT NOT NULL, status VARCHAR(20) DEFAULT \'pending\' NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(500) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, post_id INT NOT NULL, parent_id INT DEFAULT NULL, INDEX IDX_9474526C4B89032C (post_id), INDEX IDX_9474526C727ACA70 (parent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE contact_message (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, subject VARCHAR(255) NOT NULL, message LONGTEXT NOT NULL, is_read TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE media (id INT AUTO_INCREMENT NOT NULL, filename VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(100) NOT NULL, size INT NOT NULL, path VARCHAR(500) NOT NULL, alt VARCHAR(255) DEFAULT NULL, caption LONGTEXT DEFAULT NULL, type VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, uploader_id INT NOT NULL, INDEX IDX_6A2CA10C16678C77 (uploader_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE post (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) DEFAULT NULL, slug VARCHAR(255) NOT NULL, content LONGTEXT NOT NULL, excerpt VARCHAR(500) DEFAULT NULL, type VARCHAR(20) NOT NULL, status VARCHAR(20) DEFAULT \'draft\' NOT NULL, is_featured TINYINT DEFAULT 0 NOT NULL, is_sticky TINYINT DEFAULT 0 NOT NULL, featured_image VARCHAR(255) DEFAULT NULL, view_count INT DEFAULT 0 NOT NULL, programming_language VARCHAR(50) DEFAULT NULL, published_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, author_id INT NOT NULL, INDEX IDX_5A8A6C8DF675F31B (author_id), UNIQUE INDEX UNIQ_POST_SLUG (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE post_category (post_id INT NOT NULL, category_id INT NOT NULL, INDEX IDX_B9A190604B89032C (post_id), INDEX IDX_B9A1906012469DE2 (category_id), PRIMARY KEY (post_id, category_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE post_tag (post_id INT NOT NULL, tag_id INT NOT NULL, INDEX IDX_5ACE3AF04B89032C (post_id), INDEX IDX_5ACE3AF0BAD26311 (tag_id), PRIMARY KEY (post_id, tag_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE post_meta (id INT AUTO_INCREMENT NOT NULL, meta_key VARCHAR(100) NOT NULL, meta_value LONGTEXT DEFAULT NULL, post_id INT NOT NULL, INDEX IDX_1EA7733E4B89032C (post_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE site_settings (id INT AUTO_INCREMENT NOT NULL, setting_key VARCHAR(100) NOT NULL, setting_value LONGTEXT DEFAULT NULL, setting_group VARCHAR(50) DEFAULT NULL, UNIQUE INDEX UNIQ_SETTING_KEY (setting_key), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE subscriber (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, subscribed_at DATETIME NOT NULL, unsubscribed_at DATETIME DEFAULT NULL, token BINARY(16) NOT NULL, UNIQUE INDEX UNIQ_SUBSCRIBER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tag (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, slug VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_TAG_SLUG (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(50) NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, avatar VARCHAR(255) DEFAULT NULL, bio LONGTEXT DEFAULT NULL, website VARCHAR(255) DEFAULT NULL, github VARCHAR(255) DEFAULT NULL, twitter VARCHAR(255) DEFAULT NULL, linkedin VARCHAR(255) DEFAULT NULL, is_active TINYINT DEFAULT 1 NOT NULL, is_verified TINYINT DEFAULT 0 NOT NULL, last_login_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_USER_USERNAME (username), UNIQUE INDEX UNIQ_USER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE audit_log ADD CONSTRAINT FK_F6E1C0F5A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C1727ACA70 FOREIGN KEY (parent_id) REFERENCES category (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C4B89032C FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C727ACA70 FOREIGN KEY (parent_id) REFERENCES comment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE media ADD CONSTRAINT FK_6A2CA10C16678C77 FOREIGN KEY (uploader_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8DF675F31B FOREIGN KEY (author_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE post_category ADD CONSTRAINT FK_B9A190604B89032C FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_category ADD CONSTRAINT FK_B9A1906012469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_tag ADD CONSTRAINT FK_5ACE3AF04B89032C FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_tag ADD CONSTRAINT FK_5ACE3AF0BAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_meta ADD CONSTRAINT FK_1EA7733E4B89032C FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE audit_log DROP FOREIGN KEY FK_F6E1C0F5A76ED395');
        $this->addSql('ALTER TABLE category DROP FOREIGN KEY FK_64C19C1727ACA70');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C4B89032C');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C727ACA70');
        $this->addSql('ALTER TABLE media DROP FOREIGN KEY FK_6A2CA10C16678C77');
        $this->addSql('ALTER TABLE post DROP FOREIGN KEY FK_5A8A6C8DF675F31B');
        $this->addSql('ALTER TABLE post_category DROP FOREIGN KEY FK_B9A190604B89032C');
        $this->addSql('ALTER TABLE post_category DROP FOREIGN KEY FK_B9A1906012469DE2');
        $this->addSql('ALTER TABLE post_tag DROP FOREIGN KEY FK_5ACE3AF04B89032C');
        $this->addSql('ALTER TABLE post_tag DROP FOREIGN KEY FK_5ACE3AF0BAD26311');
        $this->addSql('ALTER TABLE post_meta DROP FOREIGN KEY FK_1EA7733E4B89032C');
        $this->addSql('DROP TABLE audit_log');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE comment');
        $this->addSql('DROP TABLE contact_message');
        $this->addSql('DROP TABLE media');
        $this->addSql('DROP TABLE post');
        $this->addSql('DROP TABLE post_category');
        $this->addSql('DROP TABLE post_tag');
        $this->addSql('DROP TABLE post_meta');
        $this->addSql('DROP TABLE site_settings');
        $this->addSql('DROP TABLE subscriber');
        $this->addSql('DROP TABLE tag');
        $this->addSql('DROP TABLE `user`');
    }
}
