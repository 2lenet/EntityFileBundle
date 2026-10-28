<?php

namespace Lle\EntityFileBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Lle\EntityFileBundle\Entity\EntityFileInterface;
use Lle\EntityFileBundle\Exception\EntityFileException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\MimeTypes;

class EntityFileManager
{
    /** Types finfo returns when it cannot tell, the same list as league/mime-type-detection */
    private const INCONCLUSIVE_MIME_TYPES = [
        "application/x-empty",
        "text/plain",
        "text/x-asm",
        "application/octet-stream",
        "inode/x-empty",
    ];

    private FilesystemOperator $storage;

    /** @var array<string, mixed> */
    private array $config;

    public function __construct(
        private ParameterBagInterface  $parameters,
        ServiceLocator                 $storageLocator,
        private EntityManagerInterface $em,
        private string                 $configName,
    )
    {
        /** @var array<string, array<string, mixed|class-string>> $configurations */
        $configurations = $this->parameters->get("lle.entity_file.configurations");

        if (!isset($configurations[$configName])) {
            throw new EntityFileException("Configuration '$configName' does not exist.");
        }

        $this->config = $configurations[$this->configName];
        $this->storage = $storageLocator->get($this->config["storage_adapter"]);
    }

    /**
     * Retrieve all EntityFiles for one entity.
     *
     * @param object $entity the entity to search for
     * @return EntityFileInterface[]
     */
    public function get(object $entity): array
    {
        /** @var class-string $entityClass */
        $entityClass = $this->config["entity_file_class"];

        /** @var EntityFileInterface[] $result */
        $result = $this->em->getRepository($entityClass)
            ->findBy([
                "configName" => $this->configName,
                "entityId" => method_exists($entity, 'getId') ? $entity->getId() : null,
            ]);

        return $result;
    }

    /**
     * Retrieve one EntityFile for one entity.
     * Use only when you are sure there is only one file per entity.
     *
     * @param object $entity the entity to search for
     * @return EntityFileInterface|null
     */
    public function getOne(object $entity): ?EntityFileInterface
    {
        /** @var class-string $entityClass */
        $entityClass = $this->config["entity_file_class"];

        /** @var EntityFileInterface|null $result */
        $result = $this->em->getRepository($entityClass)
            ->findOneBy([
                "configName" => $this->configName,
                "entityId" => method_exists($entity, 'getId') ? $entity->getId() : null,
            ]);

        return $result;
    }

    /**
     * @param EntityFileInterface $entityFile the EntityFile to read
     * @return string
     */
    public function read(EntityFileInterface $entityFile): string
    {
        return $this->storage->read($this->configName . "/" . $entityFile->getPath());
    }

    /**
     * @param EntityFileInterface $entityFile the EntityFile to read
     * @return resource
     */
    public function readStream(EntityFileInterface $entityFile)
    {
        return $this->storage->readStream($this->configName . "/" . $entityFile->getPath());
    }

    /**
     * Create and write an EntityFile
     *
     * @param object $entity entity to link to the file
     * @param resource|File|string $data file contents
     * @param string $name name of the file
     * @return EntityFileInterface the new EntityFile
     */
    public function save(object $entity, $data, string $name): EntityFileInterface
    {
        $entityFile = $this->create($entity, $name);
        $this->write($entityFile, $data);

        return $entityFile;
    }

    /**
     * Delete an EntityFile
     *
     * @param EntityFileInterface $entityFile the entity file to delete
     * @return void
     */
    public function delete(EntityFileInterface $entityFile): void
    {
        $this->storage->delete($this->configName . "/" . $entityFile->getPath());

        $this->em->remove($entityFile);
        $this->em->flush();
    }

    /**
     * Move an EntityFile. Always use this method if you want to rename a file.
     *
     * @param EntityFileInterface $entityFile the EntityFile to move
     * @param string $path the new path
     * @return void
     */
    public function move(EntityFileInterface $entityFile, string $path): void
    {
        $this->storage->move(
            $this->configName . "/" . $entityFile->getPath(),
            $this->configName . "/" . $path
        );

        $entityFile->setPath($path);
    }

    /**
     * Create an EntityFile
     *
     * @param object $entity entity to link to the file
     * @param string $name name of the file
     * @return EntityFileInterface the new EntityFile
     */
    public function create(object $entity, string $name): EntityFileInterface
    {
        /** @var EntityFileInterface $entityFile */
        $entityFile = new $this->config["entity_file_class"]();

        $entityFile
            ->setEntityId(method_exists($entity, 'getId') ? $entity->getId() : null)
            ->setConfigName($this->configName)
            ->setName($name)
            ->setPath(time() . "_" . $name);

        return $entityFile;
    }

    /**
     * Write an EntityFile
     *
     * @param EntityFileInterface $entityFile the EntityFile
     * @param resource|File|string $data file contents
     * @return void
     */
    public function write(EntityFileInterface $entityFile, $data): void
    {
        $path = $this->configName . "/" . $entityFile->getPath();

        switch (true) {
            case is_resource($data):
                $this->storage->writeStream($path, $data);
                break;
            case $data instanceof File:
                $this->storage->write($path, $data->getContent());
                break;
            case is_string($data):
            default:
                $this->storage->write($path, (string)$data);
                break;
        }

        // Same detection as isAllowed(), so that the stored type is the one that was checked
        $mimeType = ($data instanceof File ? $this->guessMimeType($data) : null) ?? $this->storage->mimeType($path);
        $size = $this->storage->fileSize($path);

        $entityFile->setMimeType($mimeType);
        $entityFile->setSize($size);
    }

    /**
     * Check a file against the "mime_types" option: its type (see guessMimeType()) must be accepted,
     * and its extension must belong to one of the accepted types, not necessarily to its own type.
     * Refuses both "photo.jpg" holding HTML and a JPEG named "photo.html".
     *
     * @param File $file the file to check
     * @return bool
     */
    public function isAllowed(File $file): bool
    {
        if ($this->getMimeTypes() === []) {
            return true;
        }

        $mimeType = $this->guessMimeType($file);

        return $mimeType !== null
            && $this->isAllowedMimeType($mimeType)
            && in_array($this->getExtension($file), $this->getAllowedExtensions(), true);
    }

    /**
     * Detect the type from the contents. When the contents are inconclusive (plain text, binary...),
     * fall back to the extension: a CSV is "text/csv", not "text/plain".
     *
     * @param File $file the file to check
     * @return string|null null if the contents could not be read, the extension alone is not trusted
     */
    public function guessMimeType(File $file): ?string
    {
        $mimeType = $file->getMimeType();

        if ($mimeType !== null && in_array($mimeType, self::INCONCLUSIVE_MIME_TYPES, true)) {
            $mimeType = MimeTypes::getDefault()->getMimeTypes($this->getExtension($file))[0] ?? $mimeType;
        }

        return $mimeType;
    }

    /**
     * @param string $mimeType the MIME type to check
     * @return bool true if the "mime_types" option is empty or contains this type
     */
    public function isAllowedMimeType(string $mimeType): bool
    {
        return $this->getMimeTypes() === [] || in_array(strtolower($mimeType), $this->getMimeTypes(), true);
    }

    /**
     * @return string[] the extensions of the accepted MIME types, empty if every type is accepted
     */
    public function getAllowedExtensions(): array
    {
        $mimeTypes = MimeTypes::getDefault();

        return array_values(array_unique(array_merge(
            [],
            ...array_map(fn (string $type) => $mimeTypes->getExtensions($type), $this->getMimeTypes())
        )));
    }

    /** @return string[] lowercase, see Configuration */
    private function getMimeTypes(): array
    {
        /** @var string[] */
        return $this->config["mime_types"] ?? [];
    }

    private function getExtension(File $file): string
    {
        return strtolower($file instanceof UploadedFile ? $file->getClientOriginalExtension() : $file->getExtension());
    }

    protected function getStorage(string $configName): FilesystemOperator
    {
        return $this->storage;
    }

    /** @return array<string, mixed> */
    public function getConfig(): array
    {
        return $this->config;
    }
}
