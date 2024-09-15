<?php

namespace Pantono\Storage\Repository;

use Pantono\Database\Repository\MysqlRepository;
use Pantono\Storage\Model\StoredFile;
use Pantono\Storage\Filter\StoredFileFilter;

class FileStorageRepository extends MysqlRepository
{
    public function getFileById(int $id): ?array
    {
        return $this->selectSingleRow('stored_file', 'id', $id);
    }

    public function getFileByUri(string $uri): ?array
    {
        return $this->selectSingleRow('stored_file', 'uri', $uri);
    }

    public function saveFile(StoredFile $file): void
    {
        $id = $this->insertOrUpdateCheck('stored_file', 'id', $file->getId(), $file->getAllData());
        if ($id) {
            $file->setId($id);
        }
    }

    public function getFilesByFilter(StoredFileFilter $filter): array
    {
        $select = $this->getDb()->select()->from('stored_file');
        if ($filter->getSearch()) {
            $select->where('(filename like ?', '%' . $filter->getSearch() . '%')
                ->orWhere('original_filename like ?', '%' . $filter->getSearch() . '%');
        }
        if ($filter->getFilename()) {
            $select->where('filename=?', $filter->getFilename());
        }
        if ($filter->getOriginalFilename()) {
            $select->where('original_filename=?', $filter->getOriginalFilename());
        }
        if ($filter->getBucket()) {
            $select->where('bucket=?', $filter->getBucket());
        }
        if ($filter->getMinFilesize()) {
            $select->where('filesize >= ?', $filter->getMinFilesize());
        }
        if ($filter->getMaxFilesize()) {
            $select->where('filesize <= ?', $filter->getMaxFilesize());
        }
        if ($filter->getAcl()) {
            $select->where('acl=?', $filter->getAcl());
        }

        $filter->setTotalResults($this->getCount($select));
        $select->limitPage($filter->getPage(), $filter->getPerPage());

        return $this->getDb()->fetchAll($select);
    }

    public function logFileAccess(StoredFile $file, int $userId, string $uri, \DateTimeInterface $expiryDate): void
    {
        $this->getDb()->insert('stored_file_access', [
            'file_id' => $file->getId(),
            'uri' => $uri,
            'created_date' => (new \DateTime())->format('Y-m-d H:i:s'),
            'user_id' => $userId,
            'expiry_date' => $expiryDate->format('Y-m-d H:i:s')
        ]);
    }
}
