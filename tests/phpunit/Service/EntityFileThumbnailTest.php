<?php

namespace Lle\EntityFileBundle\Tests\Service;

use Lle\EntityFileBundle\Entity\EntityFileInterface;
use Lle\EntityFileBundle\Tests\DoctrineTestTrait;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

class EntityFileThumbnailTest extends TestCase
{
    use DoctrineTestTrait;

    private const PDF = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
        . "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
        . "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 400 600]>>endobj\n"
        . "trailer<</Root 1 0 R>>\n%%EOF\n";
    private const JPEG_MAGIC = "\xFF\xD8\xFF";

    public function testThumbnailsAreOffOnlyWhenNotEnabled(): void
    {
        self::assertTrue($this->manager()->hasThumbnails());
        self::assertFalse($this->manager(self::FLAT_CONFIG)->hasThumbnails());
        self::assertNull($this->manager(self::FLAT_CONFIG)->getThumbnail($this->savedFile(self::PDF, "a.pdf", self::FLAT_CONFIG)));
    }

    public function testOnlyPdfFilesHaveAServerThumbnail(): void
    {
        $file = $this->savedFile("hello", "notes.txt");

        self::assertFalse($this->manager()->canThumbnail($file));
        self::assertNull($this->manager()->getThumbnail($file));
    }

    #[RequiresPhpExtension("imagick")]
    public function testThePdfThumbnailIsAJpegKeptInTheStorage(): void
    {
        $manager = $this->manager();
        $file = $this->savedFile(self::PDF, "contract.pdf");

        $thumbnail = $manager->getThumbnail($file);

        self::assertTrue($manager->canThumbnail($file));
        self::assertNotNull($thumbnail);
        self::assertStringStartsWith(self::JPEG_MAGIC, $thumbnail);
        self::assertTrue($this->storage->fileExists($this->thumbnailPath($file)));
        self::assertSame($thumbnail, $manager->getThumbnail($file));
    }

    #[RequiresPhpExtension("imagick")]
    public function testAnUnreadablePdfHasNoThumbnail(): void
    {
        $file = $this->savedFile("%PDF-1.4 broken", "broken.pdf");

        self::assertNull($this->manager()->getThumbnail($file));
        self::assertFalse($this->storage->fileExists($this->thumbnailPath($file)));
    }

    #[RequiresPhpExtension("imagick")]
    public function testWritingOrDeletingTheFileDropsItsThumbnail(): void
    {
        $manager = $this->manager();
        $file = $this->savedFile(self::PDF, "contract.pdf");
        $manager->getThumbnail($file);

        $manager->write($file, self::PDF);
        self::assertFalse($this->storage->fileExists($this->thumbnailPath($file)));

        $manager->getThumbnail($file);
        $manager->delete($file);
        self::assertFalse($this->storage->fileExists($this->thumbnailPath($file)));
    }

    private function thumbnailPath(EntityFileInterface $file): string
    {
        return self::CONFIG . "/.thumbnails/" . $file->getId() . ".jpg";
    }

    private function savedFile(string $contents, string $name, string $configName = self::CONFIG): EntityFileInterface
    {
        $file = $this->manager($configName)->save($this->document(), $contents, $name);
        $this->em->persist($file);
        $this->em->flush();

        return $file;
    }
}
