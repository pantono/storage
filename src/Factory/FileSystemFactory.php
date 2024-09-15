<?php

namespace Pantono\Storage\Factory;

use Pantono\Contracts\Locator\FactoryInterface;
use League\Flysystem\Filesystem;
use Nyholm\Dsn\DsnParser;
use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;

class FileSystemFactory implements FactoryInterface
{
    private string $dsn;
    private array $options;

    public function __construct(string $dsn, array $options = [])
    {
        $this->dsn = $dsn;
        $this->options = $options;
    }

    public function createInstance(): Filesystem
    {
        $adapter = $this->getAdapter();
        return new Filesystem($adapter);
    }

    public function getAdapter(): FilesystemAdapter
    {
        $dsn = DsnParser::parse($this->dsn);
        if ($dsn->getScheme() === 's3') {
            $region = $this->options['region'] ?? 'eu-west-1';
            $bucket = $dsn->getHost();
            $key = $dsn->getUser();
            $pass = $dsn->getPassword();
            $prefix = $this->options['prefix'] ?? '';
            $client = new S3Client([
                'version' => 'latest',
                'region' => $region,
                'credentials' => [
                    'key' => $key,
                    'secret' => $pass
                ]
            ]);
            return new AwsS3V3Adapter($client, $bucket, $prefix);
        }
        if ($dsn->getScheme() === 'file') {
            return new LocalFilesystemAdapter($dsn->getHost());
        }

        throw new \RuntimeException('Cannot find adapter from dsn');
    }
}
