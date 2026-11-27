<?php

namespace Lle\EntityFileBundle\Tests\Service;

use Lle\EntityFileBundle\Entity\EntityFile;
use Lle\EntityFileBundle\Entity\EntityFileFolder;
use Lle\EntityFileBundle\Exception\EntityFileFolderException;
use Lle\EntityFileBundle\Tests\DoctrineTestTrait;
use Lle\EntityFileBundle\Tests\Fixtures\FolderedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EntityFileManagerFolderTest extends TestCase
{
    use DoctrineTestTrait;

    public function testFoldersAreOffOnlyWhenNotEnabled(): void
    {
        self::assertTrue($this->manager()->hasFolders());
        self::assertFalse($this->manager(self::FLAT_CONFIG)->hasFolders());
    }

    public function testAFolderNeedsFoldersEnabled(): void
    {
        $manager = $this->manager(self::FLAT_CONFIG);
        $document = $this->document();

        $this->expectException(EntityFileFolderException::class);

        $manager->createFolder($document, "Contracts");
    }

    public function testCreateNestedFolders(): void
    {
        $manager = $this->manager();
        $document = $this->document();

        $contracts = $manager->createFolder($document, " Contracts ");
        $signed = $manager->createFolder($document, "2026", $contracts);
        $this->em->flush();

        self::assertSame("Contracts", $contracts->getName());
        self::assertSame(self::CONFIG, $signed->getConfigName());
        self::assertSame($document->getId(), $signed->getEntityId());
        self::assertSame($contracts, $signed->getParent());
        self::assertSame([$contracts, $signed], $signed->getPath());
        // Sorted by name, whatever the depth
        self::assertSame([$signed, $contracts], $manager->getFolders($document));
    }

    public function testFoldersAreListedByEntityAndConfiguration(): void
    {
        $manager = $this->manager();
        $document = $this->document();
        $otherDocument = $this->document();
        $manager->createFolder($document, "Offers");
        $manager->createFolder($otherDocument, "Offers");
        $this->manager(self::OTHER_CONFIG)->createFolder($document, "Offers");
        $this->em->flush();

        self::assertCount(1, $manager->getFolders($document));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidNames(): iterable
    {
        yield "empty" => [""];
        yield "blank" => ["   "];
        yield "slash" => ["a/b"];
        yield "backslash" => ["a\\b"];
        yield "dot" => ["."];
        yield "dot dot" => [".."];
        yield "too long" => [str_repeat("a", 256)];
    }

    #[DataProvider("provideInvalidNames")]
    public function testInvalidNamesAreRefused(string $name): void
    {
        $manager = $this->manager();
        $document = $this->document();

        $this->expectExceptionMessage(EntityFileFolderException::INVALID_NAME);

        $manager->createFolder($document, $name);
    }

    public function testTwoSiblingsCannotHaveTheSameName(): void
    {
        $manager = $this->manager();
        $document = $this->document();
        $manager->createFolder($document, "Offers");
        $this->em->flush();

        $this->expectExceptionMessage(EntityFileFolderException::DUPLICATE_NAME);

        $manager->createFolder($document, "Offers");
    }

    public function testTheSameNameIsAllowedInAnotherFolder(): void
    {
        $manager = $this->manager();
        $document = $this->document();
        $contracts = $manager->createFolder($document, "Contracts");
        $offers = $manager->createFolder($document, "Offers");
        $this->em->flush();

        $manager->createFolder($document, "2026", $contracts);
        $manager->createFolder($document, "2026", $offers);
        $this->em->flush();

        self::assertCount(4, $manager->getFolders($document));
    }

    public function testAParentOfAnotherEntityIsRefused(): void
    {
        $manager = $this->manager();
        $document = $this->document();
        $foreignFolder = $manager->createFolder($this->document(), "Contracts");
        $this->em->flush();

        $this->expectExceptionMessage(EntityFileFolderException::FOREIGN_FOLDER);

        $manager->createFolder($document, "2026", $foreignFolder);
    }

    public function testRenameFolder(): void
    {
        $manager = $this->manager();
        $document = $this->document();
        $folder = $manager->createFolder($document, "Ofers");
        $manager->createFolder($document, "Contracts");
        $this->em->flush();

        $manager->renameFolder($folder, "Offers");

        self::assertSame("Offers", $folder->getName());
    }

    public function testRenameToASiblingNameIsRefused(): void
    {
        $manager = $this->manager();
        $document = $this->document();
        $folder = $manager->createFolder($document, "Offers");
        $manager->createFolder($document, "Contracts");
        $this->em->flush();

        $this->expectExceptionMessage(EntityFileFolderException::DUPLICATE_NAME);

        $manager->renameFolder($folder, "Contracts");
    }

    public function testMoveFolder(): void
    {
        $manager = $this->manager();
        $document = $this->document();
        $contracts = $manager->createFolder($document, "Contracts");
        $archives = $manager->createFolder($document, "Archives");
        $this->em->flush();

        $manager->moveFolder($archives, $contracts);
        self::assertSame($contracts, $archives->getParent());

        $manager->moveFolder($archives, null);
        self::assertNull($archives->getParent());
    }

    public function testAFolderCannotBeMovedIntoItsDescendant(): void
    {
        $manager = $this->manager();
        $document = $this->document();
        $contracts = $manager->createFolder($document, "Contracts");
        $year = $manager->createFolder($document, "2026", $contracts);
        $month = $manager->createFolder($document, "11", $year);
        $this->em->flush();

        $this->expectExceptionMessage(EntityFileFolderException::INVALID_MOVE);

        $manager->moveFolder($contracts, $month);
    }

    public function testAFolderCannotBeMovedIntoItself(): void
    {
        $manager = $this->manager();
        $document = $this->document();
        $contracts = $manager->createFolder($document, "Contracts");
        $this->em->flush();

        $this->expectExceptionMessage(EntityFileFolderException::INVALID_MOVE);

        $manager->moveFolder($contracts, $contracts);
    }

    public function testMovingNextToASiblingOfTheSameNameIsRefused(): void
    {
        $manager = $this->manager();
        $document = $this->document();
        $contracts = $manager->createFolder($document, "Contracts");
        $manager->createFolder($document, "2026");
        $year = $manager->createFolder($document, "2026", $contracts);
        $this->em->flush();

        $this->expectExceptionMessage(EntityFileFolderException::DUPLICATE_NAME);

        $manager->moveFolder($year, null);
    }

    public function testSaveAFileInAFolder(): void
    {
        $manager = $this->manager();
        $document = $this->document();
        $folder = $manager->createFolder($document, "Contracts");
        $this->em->flush();

        $file = $manager->save($document, "contents", "contract.pdf", $folder);
        $this->em->persist($file);
        $this->em->flush();

        self::assertInstanceOf(FolderedFile::class, $file);
        self::assertSame($folder, $file->getFolder());
        self::assertSame([$file], $manager->getFiles($document, $folder));
        self::assertSame([], $manager->getFiles($document, null));
        self::assertSame([$file], $manager->get($document));
    }

    public function testGetAFileOfAnEntity(): void
    {
        $manager = $this->manager();
        $document = $this->document();
        $file = $manager->save($document, "contents", "contract.pdf");
        $foreignFile = $manager->save($this->document(), "contents", "contract.pdf");
        $this->em->persist($file);
        $this->em->persist($foreignFile);
        $this->em->flush();

        self::assertSame($file, $manager->getFile($document, (int)$file->getId()));
        self::assertNull($manager->getFile($document, (int)$foreignFile->getId()));
        self::assertNull($this->manager(self::OTHER_CONFIG)->getFile($document, (int)$file->getId()));
    }

    public function testAFileOfAFlatConfigurationCannotGoInAFolder(): void
    {
        $manager = $this->manager();
        $document = $this->document();
        $folder = $manager->createFolder($document, "Contracts");
        $this->em->flush();

        $this->expectException(EntityFileFolderException::class);

        $this->manager(self::FLAT_CONFIG)->save($document, "contents", "contract.pdf", $folder);
    }

    public function testMoveAFileToAFolderAndBackToTheRoot(): void
    {
        $manager = $this->manager();
        $document = $this->document();
        $folder = $manager->createFolder($document, "Contracts");
        $file = $manager->save($document, "contents", "contract.pdf");
        $this->em->persist($file);
        $this->em->flush();
        $path = $file->getPath();

        $manager->moveToFolder($file, $folder);
        $this->em->flush();
        self::assertSame([$file], $manager->getFiles($document, $folder));

        $manager->moveToFolder($file, null);
        $this->em->flush();
        self::assertSame([$file], $manager->getFiles($document, null));
        // Folders only exist in the database, the stored file does not move
        self::assertSame($path, $file->getPath());
        self::assertSame("contents", $manager->read($file));
    }

    public function testAFileCannotGoInAFolderOfAnotherEntity(): void
    {
        $manager = $this->manager();
        $document = $this->document();
        $foreignFolder = $manager->createFolder($this->document(), "Contracts");
        $file = $manager->save($document, "contents", "contract.pdf");
        $this->em->persist($file);
        $this->em->flush();

        $this->expectExceptionMessage(EntityFileFolderException::FOREIGN_FOLDER);

        $manager->moveToFolder($file, $foreignFolder);
    }

    public function testDeleteAnEmptyFolder(): void
    {
        $manager = $this->manager();
        $document = $this->document();
        $folder = $manager->createFolder($document, "Contracts");
        $this->em->flush();

        $manager->deleteFolder($folder);

        self::assertSame([], $manager->getFolders($document));
    }

    public function testANonEmptyFolderIsKeptWithoutRecursive(): void
    {
        $manager = $this->manager();
        $document = $this->document();
        $folder = $manager->createFolder($document, "Contracts");
        $manager->createFolder($document, "2026", $folder);
        $this->em->flush();

        $this->expectExceptionMessage(EntityFileFolderException::NOT_EMPTY);

        $manager->deleteFolder($folder);
    }

    public function testAFolderHoldingAFileIsKeptWithoutRecursive(): void
    {
        $manager = $this->manager();
        $document = $this->document();
        $folder = $manager->createFolder($document, "Contracts");
        $this->em->persist($manager->save($document, "contents", "contract.pdf", $folder));
        $this->em->flush();

        $this->expectExceptionMessage(EntityFileFolderException::NOT_EMPTY);

        $manager->deleteFolder($folder);
    }

    public function testDeleteAFolderRecursively(): void
    {
        $manager = $this->manager();
        $document = $this->document();
        $contracts = $manager->createFolder($document, "Contracts");
        $year = $manager->createFolder($document, "2026", $contracts);
        $offers = $manager->createFolder($document, "Offers");
        $deleted = $manager->save($document, "contents", "contract.pdf", $year);
        $kept = $manager->save($document, "contents", "offer.pdf", $offers);
        $this->em->persist($deleted);
        $this->em->persist($kept);
        $this->em->flush();
        $deletedPath = self::CONFIG . "/" . $deleted->getPath();

        $manager->deleteFolder($contracts, true);

        self::assertSame([$offers], $manager->getFolders($document));
        self::assertSame([$kept], $manager->get($document));
        self::assertFalse($this->storage->fileExists($deletedPath));
    }

    public function testDefaultFoldersAreCreatedOnce(): void
    {
        $manager = $this->manager();
        $document = $this->document();
        $manager->createFolder($document, "Offers");
        $this->em->flush();

        $manager->ensureDefaultFolders($document);
        $manager->ensureDefaultFolders($document);

        $names = array_map(fn (EntityFileFolder $folder) => $folder->getName(), $manager->getFolders($document));
        self::assertSame(["Contracts", "Offers"], $names);
    }

    public function testFlatConfigurationsKeepTheDefaultFileClass(): void
    {
        $manager = $this->manager(self::FLAT_CONFIG);
        $document = $this->document();

        self::assertInstanceOf(EntityFile::class, $manager->create($document, "contract.pdf"));
    }
}
