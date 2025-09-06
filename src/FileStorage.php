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
        bool   $prefixDate = true,
        string $visibility = Visibility::PRIVATE,
        array  $additionalConfig = []
    ): StoredFile
    {

        $remoteFilename = $filename;
        if ($prefixDate) {
            $remoteFilename = date('YmdHis') . $filename;
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
        return $file;
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
        return $this->hydrator->hydrate(StoredFile::class, $this->repository->getFileById($id));
    }

    /**
     * @return StoredFile[]
     */
    public function getFilesByFilter(StoredFileFilter $filter): array
    {
        return $this->hydrator->hydrateSet(StoredFile::class, $this->repository->getFilesByFilter($filter));
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
}
