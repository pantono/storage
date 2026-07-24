<?php

namespace Pantono\Storage;

use Pantono\Storage\Repository\FileStorageRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Pantono\Storage\Model\StoredFile;
use Pantono\Storage\Event\PreStoredFileSaveEvent;
use Pantono\Storage\Filter\StoredFileFilter;
use Pantono\Hydrator\Hydrator;
use Pantono\Storage\Event\PostStoredFileSaveEvent;
use League\Flysystem\Filesystem;
use Pantono\Contracts\Locator\UserInterface;
use League\Flysystem\Visibility;
use Symfony\Component\Console\Output\OutputInterface;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemException;
use Pantono\Storage\Helper\MimeTypeHelper;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Pantono\Storage\Exception\FileUploadError;
use Pantono\Storage\Model\StoredFileService;
use Pantono\Logger\Logger;
use Psr\Container\ContainerInterface;
use Pantono\Storage\Factory\FileSystemFactory;
use Pantono\Storage\Exception\NoFileServiceAvailable;

class FileStorage
{
    private FileStorageRepository $repository;
    private Hydrator $hydrator;
    private EventDispatcher $dispatcher;
    private Logger $logger;
    private ContainerInterface $container;

    public function __construct(
        FileStorageRepository $repository,
        Hydrator              $hydrator,
        EventDispatcher       $dispatcher,
        Logger                $logger,
        ContainerInterface    $container
    )
    {
        $this->repository = $repository;
        $this->hydrator = $hydrator;
        $this->dispatcher = $dispatcher;
        $this->logger = $logger;
        $this->container = $container;
    }

    public function uploadFileFromRequest(UploadedFile $uploadedFile, string $visibility = Visibility::PRIVATE, ?StoredFileService $service = null): StoredFile
    {
        if ($uploadedFile->getError()) {
            throw new FileUploadError($uploadedFile->getErrorMessage());
        }
        $remoteFilename = $uploadedFile->getClientOriginalName();
        $fileSystem = $this->getFilesystemForService($service);
        $fileSystem->write($remoteFilename, $uploadedFile->getContent(), [
            'params' => [
                'ACL' => $visibility
            ]
        ]);
        $uri = $fileSystem->publicUrl($remoteFilename);
        $file = new StoredFile();
        $file->setOriginalFilename($uploadedFile->getClientOriginalName());
        $file->setDateUploaded(new \DateTimeImmutable());
        $file->setFilename($remoteFilename);
        $file->setBucket('');
        $file->setFilesize(mb_strlen($uploadedFile->getContent()));
        $file->setFileData($uploadedFile->getContent());
        $file->setUri($uri);
        $mime = MimeTypeHelper::guessMimeType($uploadedFile->getClientOriginalName(), $uploadedFile->getContent());
        if ($mime) {
            $file->setMimeType($mime);
        }
        $this->saveFile($file);
        return $file;
    }

    public function uploadFile(
        string             $filename,
        string             $fileData,
        bool               $uniqueSuffix = true,
        string             $visibility = Visibility::PRIVATE,
        array              $additionalConfig = [],
        ?StoredFileService $service = null
    ): StoredFile
    {

        $remoteFilename = $filename;
        if ($uniqueSuffix) {
            $info = pathinfo($filename);
            if ($info['dirname'] && $info['dirname'] !== '.') {
                $remoteFilename = $info['dirname'] . DIRECTORY_SEPARATOR . $info['filename'] . '-' . uniqid() . '.' . $info['extension'];
            } else {
                $remoteFilename = $info['filename'] . '-' . uniqid() . '.' . $info['extension'];
            }
        }
        $additionalConfig['params']['visibility'] = $visibility;
        $fileSystem = $this->getFilesystemForService($service);
        $fileSystem->write($remoteFilename, $fileData, $additionalConfig);
        $uri = $fileSystem->publicUrl($remoteFilename);
        $file = new StoredFile();
        $file->setOriginalFilename($filename);
        $file->setDateUploaded(new \DateTimeImmutable());
        $file->setFilename($remoteFilename);
        $file->setBucket('');
        $file->setFilesize(mb_strlen($fileData));
        $file->setFileData($fileData);
        $file->setUri($uri);
        $mime = MimeTypeHelper::guessMimeType($filename, $fileData);
        if ($mime) {
            $file->setMimeType($mime);
        }
        $this->saveFile($file);
        return $file;
    }

    public function hydrateFileData(StoredFile $file): void
    {
        $contents = $this->getFileData($file);
        if ($contents) {
            $file->setFileData($contents);
        }
    }

    public function getFileData(StoredFile $file): string
    {
        $fileSystem = $this->getFilesystemForService($file->getStorageService());
        return $fileSystem->read($file->getFilename());
    }

    public function openFileForUser(StoredFile $file, UserInterface $user, ?\DateTimeImmutable $expiryDate = null): string
    {
        $fileSystem = $this->getFilesystemForService($file->getStorageService());
        if ($expiryDate === null) {
            $expiryDate = new \DateTimeImmutable('+30 minute');
        }
        $uri = $fileSystem->temporaryUrl($file->getFilename(), $expiryDate);
        $this->repository->logFileAccess($file, $user->getId(), $uri, $expiryDate);
        return $uri;
    }

    public function getFileById(int $id): ?StoredFile
    {
        return $this->hydrator->hydrateCached('stored_file_' . $id, StoredFile::class, function () use ($id) {
            return $this->repository->getFileById($id);
        });
    }

    public function getFileByUri(string $uri): ?StoredFile
    {
        return $this->hydrator->hydrateCached('stored_file_uri_' . $uri, StoredFile::class, function () use ($uri) {
            return $this->repository->getFileByUri($uri);
        });
    }

    public function getFileByFilename(string $filename): ?StoredFile
    {
        return $this->hydrator->hydrate(StoredFile::class, $this->repository->getFileByFilename($filename));
    }

    /**
     * @return StoredFile[]
     */
    public function getFilesByFilter(StoredFileFilter $filter): array
    {
        return $this->hydrator->hydrateSet(StoredFile::class, $this->repository->getFilesByFilter($filter));
    }

    public function syncFiles(string $path = '/', ?OutputInterface $output = null, ?StoredFileService $service = null): void
    {
        $fileSystem = $this->getFilesystemForService($service);
        $listing = $fileSystem->listContents($path);
        foreach ($listing->getIterator() as $file) {
            /**
             * @var FileAttributes $file
             */
            if ($file->isFile()) {
                $localFile = $this->getFileByFilename($file->path());
                if (!$localFile) {
                    $newFile = new StoredFile();
                    $newFile->setFilename($file->path());
                    $newFile->setFilesize($file->fileSize());
                    $newFile->setOriginalFilename($file->path());
                    try {
                        $newFile->setUri($fileSystem->publicUrl($file->path()));
                    } catch (FilesystemException $e) {
                        continue;
                    }
                    $uploaded = new \DateTimeImmutable();
                    $mod = $file->lastModified();
                    if ($mod) {
                        $uploaded = \DateTimeImmutable::createFromFormat('U', (string)$mod);
                        if (!$uploaded) {
                            $uploaded = new \DateTimeImmutable();
                        }
                    }
                    $newFile->setDateUploaded($uploaded);
                    $extraData = $file->extraMetadata();
                    if (isset($extraData['ETag'])) {
                        $newFile->setEtag($extraData['ETag']);
                    }
                    $this->saveFile($newFile);
                    if ($output) {
                        $output->writeln('[' . date('d/m/Y H:i:s') . '] File ' . $file->path() . ' has been uploaded');
                    }
                }
            } elseif ($file->type() === 'dir' && $file->path() !== '.' && $file->path() !== '..') {
                $this->syncFiles($file->path(), $output);
            }
        }
    }


    public function saveFile(StoredFile $file): void
    {
        $previous = null;
        if ($file->getId()) {
            $previous = $this->getFileById($file->getId());
        }
        $event = new PreStoredFileSaveEvent();
        $event->setCurrent($file);
        $event->setPrevious($previous);
        $this->dispatcher->dispatch($event);

        $this->repository->saveFile($file);

        $event = new PostStoredFileSaveEvent();
        $event->setCurrent($file);
        $event->setPrevious($previous);
        $this->dispatcher->dispatch($event);
    }

    public function getFilesystemForService(?StoredFileService $service = null): ?Filesystem
    {
        if (!$service) {
            $service = $this->getDefaultStorageService();
        }
        if (!$service) {
            throw new NoFileServiceAvailable('No file service has been configured');
        }
        $key = 'file_storage_service_' . $service->getId();
        if ($this->container->has($key)) {
            return $this->container->get($key);
        }
        $factory = new FileSystemFactory($service->getInterpolatedDsn(), $this->logger, $service->getAdapterOptions(), $service->getOptions());
        $this->container[$key] = $factory->createInstance();

        return $this->container[$key];
    }

    public function getDefaultStorageService(): ?StoredFileService
    {
        return $this->hydrator->hydrate(StoredFileService::class, $this->repository->getDefaultStorageService());
    }
}
