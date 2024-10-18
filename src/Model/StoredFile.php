<?php

namespace Pantono\Storage\Model;

use Pantono\Database\Traits\SavableModel;
use Pantono\Contracts\Attributes\NoSave;
use Pantono\Contracts\Attributes\Locator;
use Pantono\Storage\FileStorage;

#[Locator(className: FileStorage::class, methodName: 'getFileById')]
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
    #[NoSave]
    private ?string $fileData = null;

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

    public function getFileData(): ?string
    {
        return $this->fileData;
    }

    public function setFileData(?string $fileData): void
    {
        $this->fileData = $fileData;
    }
}
