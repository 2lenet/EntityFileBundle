<?php

namespace Lle\EntityFileBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Lle\EntityFileBundle\DependencyInjection\Configuration;
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
    #[DataProvider("provideIncludedFiles")]
    public function testIsAllowedWithIncludedMimeTypes(array $mimeTypes, string $contents, string $name, bool $expected): void
    {
        $manager = $this->createManager($mimeTypes);

        self::assertSame($expected, $manager->isAllowed($this->createUploadedFile($contents, $name)));
    }

    public static function provideIncludedFiles(): iterable
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

    #[DataProvider("provideExcludedFiles")]
    public function testIsAllowedWithDefaultExcludedMimeTypes(string $contents, string $name, bool $expected): void
    {
        $manager = $this->createManager([], Configuration::DEFAULT_EXCLUDED_MIME_TYPES);

        self::assertSame($expected, $manager->isAllowed($this->createUploadedFile($contents, $name)));
    }

    public static function provideExcludedFiles(): iterable
    {
        $png = (string)base64_decode(self::PNG);
        $php = "<?php system(\$_GET['c']);";

        yield "image" => [$png, "logo.png", true];
        yield "CSV" => ["a;b\n1;2\n", "data.csv", true];
        yield "plain text" => ["hello", "notes.txt", true];
        yield "no extension" => [$png, "logo", true];
        yield "PHP" => [$php, "shell.php", false];
        yield "PHP named as an image" => [$php, "logo.png", false];
        yield "PHP with an extension unknown to symfony/mime" => [$php, "shell.phtml", false];
        // finfo detects image/gif, only the extension refuses it
        yield "GIF/PHP polyglot named .phtml" => ["GIF89a<?php system(\$_GET['c']); ?>", "shell.phtml", false];
        yield "GIF/PHP polyglot named .phar" => ["GIF89a<?php system(\$_GET['c']); ?>", "shell.phar", false];
        yield "image named .htaccess" => [$png, ".htaccess", false];
        yield "image named as JSP" => [$png, "logo.jsp", false];
        yield "XML" => ['<?xml version="1.0"?><html xmlns="http://www.w3.org/1999/xhtml"><script>alert(1)</script></html>', "page.xml", false];
        yield "image named as PHP" => [$png, "logo.php", false];
        yield "uppercase extension" => [$png, "LOGO.PHP", false];
        yield "SQL detected as text/plain, falls back to the extension" => ["DROP TABLE users;\n", "dump.sql", false];
        yield "shell script" => ["#!/bin/sh\nrm -rf /\n", "run.sh", false];
        yield "shell script without extension" => ["#!/bin/sh\nrm -rf /\n", "run", false];
        yield "HTML" => ["<html><body><script>alert(1)</script></body></html>", "page.html", false];
        yield "SVG" => ['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', "logo.svg", false];
    }

    public function testEmptyExcludedMimeTypesAcceptEveryType(): void
    {
        $manager = $this->createManager([], []);

        self::assertTrue($manager->isAllowed($this->createUploadedFile("<?php echo 1;", "shell.php")));
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
        self::assertTrue($this->createManager([], ["text/x-php"])->isAllowedMimeType("image/png"));
        self::assertFalse($this->createManager([], ["text/x-php"])->isAllowedMimeType("TEXT/X-PHP"));
    }

    public function testGetAllowedExtensions(): void
    {
        self::assertSame([], $this->createManager([])->getAllowedExtensions());
        self::assertSame(["png", "csv"], $this->createManager(["image/png", "text/csv"])->getAllowedExtensions());

        // image/jpeg and image/pjpeg share their extensions, which vary with the symfony/mime version
        $extensions = $this->createManager(["image/jpeg", "image/pjpeg", "image/png"])->getAllowedExtensions();
        self::assertSame(array_values(array_unique($extensions)), $extensions);
        self::assertContains("jpg", $extensions);
        self::assertContains("png", $extensions);

        self::assertSame([], $this->createManager([], ["application/x-php"])->getAllowedExtensions());
    }

    public function testGetExcludedExtensions(): void
    {
        self::assertSame([], $this->createManager(["image/png"])->getExcludedExtensions());

        $extensions = $this->createManager([], ["application/x-php", "text/x-php", "text/x-perl"])->getExcludedExtensions();
        self::assertContains("php", $extensions);
        // symfony/mime lists "PL" next to "pl"
        self::assertSame(array_values(array_unique(array_map("strtolower", $extensions))), $extensions);

        // extensions without a known type are refused whatever the excluded types
        self::assertContains("phtml", $this->createManager([], ["text/html"])->getExcludedExtensions());
        self::assertSame([], $this->createManager([], [])->getExcludedExtensions());
    }

    public function testPolyglotIsDetectedAsAnImage(): void
    {
        // the reason why DEFAULT_EXCLUDED_EXTENSIONS exists: the contents check alone accepts it
        $manager = $this->createManager([], Configuration::DEFAULT_EXCLUDED_MIME_TYPES);
        $file = $this->createUploadedFile("GIF89a<?php system(\$_GET['c']); ?>", "shell.phtml");

        self::assertSame("image/gif", $manager->guessMimeType($file));
        self::assertTrue($manager->isAllowedMimeType("image/gif"));
        self::assertFalse($manager->isAllowed($file));
    }

    /**
     * @param string[] $includedMimeTypes already normalized, see Configuration
     * @param string[] $excludedMimeTypes already normalized, see Configuration
     */
    private function createManager(array $includedMimeTypes, array $excludedMimeTypes = []): EntityFileManager
    {
        $parameters = new ParameterBag([
            "lle.entity_file.configurations" => [
                "test" => [
                    "storage_adapter" => "storage",
                    "included_mime_types" => $includedMimeTypes,
                    "excluded_mime_types" => $excludedMimeTypes,
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
