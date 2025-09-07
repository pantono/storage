<?php

namespace Pantono\Storage\Factory;

use Pantono\Contracts\Locator\FactoryInterface;
use League\Flysystem\Filesystem;
use Nyholm\Dsn\DsnParser;
use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Pantono\Logger\Logger;
use Aws\Handler\Guzzle\GuzzleHandler;

class FileSystemFactory implements FactoryInterface
{
    private string $dsn;
    private Logger $logger;
    private array $options;
    private array $fileSystemOptions;

    public function __construct(string $dsn, Logger $Logger, array $adapterOptions = [], array $fileSystemOptions = [])
    {
        $this->dsn = $dsn;
        $this->logger = $Logger;
        $this->options = $adapterOptions;
        $this->fileSystemOptions = $fileSystemOptions;
    }

    public function createInstance(): Filesystem
    {
        $adapter = $this->getAdapter();
        return new Filesystem($adapter, $this->fileSystemOptions);
    }

    public function getAdapter(): FilesystemAdapter
    {
        $dsn = DsnParser::parse($this->dsn);
        if ($dsn->getScheme() === 's3') {
            $region = $this->options['region'] ?? 'eu-west-1';
            $bucket = $dsn->getPath();
            if (str_starts_with($bucket, '/')) {
                $bucket = substr($bucket, 1);
            }
            $host = $dsn->getHost();
            $key = $dsn->getUser();
            $pass = $dsn->getPassword();
            $prefix = $this->options['prefix'] ?? '';
            $clientConfig = [
                'version' => 'latest',
                'region' => $region,
                'credentials' => [
                    'key' => $key,
                    'secret' => $pass
                ]
            ];
            if ($host) {
                $clientConfig['endpoint'] = 'https://' . $host . '/';
            }

            if (array_key_exists('use_path_style_endpoint', $this->options)) {
                $clientConfig['use_path_style_endpoint'] = (bool)$this->options['use_path_style_endpoint'];
            } elseif (!empty($clientConfig['endpoint'])) {
                $clientConfig['use_path_style_endpoint'] = true;
            }

            if (array_key_exists('use_log', $this->options) && $this->options['use_log']) {
                $logger = $this->logger->createLoggedHttpClient('s3_file_storage');
                $clientConfig['http_handler'] = new GuzzleHandler($logger);
            }

            $client = new S3Client($clientConfig);
            return new AwsS3V3Adapter($client, $bucket, $prefix);
        }
        if ($dsn->getScheme() === 'file') {
            return new LocalFilesystemAdapter($dsn->getHost());
        }

        throw new \RuntimeException('Cannot find adapter from dsn');
    }
}
