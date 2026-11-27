<?php

namespace Lle\EntityFileBundle\Tests\Service;

use Lle\EntityFileBundle\Entity\EntityFilePublicLink;
use Lle\EntityFileBundle\Exception\EntityFileException;
use Lle\EntityFileBundle\Service\EntityFileLoader;
use Lle\EntityFileBundle\Service\EntityFilePublicLinkResolver;
use Lle\EntityFileBundle\Tests\DoctrineTestTrait;
use PHPUnit\Framework\TestCase;

class EntityFilePublicLinkTest extends TestCase
{
    use DoctrineTestTrait;

    public function testPublicLinksAreOffOnlyWhenNotEnabled(): void
    {
        self::assertTrue($this->manager()->hasPublicLinks());
        self::assertFalse($this->manager(self::FLAT_CONFIG)->hasPublicLinks());
    }

    public function testAPublicLinkNeedsPublicLinksEnabled(): void
    {
        $manager = $this->manager(self::FLAT_CONFIG);
        $file = $this->savedFile(self::FLAT_CONFIG);

        $this->expectException(EntityFileException::class);

        $manager->getPublicLink($file);
    }

    public function testAPublicLinkIsCreatedOnceWithARandomToken(): void
    {
        $manager = $this->manager();
        $file = $this->savedFile();
        $otherFile = $this->savedFile();

        $link = $manager->getPublicLink($file);
        $this->em->flush();
        $sameLink = $manager->getPublicLink($file);
        $otherLink = $manager->getPublicLink($otherFile);

        self::assertSame($link, $sameLink);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', (string)$link->getToken());
        self::assertNotSame($link->getToken(), $otherLink->getToken());
        self::assertStringNotContainsString((string)$file->getPath(), (string)$link->getToken());
    }

    public function testATokenLeadsToItsFile(): void
    {
        $manager = $this->manager();
        $file = $this->savedFile();
        $link = $manager->getPublicLink($file);
        $this->em->flush();

        $resolved = $this->resolver()->resolve((string)$link->getToken());

        self::assertNotNull($resolved);
        self::assertSame($file, $resolved[1]);
        self::assertTrue($resolved[0]->hasPublicLinks());
        self::assertNull($this->resolver()->resolve("unknown"));
    }

    public function testATokenStopsWorkingWhenPublicLinksAreDisabled(): void
    {
        $file = $this->savedFile();
        $link = (new EntityFilePublicLink())
            ->setConfigName(self::FLAT_CONFIG)
            ->setFileId((int)$file->getId())
            ->setToken("token-of-a-disabled-configuration");
        $this->em->persist($link);
        $this->em->flush();

        self::assertNull($this->resolver()->resolve("token-of-a-disabled-configuration"));
    }

    public function testDeletingTheFileDeletesItsLink(): void
    {
        $manager = $this->manager();
        $file = $this->savedFile();
        $token = (string)$manager->getPublicLink($file)->getToken();
        $this->em->flush();

        $manager->delete($file);

        self::assertNull($this->resolver()->resolve($token));
        self::assertCount(0, $this->em->getRepository(EntityFilePublicLink::class)->findAll());
    }

    public function testDeletingAFolderRecursivelyDeletesTheLinksOfItsFiles(): void
    {
        $manager = $this->manager();
        $document = $this->document();
        $folder = $manager->createFolder($document, "Contracts");
        $file = $manager->save($document, "contents", "contract.pdf", $folder);
        $this->em->persist($file);
        $this->em->flush();
        $manager->getPublicLink($file);
        $this->em->flush();

        $manager->deleteFolder($folder, true);

        self::assertCount(0, $this->em->getRepository(EntityFilePublicLink::class)->findAll());
    }

    private function resolver(): EntityFilePublicLinkResolver
    {
        return new EntityFilePublicLinkResolver(
            new EntityFileLoader($this->parameters(), $this->storageLocator(), $this->em),
            $this->em,
        );
    }

    private function savedFile(string $configName = self::CONFIG): object
    {
        $file = $this->manager($configName)->save($this->document(), "contents", "contract.pdf");
        $this->em->persist($file);
        $this->em->flush();

        return $file;
    }
}
