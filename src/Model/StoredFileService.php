<?php

namespace Pantono\Storage\Model;

use Pantono\Contracts\Attributes\Filter;
use Pantono\Contracts\Attributes\DatabaseTable;
use Pantono\Utilities\ApplicationHelper;

#[DatabaseTable('stored_file_service')]
class StoredFileService
{
    private ?int $id = null;
    private string $name;
    private string $dsn;
    /**
     * @var array<mixed>
     */
    #[Filter('json_decode')]
    private array $adapterOptions = [];
    /**
     * @var array<mixed>
     */
    #[Filter('json_decode')]
    private array $options = [];
    private bool $default;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDsn(): string
    {
        return $this->dsn;
    }

    public function setDsn(string $dsn): void
    {
        $this->dsn = $dsn;
    }

    public function getInterpolatedDsn(): string
    {
        $dsn = $this->getDsn();
        return ApplicationHelper::interpolateEnv($dsn);
    }

    public function getAdapterOptions(): array
    {
        return $this->adapterOptions;
    }

    public function setAdapterOptions(array $adapterOptions): void
    {
        $this->adapterOptions = $adapterOptions;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    public function isDefault(): bool
    {
        return $this->default;
    }

    public function setDefault(bool $default): void
    {
        $this->default = $default;
    }
}
