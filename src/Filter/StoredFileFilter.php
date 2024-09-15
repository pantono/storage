<?php

namespace Pantono\Storage\Filter;

use Pantono\Database\Traits\Pageable;
use Pantono\Contracts\Filter\PageableInterface;

class StoredFileFilter implements PageableInterface
{
    use Pageable;

    private ?string $bucket = null;
    private ?string $filename = null;
    private ?string $originalFilename = null;
    private ?string $uri = null;
    private ?int $minFilesize = null;
    private ?int $maxFilesize = null;
    private ?string $acl = null;
    private ?string $search = null;

    public function getBucket(): ?string
    {
        return $this->bucket;
    }

    public function setBucket(?string $bucket): void
    {
        $this->bucket = $bucket;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(?string $filename): void
    {
        $this->filename = $filename;
    }

    public function getOriginalFilename(): ?string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(?string $originalFilename): void
    {
        $this->originalFilename = $originalFilename;
    }

    public function getUri(): ?string
    {
        return $this->uri;
    }

    public function setUri(?string $uri): void
    {
        $this->uri = $uri;
    }

    public function getMinFilesize(): ?int
    {
        return $this->minFilesize;
    }

    public function setMinFilesize(?int $minFilesize): void
    {
        $this->minFilesize = $minFilesize;
    }

    public function getMaxFilesize(): ?int
    {
        return $this->maxFilesize;
    }

    public function setMaxFilesize(?int $maxFilesize): void
    {
        $this->maxFilesize = $maxFilesize;
    }

    public function getAcl(): ?string
    {
        return $this->acl;
    }

    public function setAcl(?string $acl): void
    {
        $this->acl = $acl;
    }

    public function getSearch(): ?string
    {
        return $this->search;
    }

    public function setSearch(?string $search): void
    {
        $this->search = $search;
    }
}
