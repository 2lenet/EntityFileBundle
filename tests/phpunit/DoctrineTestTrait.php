<?php

namespace Lle\EntityFileBundle\Tests;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Gedmo\Timestampable\TimestampableListener;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Lle\EntityFileBundle\Entity\EntityFile;
use Lle\EntityFileBundle\Service\EntityFileManager;
use Lle\EntityFileBundle\Tests\Fixtures\Document;
use Lle\EntityFileBundle\Tests\Fixtures\FolderedFile;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Filesystem\Filesystem as LocalFilesystem;

/**
 * A real EntityManager on an in-memory SQLite database, and a storage in a temporary directory.
 * Three configurations: CONFIG and OTHER_CONFIG with folders, FLAT_CONFIG without.
 */
trait DoctrineTestTrait
{
    private const CONFIG = "documents";
    private const FLAT_CONFIG = "flat";
    private const OTHER_CONFIG = "other";
    private const STORAGE = "storage";
    private const DEFAULT_FOLDERS = ["Contracts", "Offers"];

    private EntityManager $em;

    private Filesystem $storage;

    private string $storageDir;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfig(
            [__DIR__ . "/../../src/Entity", __DIR__ . "/Fixtures"],
            true,
        );
        $config->enableNativeLazyObjects(true);
        // As in Symfony applications: the indexes of EntityFile name "entity_id"
        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER, true));
        $eventManager = new EventManager();
        $eventManager->addEventSubscriber(new TimestampableListener());
        $connection = DriverManager::getConnection(["driver" => "pdo_sqlite", "memory" => true], $config);
        $this->em = new EntityManager($connection, $config, $eventManager);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());

        $this->storageDir = sys_get_temp_dir() . "/entity_file_test_" . uniqid();
        $this->storage = new Filesystem(new LocalFilesystemAdapter($this->storageDir));
    }

    protected function tearDown(): void
    {
        (new LocalFilesystem())->remove($this->storageDir);
    }

    private function parameters(): ParameterBag
    {
        $folders = [
            "entity_file_class" => FolderedFile::class,
            "folders" => true,
            "default_folders" => self::DEFAULT_FOLDERS,
        ];
        $flat = [
            "entity_file_class" => EntityFile::class,
            "folders" => false,
            "default_folders" => [],
        ];
        $common = [
            "class" => Document::class,
            "storage_adapter" => self::STORAGE,
            "role" => "ROLE_READ",
            "role_write" => "ROLE_WRITE",
            "role_delete" => "ROLE_DELETE",
            "disposition" => "inline",
            "included_mime_types" => [],
            "excluded_mime_types" => [],
        ];

        return new ParameterBag([
            "lle.entity_file.configurations" => [
                self::CONFIG => $common + $folders,
                self::OTHER_CONFIG => $common + $folders,
                self::FLAT_CONFIG => $common + $flat,
            ],
        ]);
    }

    private function storageLocator(): ServiceLocator
    {
        $storage = $this->storage;

        return new ServiceLocator([self::STORAGE => fn () => $storage]);
    }

    private function manager(string $configName = self::CONFIG): EntityFileManager
    {
        return new EntityFileManager($this->parameters(), $this->storageLocator(), $this->em, $configName);
    }

    private function document(): Document
    {
        $document = new Document();
        $this->em->persist($document);
        $this->em->flush();

        return $document;
    }
}
