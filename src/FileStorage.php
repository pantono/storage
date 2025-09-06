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
                    $newFile->setUri($this->filesystem->publicUrl($file->path()));
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
}
