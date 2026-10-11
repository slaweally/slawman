<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260706090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add self-referencing parent_id to menu_item for nested dropdown menus';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE menu_item ADD parent_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE menu_item ADD CONSTRAINT FK_MENU_ITEM_PARENT FOREIGN KEY (parent_id) REFERENCES menu_item (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX idx_menu_item_parent_sort ON menu_item (parent_id, sort_order)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE menu_item DROP FOREIGN KEY FK_MENU_ITEM_PARENT');
        $this->addSql('DROP INDEX idx_menu_item_parent_sort ON menu_item');
        $this->addSql('ALTER TABLE menu_item DROP parent_id');
    }
}
