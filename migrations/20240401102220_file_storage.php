<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class FileStorage extends AbstractMigration
{
    public function change(): void
    {
        $this->table('stored_file')
            ->addColumn('date_uploaded', 'datetime')
            ->addColumn('filename', 'string')
            ->addColumn('original_filename', 'string')
            ->addColumn('bucket', 'string', ['null' => true])
            ->addColumn('filesize', 'biginteger')
            ->addColumn('etag', 'string', ['null' => true])
            ->addColumn('acl', 'string', ['null' => true])
            ->addColumn('uri', 'string')
            ->addIndex('filename')
            ->create();

        $this->table('stored_file_access')
            ->addColumn('file_id', 'integer', ['signed' => false])
            ->addColumn('uri', 'string', ['null' => true])
            ->addColumn('created_date', 'datetime')
            ->addColumn('expiry_date', 'datetime', ['null' => true])
            ->addColumn('user_id', 'integer', ['signed' => false])
            ->addForeignKey('file_id', 'stored_file', 'id')
            ->addForeignKey('user_id', 'user', 'id')
            ->create();
    }
}
