<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class FileStorageMimeType extends AbstractMigration
{
    public function change(): void
    {
        $this->table('stored_file')
            ->addColumn('mime_type', 'string', ['null' => true])
            ->update();
    }
}
