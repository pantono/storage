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

class FileStorage
{
    private FileStorageRepository $repository;
    private Hydrator $hydrator;
    private EventDispatcher $dispatcher;
    private Filesystem $filesystem;

    public function __construct(
        FileStorageRepository $repository,
        Hydrator              $hydrator,
        EventDispatcher       $dispatcher,
        Filesystem            $filesystem
    )
    {
        $this->repository = $repository;
        $this->hydrator = $hydrator;
        $this->dispatcher = $dispatcher;
        $this->filesystem = $filesystem;
    }

    public function uploadFile(
        string $filename,
        string $fileData,
        bool   $uniqueSuffix = true,
        string $visibility = Visibility::PRIVATE,
        array  $additionalConfig = []
    ): StoredFile
    {

        $remoteFilename = $filename;
        if ($uniqueSuffix) {
            $info = pathinfo($filename);
            if ($info['dirname']) {
                $remoteFilename = $info['dirname'] . DIRECTORY_SEPARATOR . $info['filename'] . '-' . uniqid() . '.' . $info['extension'];
            } else {
                $remoteFilename = $info['filename'] . '-' . uniqid() . '.' . $info['extension'];
            }
        }
        $additionalConfig['visibility'] = $visibility;
        $this->filesystem->write($remoteFilename, $fileData, $additionalConfig);
        $uri = $this->filesystem->publicUrl($remoteFilename);
        $file = new StoredFile();
        $file->setOriginalFilename($filename);
        $file->setDateUploaded(new \DateTimeImmutable());
        $file->setFilename($remoteFilename);
        $file->setBucket('');
        $file->setFilesize(mb_strlen($fileData));
        $file->setFileData($fileData);
        $file->setUri($uri);
        $this->saveFile($file);
        $mime = $this->getMimeType($fileData);
        if ($mime) {
            $file->setMimeType($mime);
        }
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
        return $this->filesystem->read($file->getFilename());
    }

    public function openFileForUser(StoredFile $storedFile, UserInterface $user, ?\DateTimeImmutable $expiryDate = null): string
    {
        if ($expiryDate === null) {
            $expiryDate = new \DateTimeImmutable('+30 minute');
        }
        $uri = $this->filesystem->temporaryUrl($storedFile->getFilename(), $expiryDate);
        $this->repository->logFileAccess($storedFile, $user->getId(), $uri, $expiryDate);
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

    public function syncFiles(string $path = '/', ?OutputInterface $output = null): void
    {
        $listing = $this->filesystem->listContents($path);
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
                        $newFile->setUri($this->filesystem->publicUrl($file->path()));
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

    private function getMimeType(string $fileData): ?string
    {
        if ($fileData === '') {
            return null;
        }

        $fInfo = new \finfo(FILEINFO_MIME_TYPE);
        return $fInfo->buffer($fileData) ?: null;
    }
}
