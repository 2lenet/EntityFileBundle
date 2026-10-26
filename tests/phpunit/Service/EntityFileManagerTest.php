<?php

namespace Lle\EntityFileBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Lle\EntityFileBundle\Service\EntityFileManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class EntityFileManagerTest extends TestCase
{
    // 1x1 transparent PNG
    private const PNG = "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==";

    /** @var string[] */
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        array_map("unlink", $this->tmpFiles);
        $this->tmpFiles = [];
    }

    /**
     * @param string[] $mimeTypes
     */
    #[DataProvider("provideFiles")]
    public function testIsAllowed(array $mimeTypes, string $contents, string $name, bool $expected): void
    {
        $manager = $this->createManager($mimeTypes);

        self::assertSame($expected, $manager->isAllowed($this->createUploadedFile($contents, $name)));
    }

    public static function provideFiles(): iterable
    {
        $png = (string)base64_decode(self::PNG);
        $html = "<html><body><script>alert(1)</script></body></html>";
        $csv = "a;b\n1;2\n";

        yield "no restriction" => [[], $html, "page.html", true];
        yield "accepted type and extension" => [["image/png"], $png, "logo.png", true];
        yield "uppercase extension" => [["image/png"], $png, "LOGO.PNG", true];
        yield "HTML named as an image" => [["image/png"], $html, "logo.png", false];
        yield "image named as HTML" => [["image/png"], $png, "logo.html", false];
        yield "no extension" => [["image/png"], $png, "logo", false];
        yield "refused type" => [["application/pdf"], $png, "logo.png", false];
        yield "extension of another accepted type" => [["image/png", "application/pdf"], $png, "logo.pdf", true];
        yield "CSV detected as text/plain, falls back to the extension" => [["text/csv"], $csv, "data.csv", true];
        yield "CSV is not text/plain" => [["text/plain"], $csv, "data.csv", false];
        yield "plain text" => [["text/plain"], "hello", "notes.txt", true];
    }

    public function testGuessMimeType(): void
    {
        $manager = $this->createManager([]);
        $png = (string)base64_decode(self::PNG);

        self::assertSame("text/csv", $manager->guessMimeType($this->createUploadedFile("a;b\n1;2\n", "data.csv")));
        self::assertSame("text/plain", $manager->guessMimeType($this->createUploadedFile("a;b\n1;2\n", "data")));
        // conclusive contents win over the extension
        self::assertSame("image/png", $manager->guessMimeType($this->createUploadedFile($png, "logo.txt")));
    }

    public function testIsAllowedMimeType(): void
    {
        self::assertTrue($this->createManager([])->isAllowedMimeType("text/html"));
        self::assertTrue($this->createManager(["image/png"])->isAllowedMimeType("IMAGE/PNG"));
        self::assertFalse($this->createManager(["image/png"])->isAllowedMimeType("text/html"));
    }

    public function testGetAllowedExtensions(): void
    {
        self::assertSame([], $this->createManager([])->getAllowedExtensions());
        self::assertSame(["png", "csv"], $this->createManager(["image/png", "text/csv"])->getAllowedExtensions());
        // no duplicates
        self::assertSame(
            ["jpg", "jpeg", "jpe", "jfif", "png"],
            $this->createManager(["image/jpeg", "image/pjpeg", "image/png"])->getAllowedExtensions(),
        );
    }

    /**
     * @param string[] $mimeTypes already normalized, see Configuration
     */
    private function createManager(array $mimeTypes): EntityFileManager
    {
        $parameters = new ParameterBag([
            "lle.entity_file.configurations" => [
                "test" => [
                    "storage_adapter" => "storage",
                    "mime_types" => $mimeTypes,
                ],
            ],
        ]);
        $storage = $this->createStub(FilesystemOperator::class);

        return new EntityFileManager(
            $parameters,
            new ServiceLocator(["storage" => fn () => $storage]),
            $this->createStub(EntityManagerInterface::class),
            "test",
        );
    }

    private function createUploadedFile(string $contents, string $name): UploadedFile
    {
        $path = (string)tempnam(sys_get_temp_dir(), "entity_file_test");
        file_put_contents($path, $contents);
        $this->tmpFiles[] = $path;

        return new UploadedFile($path, $name, null, null, true);
    }
}
