<?php

namespace Lle\EntityFileBundle\Service;

use Lle\EntityFileBundle\Controller\EntityFileController;
use Lle\EntityFileBundle\Entity\EntityFileFolder;
use Lle\EntityFileBundle\Entity\EntityFileInterface;
use Lle\EntityFileBundle\Entity\FolderAwareEntityFileInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Files and folders of one entity, as the dropzone displays them: the brick renders it,
 * the folder routes return it after each change.
 */
class EntityFileListing
{
    public const CSRF_TOKEN_ID = "lle_entity_file";
    public const CSRF_HEADER = "X-CSRF-Token";

    public function __construct(
        private EntityFileLoader $loader,
        private UrlGeneratorInterface $urlGenerator,
        private AuthorizationCheckerInterface $authorizationChecker,
        private ?CsrfTokenManagerInterface $csrfTokenManager = null,
    )
    {
    }

    /**
     * Creates the missing "default_folders" first.
     *
     * @param string $configName the configuration
     * @param object $entity the entity the files are attached to
     * @return array<string, mixed>
     */
    public function getListing(string $configName, object $entity): array
    {
        $manager = $this->loader->get($configName);
        $config = $manager->getConfig();
        $entityId = method_exists($entity, "getId") ? $entity->getId() : null;

        $files = [];
        foreach ($manager->get($entity) as $file) {
            if ($this->authorizationChecker->isGranted($config["role"], $file)) {
                $files[] = $this->getFileRow($configName, $file);
            }
        }

        $folders = [];
        if ($manager->hasFolders()) {
            $manager->ensureDefaultFolders($entity);

            foreach ($manager->getFolders($entity) as $folder) {
                $folders[] = $this->getFolderRow($configName, $entityId, $folder);
            }
        }

        return [
            "foldersEnabled" => $manager->hasFolders(),
            "files" => $files,
            "folders" => $folders,
            "createFolderUrl" => $manager->hasFolders()
                ? $this->urlGenerator->generate("lle_entityfile_folder_create", [
                    "configName" => $configName,
                    "id" => $entityId,
                ])
                : null,
            "csrfToken" => $this->csrfTokenManager?->getToken(self::CSRF_TOKEN_ID)->getValue(),
            // Folders are changed with the rights on the files, checked against the entity
            "canWrite" => $this->authorizationChecker->isGranted($config["role_write"], $entity),
            "canDelete" => $this->authorizationChecker->isGranted($config["role_delete"], $entity),
            "canSharePublicLink" => $manager->hasPublicLinks()
                && $this->authorizationChecker->isGranted($config["role_public_link"], $entity),
        ];
    }

    /**
     * @return array<string, mixed> a file, in the format of Dropzone
     */
    public function getFileRow(string $configName, EntityFileInterface $file): array
    {
        $manager = $this->loader->get($configName);
        $mimeType = (string)$file->getMimeType();
        $isImage = str_starts_with($mimeType, "image/");
        $folder = $file instanceof FolderAwareEntityFileInterface ? $file->getFolder() : null;
        $fileParameters = [
            "configName" => $configName,
            "id" => $file->getEntityId(),
            "fileId" => method_exists($file, "getId") ? $file->getId() : null,
        ];

        $row = [
            "url" => $this->urlGenerator->generate("lle_entityfile_entityfile_readbypath", [
                "configName" => $configName,
                "path" => $file->getPath(),
            ]),
            "deleteUrl" => $this->urlGenerator->generate("lle_entityfile_entityfile_deletebypath", [
                "configName" => $configName,
                "path" => $file->getPath(),
            ]),
            "name" => $file->getName(),
            "path" => $file->getPath(),
            "size" => $file->getSize(),
            "resizeThumbnail" => $isImage,
            "disablePreview" => !$isImage,
            "folderId" => $folder?->getId(),
            "mimeType" => $mimeType,
            // Displayed in the browser, even with "disposition: attachment"
            "previewUrl" => EntityFileStreamer::isPreviewable($mimeType) && $manager->isAllowedMimeType($mimeType)
                ? $this->urlGenerator->generate("lle_entityfile_entityfile_readbypath", [
                    "configName" => $configName,
                    "path" => $file->getPath(),
                    EntityFileController::PARAMETER_PREVIEW => 1,
                ])
                : null,
            "thumbnailUrl" => $manager->canThumbnail($file)
                ? $this->urlGenerator->generate("lle_entityfile_file_thumbnail", $fileParameters)
                : null,
            // Creates the link when called, see EntityFileLinkController
            "publicLinkUrl" => $manager->hasPublicLinks() && $fileParameters["fileId"] !== null
                ? $this->urlGenerator->generate("lle_entityfile_file_publiclink", $fileParameters)
                : null,
        ];

        if ($file instanceof FolderAwareEntityFileInterface && method_exists($file, "getId")) {
            $row["moveUrl"] = $this->urlGenerator->generate("lle_entityfile_folder_movefile", $fileParameters);
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function getFolderRow(string $configName, mixed $entityId, EntityFileFolder $folder): array
    {
        $parameters = [
            "configName" => $configName,
            "id" => $entityId,
            "folderId" => $folder->getId(),
        ];

        return [
            "id" => $folder->getId(),
            "name" => $folder->getName(),
            "parentId" => $folder->getParent()?->getId(),
            "renameUrl" => $this->urlGenerator->generate("lle_entityfile_folder_rename", $parameters),
            "moveUrl" => $this->urlGenerator->generate("lle_entityfile_folder_move", $parameters),
            "deleteUrl" => $this->urlGenerator->generate("lle_entityfile_folder_delete", $parameters),
        ];
    }
}
