<?php

namespace Lle\EntityFileBundle\Tests\Service;

use Lle\EntityFileBundle\Service\EntityFileListing;
use Lle\EntityFileBundle\Service\EntityFileLoader;
use Lle\EntityFileBundle\Tests\DoctrineTestTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class EntityFileListingTest extends TestCase
{
    use DoctrineTestTrait;

    private const TOKEN = "token";

    public function testAFlatConfigurationListsItsFilesWithoutFolders(): void
    {
        $document = $this->document();
        $manager = $this->manager(self::FLAT_CONFIG);
        $this->em->persist($manager->save($document, "contents", "contract.pdf"));
        $this->em->flush();

        $listing = $this->listing()->getListing(self::FLAT_CONFIG, $document);

        self::assertFalse($listing["foldersEnabled"]);
        self::assertSame([], $listing["folders"]);
        self::assertCount(1, $listing["files"]);
        self::assertSame("contract.pdf", $listing["files"][0]["name"]);
        self::assertNull($listing["files"][0]["folderId"]);
        self::assertArrayNotHasKey("moveUrl", $listing["files"][0]);
    }

    public function testTheDefaultFoldersAreCreatedAndListed(): void
    {
        $document = $this->document();

        $listing = $this->listing()->getListing(self::CONFIG, $document);

        self::assertTrue($listing["foldersEnabled"]);
        self::assertSame(self::DEFAULT_FOLDERS, array_column($listing["folders"], "name"));
        self::assertSame([null, null], array_column($listing["folders"], "parentId"));
        self::assertSame(self::TOKEN, $listing["csrfToken"]);
        self::assertSame("route:lle_entityfile_folder_create", $listing["createFolderUrl"]);
    }

    public function testFilesAndFoldersCarryTheirFolderAndUrls(): void
    {
        $document = $this->document();
        $manager = $this->manager();
        $contracts = $manager->createFolder($document, "Contracts");
        $year = $manager->createFolder($document, "2026", $contracts);
        $file = $manager->save($document, "contents", "contract.pdf", $year);
        $this->em->persist($file);
        $this->em->flush();

        $listing = $this->listing()->getListing(self::CONFIG, $document);

        $folders = array_column($listing["folders"], null, "name");
        self::assertSame($contracts->getId(), $folders["2026"]["parentId"]);
        self::assertSame("route:lle_entityfile_folder_rename", $folders["2026"]["renameUrl"]);
        self::assertSame("route:lle_entityfile_folder_move", $folders["2026"]["moveUrl"]);
        self::assertSame("route:lle_entityfile_folder_delete", $folders["2026"]["deleteUrl"]);
        self::assertSame($year->getId(), $listing["files"][0]["folderId"]);
        self::assertSame("route:lle_entityfile_folder_movefile", $listing["files"][0]["moveUrl"]);
    }

    public function testFilesCarryTheirPreviewThumbnailAndPublicLinkUrls(): void
    {
        $document = $this->document();
        $manager = $this->manager();
        $pdf = $manager->save($document, "%PDF-1.4", "contract.pdf");
        $pdf->setMimeType("application/pdf");
        $text = $manager->save($document, "hello", "notes.txt");
        $this->em->persist($pdf);
        $this->em->persist($text);
        $this->em->flush();

        $listing = $this->listing()->getListing(self::CONFIG, $document);

        $files = array_column($listing["files"], null, "name");
        self::assertTrue($listing["canSharePublicLink"]);
        self::assertSame("application/pdf", $files["contract.pdf"]["mimeType"]);
        self::assertSame("route:lle_entityfile_entityfile_readbypath", $files["contract.pdf"]["previewUrl"]);
        self::assertSame("route:lle_entityfile_file_publiclink", $files["contract.pdf"]["publicLinkUrl"]);
        self::assertSame(
            extension_loaded("imagick") ? "route:lle_entityfile_file_thumbnail" : null,
            $files["contract.pdf"]["thumbnailUrl"],
        );
        self::assertNull($files["notes.txt"]["previewUrl"]);
        self::assertNull($files["notes.txt"]["thumbnailUrl"]);
    }

    public function testAFlatConfigurationHasNoPublicLinkNorThumbnail(): void
    {
        $document = $this->document();
        $manager = $this->manager(self::FLAT_CONFIG);
        $file = $manager->save($document, "%PDF-1.4", "contract.pdf");
        $file->setMimeType("application/pdf");
        $this->em->persist($file);
        $this->em->flush();

        $listing = $this->listing()->getListing(self::FLAT_CONFIG, $document);

        self::assertFalse($listing["canSharePublicLink"]);
        self::assertNull($listing["files"][0]["publicLinkUrl"]);
        self::assertNull($listing["files"][0]["thumbnailUrl"]);
        self::assertNotNull($listing["files"][0]["previewUrl"]);
    }

    public function testFilesTheUserCannotReadAreHidden(): void
    {
        $document = $this->document();
        $manager = $this->manager();
        $this->em->persist($manager->save($document, "contents", "contract.pdf"));
        $this->em->flush();

        $listing = $this->listing(false)->getListing(self::CONFIG, $document);

        self::assertSame([], $listing["files"]);
    }

    public function testTheRightsOfTheUserAreGiven(): void
    {
        $document = $this->document();

        $listing = $this->listing(true, false)->getListing(self::CONFIG, $document);

        self::assertFalse($listing["canWrite"]);
        self::assertTrue($listing["canDelete"]);
    }

    private function listing(bool $canRead = true, bool $canWrite = true): EntityFileListing
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method("generate")->willReturnCallback(fn (string $route) => "route:" . $route);

        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method("isGranted")->willReturnCallback(fn (string $role) => match ($role) {
            "ROLE_READ" => $canRead,
            "ROLE_WRITE" => $canWrite,
            default => true,
        });

        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method("getToken")->willReturn(new CsrfToken("id", self::TOKEN));

        return new EntityFileListing(
            new EntityFileLoader($this->parameters(), $this->storageLocator(), $this->em),
            $urlGenerator,
            $authorizationChecker,
            $csrfTokenManager,
        );
    }
}
