<?php

namespace Lle\EntityFileBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Lle\EntityFileBundle\DependencyInjection\Configuration;
use Lle\EntityFileBundle\Entity\EntityFileFolder;
use Lle\EntityFileBundle\Entity\EntityFileInterface;
use Lle\EntityFileBundle\Entity\EntityFilePublicLink;
use Lle\EntityFileBundle\Entity\FolderAwareEntityFileInterface;
use Lle\EntityFileBundle\Exception\EntityFileException;
use Lle\EntityFileBundle\Exception\EntityFileFolderException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\MimeTypes;

class EntityFileManager
{
    private const THUMBNAIL_DIRECTORY = ".thumbnails";
    private const THUMBNAIL_WIDTH = 240;
    private const THUMBNAIL_QUALITY = 80;
    private const PDF_MIME_TYPE = "application/pdf";

    /** Types finfo returns when it cannot tell, the same list as league/mime-type-detection */
    private const INCONCLUSIVE_MIME_TYPES = [
        "application/x-empty",
        "text/plain",
        "text/x-asm",
        "application/octet-stream",
        "inode/x-empty",
    ];

    private FilesystemOperator $storage;

    /** @var array<string, mixed> */
    private array $config;

    public function __construct(
        private ParameterBagInterface  $parameters,
        ServiceLocator                 $storageLocator,
        private EntityManagerInterface $em,
        private string                 $configName,
    )
    {
        /** @var array<string, array<string, mixed|class-string>> $configurations */
        $configurations = $this->parameters->get("lle.entity_file.configurations");

        if (!isset($configurations[$configName])) {
            throw new EntityFileException("Configuration '$configName' does not exist.");
        }

        $this->config = $configurations[$this->configName];
        $this->storage = $storageLocator->get($this->config["storage_adapter"]);
    }

    /**
     * Retrieve all EntityFiles for one entity.
     *
     * @param object $entity the entity to search for
     * @return EntityFileInterface[]
     */
    public function get(object $entity): array
    {
        /** @var class-string $entityClass */
        $entityClass = $this->config["entity_file_class"];

        /** @var EntityFileInterface[] $result */
        $result = $this->em->getRepository($entityClass)
            ->findBy([
                "configName" => $this->configName,
                "entityId" => $this->getEntityId($entity),
            ]);

        return $result;
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
        /** @var class-string $entityClass */
        $entityClass = $this->config["entity_file_class"];

        /** @var EntityFileInterface|null $result */
        $result = $this->em->getRepository($entityClass)
            ->findOneBy([
                "configName" => $this->configName,
                "entityId" => $this->getEntityId($entity),
            ]);

        return $result;
    }

    /**
     * @param EntityFileInterface $entityFile the EntityFile to read
     * @return string
     */
    public function read(EntityFileInterface $entityFile): string
    {
        return $this->storage->read($this->configName . "/" . $entityFile->getPath());
    }

    /**
     * @param EntityFileInterface $entityFile the EntityFile to read
     * @return resource
     */
    public function readStream(EntityFileInterface $entityFile)
    {
        return $this->storage->readStream($this->configName . "/" . $entityFile->getPath());
    }

    /**
     * Create and write an EntityFile
     *
     * @param object $entity entity to link to the file
     * @param resource|File|string $data file contents
     * @param string $name name of the file
     * @param EntityFileFolder|null $folder folder of the file, null for the root (configurations with folders only)
     * @return EntityFileInterface the new EntityFile
     */
    public function save(object $entity, $data, string $name, ?EntityFileFolder $folder = null): EntityFileInterface
    {
        $entityFile = $this->create($entity, $name, $folder);
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
        $this->removeFile($entityFile);
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
     * @param string $name name of the file
     * @param EntityFileFolder|null $folder folder of the file, null for the root (configurations with folders only)
     * @return EntityFileInterface the new EntityFile
     */
    public function create(object $entity, string $name, ?EntityFileFolder $folder = null): EntityFileInterface
    {
        /** @var EntityFileInterface $entityFile */
        $entityFile = new $this->config["entity_file_class"]();

        $entityFile
            ->setEntityId($this->getEntityId($entity))
            ->setConfigName($this->configName)
            ->setName($name)
            ->setPath(time() . "_" . $name);

        if ($folder !== null) {
            $this->moveToFolder($entityFile, $folder);
        }

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
        // The contents change: the thumbnail is generated again
        $this->deleteThumbnail($entityFile);

        switch (true) {
            case is_resource($data):
                $this->storage->writeStream($path, $data);
                break;
            case $data instanceof File:
                $this->storage->write($path, $data->getContent());
                break;
            case is_string($data):
            default:
                $this->storage->write($path, (string)$data);
                break;
        }

        // Same detection as isAllowed(), so that the stored type is the one that was checked
        $mimeType = ($data instanceof File ? $this->guessMimeType($data) : null) ?? $this->storage->mimeType($path);
        $size = $this->storage->fileSize($path);

        $entityFile->setMimeType($mimeType);
        $entityFile->setSize($size);
    }

    /**
     * Check a file against the "included_mime_types" or "excluded_mime_types" option.
     *
     * With "included_mime_types": its type (see guessMimeType()) must be included, and its extension
     * must belong to one of the included types, not necessarily to its own type.
     * Refuses both "photo.jpg" holding HTML and a JPEG named "photo.html".
     *
     * With "excluded_mime_types": its type must not be excluded, and its extension must not belong
     * to an excluded type nor to Configuration::DEFAULT_EXCLUDED_EXTENSIONS. Refuses "photo.jpg" holding PHP,
     * a JPEG named "photo.php" and a GIF holding PHP named "photo.phtml".
     *
     * @param File $file the file to check
     * @return bool
     */
    public function isAllowed(File $file): bool
    {
        if ($this->getIncludedMimeTypes() === [] && $this->getExcludedMimeTypes() === []) {
            return true;
        }

        $mimeType = $this->guessMimeType($file);

        if ($mimeType === null || !$this->isAllowedMimeType($mimeType)) {
            return false;
        }

        $extension = $this->getExtension($file);

        return $this->getIncludedMimeTypes() !== []
            ? in_array($extension, $this->getAllowedExtensions(), true)
            : !in_array($extension, $this->getExcludedExtensions(), true);
    }

    /**
     * Detect the type from the contents. When the contents are inconclusive (plain text, binary...),
     * fall back to the extension: a CSV is "text/csv", not "text/plain".
     *
     * @param File $file the file to check
     * @return string|null null if the contents could not be read, the extension alone is not trusted
     */
    public function guessMimeType(File $file): ?string
    {
        $mimeType = $file->getMimeType();

        if ($mimeType !== null && in_array($mimeType, self::INCONCLUSIVE_MIME_TYPES, true)) {
            $mimeType = MimeTypes::getDefault()->getMimeTypes($this->getExtension($file))[0] ?? $mimeType;
        }

        return $mimeType;
    }

    /**
     * @param string $mimeType the MIME type to check
     * @return bool true if this type is included, or not excluded when no inclusion list is set
     */
    public function isAllowedMimeType(string $mimeType): bool
    {
        $mimeType = strtolower($mimeType);

        if ($this->getIncludedMimeTypes() !== []) {
            return in_array($mimeType, $this->getIncludedMimeTypes(), true);
        }

        return !in_array($mimeType, $this->getExcludedMimeTypes(), true);
    }

    /**
     * @return string[] the extensions of the included MIME types, empty if no inclusion list is set
     */
    public function getAllowedExtensions(): array
    {
        return $this->getExtensions($this->getIncludedMimeTypes());
    }

    /**
     * @return string[] the extensions of the excluded MIME types and Configuration::DEFAULT_EXCLUDED_EXTENSIONS,
     *                  empty if an inclusion list is set or the exclusion list is empty
     */
    public function getExcludedExtensions(): array
    {
        if ($this->getExcludedMimeTypes() === []) {
            return [];
        }

        return array_values(array_unique(array_merge(
            $this->getExtensions($this->getExcludedMimeTypes()),
            Configuration::DEFAULT_EXCLUDED_EXTENSIONS,
        )));
    }

    /**
     * @param string[] $mimeTypes
     * @return string[] lowercase
     */
    private function getExtensions(array $mimeTypes): array
    {
        $knownTypes = MimeTypes::getDefault();

        return array_values(array_unique(array_map("strtolower", array_merge(
            [],
            ...array_map(fn (string $type) => $knownTypes->getExtensions($type), $mimeTypes)
        ))));
    }

    /** @return string[] lowercase, see Configuration */
    private function getIncludedMimeTypes(): array
    {
        /** @var string[] */
        return $this->config["included_mime_types"] ?? [];
    }

    /** @return string[] lowercase, empty if an inclusion list is set, see Configuration */
    private function getExcludedMimeTypes(): array
    {
        /** @var string[] */
        return $this->config["excluded_mime_types"] ?? [];
    }

    private function getExtension(File $file): string
    {
        return strtolower($file instanceof UploadedFile ? $file->getClientOriginalExtension() : $file->getExtension());
    }

    /**
     * @return bool true if the files of this configuration can be sorted in folders ("folders" option)
     */
    public function hasFolders(): bool
    {
        return (bool)($this->config["folders"] ?? false);
    }

    /**
     * Retrieve all the folders of one entity, whatever their depth, sorted by name.
     *
     * @param object $entity the entity to search for
     * @return EntityFileFolder[]
     */
    public function getFolders(object $entity): array
    {
        return $this->em->getRepository(EntityFileFolder::class)->findBy([
            "configName" => $this->configName,
            "entityId" => $this->getEntityId($entity),
        ], ["name" => "ASC", "id" => "ASC"]);
    }

    /**
     * Retrieve one folder of one entity.
     *
     * @param object $entity the entity the folder must belong to
     * @param int $id id of the folder
     * @return EntityFileFolder|null null if there is no such folder for this entity in this configuration
     */
    public function getFolder(object $entity, int $id): ?EntityFileFolder
    {
        return $this->em->getRepository(EntityFileFolder::class)->findOneBy([
            "id" => $id,
            "configName" => $this->configName,
            "entityId" => $this->getEntityId($entity),
        ]);
    }

    /**
     * Retrieve one file of one entity.
     *
     * @param object $entity the entity the file must belong to
     * @param int $id id of the file
     * @return EntityFileInterface|null null if there is no such file for this entity in this configuration
     */
    public function getFile(object $entity, int $id): ?EntityFileInterface
    {
        /** @var class-string $entityClass */
        $entityClass = $this->config["entity_file_class"];

        /** @var EntityFileInterface|null $result */
        $result = $this->em->getRepository($entityClass)->findOneBy([
            "id" => $id,
            "configName" => $this->configName,
            "entityId" => $this->getEntityId($entity),
        ]);

        return $result;
    }

    /**
     * Retrieve the files of one entity directly in one folder (not in its subfolders).
     *
     * @param object $entity the entity to search for
     * @param EntityFileFolder|null $folder the folder, null for the root
     * @return EntityFileInterface[]
     */
    public function getFiles(object $entity, ?EntityFileFolder $folder): array
    {
        $this->checkFolders();

        /** @var class-string $entityClass */
        $entityClass = $this->config["entity_file_class"];

        /** @var EntityFileInterface[] $result */
        $result = $this->em->getRepository($entityClass)->findBy([
            "configName" => $this->configName,
            "entityId" => $this->getEntityId($entity),
            "folder" => $folder,
        ]);

        return $result;
    }

    /**
     * Create a folder. It is persisted, flush to save it.
     *
     * @param object $entity entity to link to the folder
     * @param string $name name of the folder, trimmed
     * @param EntityFileFolder|null $parent parent folder, null for the root
     * @return EntityFileFolder the new folder
     * @throws EntityFileFolderException if the name is not valid or already used in the parent
     */
    public function createFolder(object $entity, string $name, ?EntityFileFolder $parent = null): EntityFileFolder
    {
        $this->checkFolders();

        $folder = (new EntityFileFolder())
            ->setConfigName($this->configName)
            ->setEntityId($this->getEntityId($entity));

        if ($parent !== null) {
            $this->checkOwner($parent, $folder->getEntityId());
        }

        $name = $this->checkName($name);
        $this->checkUniqueName($folder, $name, $parent);

        $folder->setName($name)->setParent($parent);
        $this->em->persist($folder);

        return $folder;
    }

    /**
     * Create the missing "default_folders" at the root of an entity, and flush if any was created.
     *
     * @param object $entity the entity
     * @return void
     */
    public function ensureDefaultFolders(object $entity): void
    {
        /** @var string[] $names */
        $names = $this->config["default_folders"] ?? [];

        if (!$this->hasFolders() || $names === []) {
            return;
        }

        $existing = [];
        foreach ($this->getFolders($entity) as $folder) {
            if ($folder->getParent() === null) {
                $existing[] = $folder->getName();
            }
        }

        $created = false;
        foreach ($names as $name) {
            if (!in_array(trim($name), $existing, true)) {
                $this->createFolder($entity, $name);
                $created = true;
            }
        }

        if ($created) {
            $this->em->flush();
        }
    }

    /**
     * Rename a folder, flush to save it.
     *
     * @param EntityFileFolder $folder the folder
     * @param string $name the new name, trimmed
     * @return void
     * @throws EntityFileFolderException if the name is not valid or already used in the parent
     */
    public function renameFolder(EntityFileFolder $folder, string $name): void
    {
        $this->checkFolders();
        $this->checkOwner($folder, $folder->getEntityId());

        $name = $this->checkName($name);
        $this->checkUniqueName($folder, $name, $folder->getParent());

        $folder->setName($name);
    }

    /**
     * Move a folder with its contents, flush to save it.
     *
     * @param EntityFileFolder $folder the folder
     * @param EntityFileFolder|null $parent the new parent, null for the root
     * @return void
     * @throws EntityFileFolderException if the parent is the folder itself or one of its subfolders,
     *     or already holds a folder of this name
     */
    public function moveFolder(EntityFileFolder $folder, ?EntityFileFolder $parent): void
    {
        $this->checkFolders();
        $this->checkOwner($folder, $folder->getEntityId());

        if ($parent !== null) {
            $this->checkOwner($parent, $folder->getEntityId());

            if ($parent->isIn($folder)) {
                throw new EntityFileFolderException(EntityFileFolderException::INVALID_MOVE);
            }
        }

        $this->checkUniqueName($folder, (string)$folder->getName(), $parent);

        $folder->setParent($parent);
    }

    /**
     * Delete a folder. Like delete(), it flushes.
     *
     * @param EntityFileFolder $folder the folder
     * @param bool $recursive also delete its subfolders and files (stored files included)
     * @return void
     * @throws EntityFileFolderException if the folder is not empty and $recursive is false
     */
    public function deleteFolder(EntityFileFolder $folder, bool $recursive = false): void
    {
        $this->checkFolders();
        $this->checkOwner($folder, $folder->getEntityId());

        // Every folder of the entity, to find the subfolders without a query per level
        $subfolders = array_values(array_filter(
            $this->em->getRepository(EntityFileFolder::class)->findBy([
                "configName" => $this->configName,
                "entityId" => $folder->getEntityId(),
            ]),
            fn (EntityFileFolder $candidate) => $candidate !== $folder && $candidate->isIn($folder),
        ));
        $folders = [$folder, ...$subfolders];

        /** @var class-string $entityClass */
        $entityClass = $this->config["entity_file_class"];

        /** @var EntityFileInterface[] $files */
        $files = $this->em->getRepository($entityClass)->findBy(["folder" => $folders]);

        if (!$recursive && ($subfolders !== [] || $files !== [])) {
            throw new EntityFileFolderException(EntityFileFolderException::NOT_EMPTY);
        }

        foreach ($files as $file) {
            $this->removeFile($file);
        }

        // Deepest first, so that no folder is removed before its subfolders
        usort($folders, fn (EntityFileFolder $first, EntityFileFolder $second)
            => count($second->getPath()) <=> count($first->getPath()));

        foreach ($folders as $removed) {
            $this->em->remove($removed);
        }

        $this->em->flush();
    }

    /**
     * Move a file to a folder, flush to save it. The stored file does not move.
     *
     * @param EntityFileInterface $entityFile the file
     * @param EntityFileFolder|null $folder the folder, null for the root
     * @return void
     * @throws EntityFileFolderException if the folder belongs to another entity
     */
    public function moveToFolder(EntityFileInterface $entityFile, ?EntityFileFolder $folder): void
    {
        $this->checkFolders();

        if (!$entityFile instanceof FolderAwareEntityFileInterface) {
            throw new EntityFileFolderException(EntityFileFolderException::FOLDERS_DISABLED);
        }

        if ($folder !== null) {
            $this->checkOwner($folder, $entityFile->getEntityId());
        }

        $entityFile->setFolder($folder);
    }

    /**
     * @return bool true if the files of this configuration can have a public link ("public_links" option)
     */
    public function hasPublicLinks(): bool
    {
        return (bool)($this->config["public_links"] ?? false);
    }

    /**
     * Public link of a file, created (persisted, flush to save it) the first time.
     * Anyone with its token reads the file without logging in, see EntityFilePublicLinkResolver.
     *
     * @param EntityFileInterface $entityFile a saved file
     * @return EntityFilePublicLink
     * @throws EntityFileException if public links are not enabled, or the file is not saved
     */
    public function getPublicLink(EntityFileInterface $entityFile): EntityFilePublicLink
    {
        $fileId = $this->getFileId($entityFile);

        if (!$this->hasPublicLinks() || $fileId === null) {
            throw new EntityFileException("Public links are not enabled for configuration '$this->configName'.");
        }

        $link = $this->em->getRepository(EntityFilePublicLink::class)->findOneBy([
            "configName" => $this->configName,
            "fileId" => $fileId,
        ]);

        if ($link === null) {
            $link = (new EntityFilePublicLink())
                ->setConfigName($this->configName)
                ->setFileId($fileId)
                ->setToken(rtrim(strtr(base64_encode(random_bytes(32)), "+/", "-_"), "="));
            $this->em->persist($link);
        }

        return $link;
    }

    /**
     * @return bool true if PDF files of this configuration have a thumbnail ("thumbnails" option)
     */
    public function hasThumbnails(): bool
    {
        return (bool)($this->config["thumbnails"] ?? false);
    }

    /**
     * @return bool true if a thumbnail can be generated for this file (a PDF, with the imagick extension)
     */
    public function canThumbnail(EntityFileInterface $entityFile): bool
    {
        return $this->hasThumbnails()
            && $entityFile->getMimeType() === self::PDF_MIME_TYPE
            && $this->getFileId($entityFile) !== null
            && extension_loaded("imagick");
    }

    /**
     * Image of the first page of a PDF file, generated the first time and kept in the storage.
     * Ghostscript reads the PDF: keep it up to date, it runs with -dSAFER by default (9.50+).
     *
     * @return string|null JPEG contents, null if the file has no thumbnail or it could not be generated
     */
    public function getThumbnail(EntityFileInterface $entityFile): ?string
    {
        if (!$this->canThumbnail($entityFile)) {
            return null;
        }

        $path = $this->getThumbnailPath($entityFile);

        if ($this->storage->fileExists($path)) {
            return $this->storage->read($path);
        }

        $pdfPath = tempnam(sys_get_temp_dir(), "lle_entity_file_");

        if ($pdfPath === false) {
            return null;
        }

        try {
            file_put_contents($pdfPath, $this->readStream($entityFile));

            $image = new \Imagick();
            $image->setResolution(72, 72);
            // First page only, without loading the others
            $image->readImage("pdf:" . $pdfPath . "[0]");
            $image->setImageBackgroundColor("white");
            $image = $image->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
            $image->thumbnailImage(self::THUMBNAIL_WIDTH, 0);
            $image->setImageFormat("jpeg");
            $image->setImageCompressionQuality(self::THUMBNAIL_QUALITY);
            $thumbnail = $image->getImageBlob();
        } catch (\ImagickException) {
            return null;
        } finally {
            unlink($pdfPath);
        }

        $this->storage->write($path, $thumbnail);

        return $thumbnail;
    }

    /**
     * Deletes the stored file, its thumbnail and its public link, and removes the EntityFile (no flush).
     */
    private function removeFile(EntityFileInterface $entityFile): void
    {
        $this->storage->delete($this->configName . "/" . $entityFile->getPath());
        $this->deleteThumbnail($entityFile);

        $fileId = $this->getFileId($entityFile);
        if ($fileId !== null) {
            $links = $this->em->getRepository(EntityFilePublicLink::class)->findBy([
                "configName" => $this->configName,
                "fileId" => $fileId,
            ]);
            foreach ($links as $link) {
                $this->em->remove($link);
            }
        }

        $this->em->remove($entityFile);
    }

    private function deleteThumbnail(EntityFileInterface $entityFile): void
    {
        if ($this->getFileId($entityFile) !== null && $this->storage->fileExists($this->getThumbnailPath($entityFile))) {
            $this->storage->delete($this->getThumbnailPath($entityFile));
        }
    }

    /**
     * By id, not by path: renaming a file (move()) keeps its thumbnail.
     */
    private function getThumbnailPath(EntityFileInterface $entityFile): string
    {
        return $this->configName . "/" . self::THUMBNAIL_DIRECTORY . "/" . $this->getFileId($entityFile) . ".jpg";
    }

    /**
     * @return int|null null for a file not saved yet
     */
    private function getFileId(EntityFileInterface $entityFile): ?int
    {
        try {
            $id = method_exists($entityFile, "getId") ? $entityFile->getId() : null;
        } catch (\Error) {
            // A file class declaring "private ?int $id;" without default, read before the first flush
            return null;
        }

        return is_int($id) ? $id : null;
    }

    private function checkFolders(): void
    {
        if (!$this->hasFolders()) {
            throw new EntityFileFolderException(EntityFileFolderException::FOLDERS_DISABLED);
        }
    }

    private function checkOwner(EntityFileFolder $folder, mixed $entityId): void
    {
        if ($folder->getConfigName() !== $this->configName || $folder->getEntityId() !== $entityId) {
            throw new EntityFileFolderException(EntityFileFolderException::FOREIGN_FOLDER);
        }
    }

    /**
     * @return string the trimmed name
     */
    private function checkName(string $name): string
    {
        $name = trim($name);

        if (
            $name === ""
            || $name === "."
            || $name === ".."
            || strpbrk($name, "/\\") !== false
            || mb_strlen($name) > EntityFileFolder::NAME_MAX_LENGTH
        ) {
            throw new EntityFileFolderException(EntityFileFolderException::INVALID_NAME);
        }

        return $name;
    }

    private function checkUniqueName(EntityFileFolder $folder, string $name, ?EntityFileFolder $parent): void
    {
        $sibling = $this->em->getRepository(EntityFileFolder::class)->findOneBy([
            "configName" => $this->configName,
            "entityId" => $folder->getEntityId(),
            "parent" => $parent,
            "name" => $name,
        ]);

        if ($sibling !== null && $sibling !== $folder) {
            throw new EntityFileFolderException(EntityFileFolderException::DUPLICATE_NAME);
        }
    }

    private function getEntityId(object $entity): mixed
    {
        return method_exists($entity, "getId") ? $entity->getId() : null;
    }

    protected function getStorage(string $configName): FilesystemOperator
    {
        return $this->storage;
    }

    /** @return array<string, mixed> */
    public function getConfig(): array
    {
        return $this->config;
    }
}
