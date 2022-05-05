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
    public function __construct(
        private ParameterBagInterface $parameters,
        private ServiceLocator $storageLocator,
        private EntityManagerInterface $em,
    )
    {
    }

    /**
     * Retrieve all EntityFiles for one entity.
     *
     * @param string $configName the configuration name
     * @param object $entity the entity to search for
     * @return EntityFileInterface[]
     */
    public function get(string $configName, object $entity): array
    {
        $config = $this->getConfiguration($configName);

        return $this->em->getRepository($config["entity_file_class"])
            ->findBy([
                "configName" => $configName,
                "entityId" => $entity->getId(),
            ]);
    }

    /**
     * Retrieve one EntityFile for one entity.
     * Use only when you are sure there is only one file per entity.
     *
     * @param string $configName the configuration name
     * @param object $entity the entity to search for
     * @return EntityFileInterface|null
     */
    public function getOne(string $configName, object $entity): ?EntityFileInterface
    {
        $config = $this->getConfiguration($configName);

        return $this->em->getRepository($config["entity_file_class"])
            ->findOneBy([
                "configName" => $configName,
                "entityId" => $entity->getId(),
            ]);
    }

    /**
     * Create and write an EntityFile
     *
     * @param string $configName the configuration name
     * @param object $entity entity to link to the file
     * @param resource|File|string $data file contents
     * @param string $path path/name of the file
     * @return EntityFileInterface the new EntityFile
     */
    public function save(string $configName, object $entity, $data, string $path): EntityFileInterface
    {
        $entityFile = $this->create($configName, $entity, $path);
        $this->write($configName, $entityFile, $data);

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
        $storage = $this->getStorage($entityFile->getConfigName());
        $storage->delete($entityFile->getConfigName() . "/" . $entityFile->getPath());

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
        $storage = $this->getStorage($entityFile->getConfigName());

        $storage->move(
            $entityFile->getConfigName() . "/" . $entityFile->getPath(),
            $entityFile->getConfigName() . "/" . $path
        );

        $entityFile->setPath($path);
    }

    /**
     * Create an EntityFile
     *
     * @param string $configName the configuration name
     * @param object $entity entity to link to the file
     * @param string $path path/name of the file
     * @return EntityFileInterface the new EntityFile
     */
    public function create(string $configName, object $entity, string $path): EntityFileInterface
    {
        $config = $this->getConfiguration($configName);

        /** @var EntityFileInterface $entityFile */
        $entityFile = new $config["entity_file_class"]();

        $entityFile
            ->setEntityId($entity->getId())
            ->setConfigName($configName)
            ->setPath($path);

        return $entityFile;
    }

    /**
     * Write an EntityFile
     *
     * @param string $configName the configuration name
     * @param EntityFileInterface $entityFile the EntityFile
     * @param resource|File|string $data file contents
     * @return void
     */
    public function write(string $configName, EntityFileInterface $entityFile, $data): void
    {
        $storage = $this->getStorage($configName);
        $path = $configName . "/" . $entityFile->getPath();

        switch (true) {
            case is_resource($data):
                $storage->writeStream($path, $data);
                break;
            case $data instanceof File:
                $storage->write($path, $data->getContent());
                break;
            case is_string($data):
            default:
                $storage->write($path, $data);
                break;
        }

        $mimeType = $storage->mimeType($path);
        $size = $storage->fileSize($path);

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
