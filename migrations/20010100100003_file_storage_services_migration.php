<?php

declare(strict_types=1);

use Pantono\Database\Migration\Base\BasePantonoMigration;

final class FileStorageServicesMigration extends BasePantonoMigration
{
    public function change(): void
    {
        $this->table($this->addTablePrefix('stored_file_service'))
            ->addColumn('name', 'string')
            ->addColumn('dsn', 'string')
            ->addColumn('adapter_options', 'json', ['default' => '{}'])
            ->addColumn('options', 'json', ['default' => '{}'])
            ->addColumn('default', 'boolean')
            ->create();

        $this->table($this->addTablePrefix('stored_file'))
            ->addLinkedColumn('storage_service', $this->addTablePrefix('stored_file_service'), 'id', ['null' => true, 'signed' => false])
            ->update();
    }
}
