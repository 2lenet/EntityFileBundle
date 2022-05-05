<?php

namespace Lle\EntityFileBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Lle\EntityFileBundle\Entity\EntityFileInterface;
use Lle\EntityFileBundle\Exception\EntityFileException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\File\File;

class EntityFileStorage
{
    private FilesystemOperator $storage;

    private array $config;

    public function __construct(
        private ParameterBagInterface  $parameters,
        private ServiceLocator         $storageLocator,
        private EntityManagerInterface $em,
        private string                 $configName,
    )
    {
        $this->config = $this->getConfiguration($this->configName);
        $this->storage = $this->getStorage($this->configName);
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
     * Create and write an EntityFile
     *
     * @param object $entity entity to link to the file
     * @param resource|File|string $data file contents
     * @param string $path path/name of the file
     * @return EntityFileInterface the new EntityFile
     */
    public function save(object $entity, $data, string $path): EntityFileInterface
    {
        $entityFile = $this->create($entity, $path);
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
     * @param string $path path/name of the file
     * @return EntityFileInterface the new EntityFile
     */
    public function create(object $entity, string $path): EntityFileInterface
    {
        /** @var EntityFileInterface $entityFile */
        $entityFile = new $this->config["entity_file_class"]();

        $entityFile
            ->setEntityId($entity->getId())
            ->setConfigName($this->configName)
            ->setPath($path);

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

    /**
     * Retrieve a configuration's storage
     *
     * @param string $configName the configuration name
     * @return FilesystemOperator the storage operator (writer/reader)
     * @throws EntityFileException if the configuration does not exist
     */
    protected function getStorage(string $configName): FilesystemOperator
    {
        $storageAdapter = $this->getConfiguration($configName)["storage_adapter"];

        return $this->storageLocator->get($storageAdapter);
    }

    /**
     * Retrieve a configuration by name
     *
     * @param string $configName the configuration name
     * @return array the configuration
     * @throws EntityFileException if the configuration does not exist
     */
    protected function getConfiguration(string $configName): array
    {
        $configurations = $this->parameters->get("lle.entity_file.configurations");

        if (!isset($configurations[$configName])) {
            throw new EntityFileException("Configuration '$configName' does not exist.");
        }

        return $configurations[$configName];
    }
}
