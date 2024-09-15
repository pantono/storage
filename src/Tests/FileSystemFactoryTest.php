<?php

namespace Pantono\Storage\Tests;

use PHPUnit\Framework\TestCase;
use Pantono\Storage\Factory\FileSystemFactory;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Local\LocalFilesystemAdapter;

class FileSystemFactoryTest extends TestCase
{
    public function testS3()
    {
        $factory = new FileSystemFactory('s3://user:pass@some-bucket');
        $instance = $factory->getAdapter();
        $this->assertEquals($instance::class, AwsS3V3Adapter::class);
    }

    public function testLocalFilesystem()
    {
        $factory = new FileSystemFactory('file://some-path');
        $instance = $factory->getAdapter();
        $this->assertEquals($instance::class, LocalFilesystemAdapter::class);
    }
}
