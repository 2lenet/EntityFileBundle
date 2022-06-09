<?php

namespace Lle\EntityFileBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Lle\EntityFileBundle\Entity\EntityFileInterface;
use Lle\EntityFileBundle\Exception\EntityFileException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\File\File;

class EntityFileManager
{
    private FilesystemOperator $storage;

    private array $config;

    public function __construct(
        private ParameterBagInterface  $parameters,
        ServiceLocator                 $storageLocator,
        private EntityManagerInterface $em,
        private string                 $configName,
    )
    {
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
        return $this->em->getRepository($this->config["entity_file_class"])
            ->findBy([
                "configName" => $this->configName,
                "entityId" => $entity->getId(),
            ]);
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
        return $this->em->getRepository($this->config["entity_file_class"])
            ->findOneBy([
                "configName" => $this->configName,
                "entityId" => $entity->getId(),
            ]);
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
            ->setEntityId($entity->getId())
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
                $this->storage->write($path, $data);
                break;
        }

        $mimeType = $this->storage->mimeType($path);
        $size = $this->storage->fileSize($path);

        $entityFile->setMimeType($mimeType);
        $entityFile->setSize($size);
    }

    protected function getStorage(string $configName): FilesystemOperator
    {
        return $this->storage;
    }

    public function getConfig(): array
    {
        return $this->config;
    }
}
