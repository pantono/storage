<?php

namespace Pantono\Storage\Model;

use Pantono\Database\Traits\SavableModel;
use Pantono\Contracts\Attributes\NoSave;
use Pantono\Contracts\Attributes\Locator;
use Pantono\Storage\FileStorage;
use Pantono\Contracts\Attributes\Lazy;
use Pantono\Contracts\Attributes\FieldName;
use Pantono\Contracts\Attributes\DatabaseTable;
use Pantono\Contracts\Attributes\Database\OneToOne;
use Pantono\Contracts\Attributes\EagerLoad;

#[DatabaseTable(table: 'stored_file', idColumn: 'id'), EagerLoad]
class StoredFile
{
    use SavableModel;

    private ?int $id = null;
    private \DateTimeImmutable $dateUploaded;
    private string $filename;
    private string $originalFilename;
    private ?string $bucket = null;
    private int $filesize;
    private ?string $etag = null;
    private ?string $acl = null;
    private ?string $uri = null;
    private ?string $mimeType = null;
    #[NoSave, Locator(methodName: 'getFileData', className: FileStorage::class), Lazy, FieldName('$this')]
    private ?string $fileData = null;
    #[OneToOne(StoredFileService::class), FieldName('storage_service')]
    private ?StoredFileService $storageService = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getDateUploaded(): \DateTimeImmutable
    {
        return $this->dateUploaded;
    }

    public function setDateUploaded(\DateTimeImmutable $dateUploaded): void
    {
        $this->dateUploaded = $dateUploaded;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): void
    {
        $this->filename = $filename;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(string $originalFilename): void
    {
        $this->originalFilename = $originalFilename;
    }

    public function getBucket(): ?string
    {
        return $this->bucket;
    }

    public function setBucket(?string $bucket): void
    {
        $this->bucket = $bucket;
    }

    public function getFilesize(): int
    {
        return $this->filesize;
    }

    public function setFilesize(int $filesize): void
    {
        $this->filesize = $filesize;
    }

    public function getEtag(): ?string
    {
        return $this->etag;
    }

    public function setEtag(?string $etag): void
    {
        $this->etag = $etag;
    }

    public function getAcl(): ?string
    {
        return $this->acl;
    }

    public function setAcl(?string $acl): void
    {
        $this->acl = $acl;
    }

    public function getUri(): ?string
    {
        return $this->uri;
    }

    public function setUri(?string $uri): void
    {
        $this->uri = $uri;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): void
    {
        $this->mimeType = $mimeType;
    }

    public function getFileData(): ?string
    {
        return $this->fileData;
    }

    public function setFileData(?string $fileData): void
    {
        $this->fileData = $fileData;
    }

    public function getDataUri(): ?string
    {
        $mime = $this->lookupMimeType() ?: 'application/octet-stream';
        return 'data:' . $mime . ';base64,' . base64_encode($this->getFileData());
    }

    private function lookupMimeType(): ?string
    {
        if ($this->getFileData() === '') {
            return null;
        }

        $fInfo = new \finfo(FILEINFO_MIME_TYPE);
        return $fInfo->buffer($this->getFileData()) ?: null;
    }

    public function getStorageService(): ?StoredFileService
    {
        return $this->storageService;
    }

    public function setStorageService(?StoredFileService $storageService): void
    {
        $this->storageService = $storageService;
    }
}
