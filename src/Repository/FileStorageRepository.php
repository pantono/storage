<?php

namespace Pantono\Storage\Repository;

use Pantono\Database\Repository\DefaultRepository;
use Pantono\Storage\Model\StoredFile;
use Pantono\Storage\Filter\StoredFileFilter;

class FileStorageRepository extends DefaultRepository
{
    public function getFileById(int $id): ?array
    {
        return $this->selectSingleRow('stored_file', 'id', $id);
    }

    public function getFileByUri(string $uri): ?array
    {
        return $this->selectSingleRow('stored_file', 'uri', $uri);
    }

    public function getFileByFilename(string $filename): ?array
    {
        return $this->selectSingleRow('stored_file', 'filename', $filename);
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
        $select = $this->getDb()->select('f.*')->from('stored_file', 'f');
        if ($filter->getSearch()) {
            $select->where('(filename like :search or original_filename like :search)')
                ->setParameter('search', '%' . $filter->getSearch() . '%');
        }
        if ($filter->getFilename()) {
            $select->where('filename=:filename')
                ->setParameter('filename', $filter->getFilename());
        }
        if ($filter->getOriginalFilename()) {
            $select->where('original_filename=:original_filename')
                ->setParameter('original_filename', $filter->getOriginalFilename());
        }
        if ($filter->getBucket()) {
            $select->where('bucket=:bucket')
                ->setParameter('bucket', $filter->getBucket());
        }
        if ($filter->getMinFilesize()) {
            $select->where('filesize >= :min_filesize')
                ->setParameter('min_filesize', $filter->getMinFilesize());
        }
        if ($filter->getMaxFilesize()) {
            $select->where('filesize <= :max_filesize')
                ->setParameter('max_filesize', $filter->getMaxFilesize());
        }
        if ($filter->getAcl()) {
            $select->where('acl = :acl')
                ->setParameter('acl', $filter->getAcl());
        }

        $this->applyCountAndLimit($select, $filter);

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

    /**
     * @return array<mixed>|null
     */
    public function getDefaultStorageService(): ?array
    {
        return $this->selectSingleRow($this->pt('stored_file_service'), 'default', 1);
    }
}
