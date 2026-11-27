<?php

namespace Lle\EntityFileBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Lle\EntityFileBundle\Entity\EntityFileFolder;
use Lle\EntityFileBundle\Exception\EntityFileFolderException;
use Lle\EntityFileBundle\Service\EntityFileListing;
use Lle\EntityFileBundle\Service\EntityFileLoader;
use Lle\EntityFileBundle\Service\EntityFileManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Folders of a configuration with "folders: true". Every route answers with the new listing
 * (see EntityFileListing), or a JSON "error" (HTTP 400).
 * Creating, renaming and moving need "role_write", deleting "role_delete", both checked against the entity.
 */
#[Route("/lle-entity-file/{configName}/{id}")]
class EntityFileFolderController extends AbstractController
{
    public const CSRF_HEADER = "X-CSRF-Token";
    public const PARAMETER_NAME = "name";
    public const PARAMETER_PARENT = "parent";
    public const PARAMETER_FOLDER = "folder";
    public const PARAMETER_RECURSIVE = "recursive";

    public function __construct(
        private EntityManagerInterface $em,
        private EntityFileLoader $entityFileLoader,
        private EntityFileListing $listing,
        private ?CsrfTokenManagerInterface $csrfTokenManager = null,
        private ?TranslatorInterface $translator = null,
    )
    {
    }

    #[Route("/folders", name: "lle_entityfile_folder_create", methods: ["POST"])]
    public function create(string $configName, mixed $id, Request $request): JsonResponse
    {
        [$manager, $entity] = $this->load($configName, $id, $request, "role_write");
        $parent = $this->findFolder($manager, $entity, $request->request->get(self::PARAMETER_PARENT));

        return $this->apply($configName, $entity, fn () => $manager->createFolder(
            $entity,
            (string)$request->request->get(self::PARAMETER_NAME),
            $parent,
        ));
    }

    #[Route("/folders/{folderId}/rename", name: "lle_entityfile_folder_rename", methods: ["POST"])]
    public function rename(string $configName, mixed $id, int $folderId, Request $request): JsonResponse
    {
        [$manager, $entity] = $this->load($configName, $id, $request, "role_write");
        $folder = $this->getFolder($manager, $entity, $folderId);

        return $this->apply($configName, $entity, fn () => $manager->renameFolder(
            $folder,
            (string)$request->request->get(self::PARAMETER_NAME),
        ));
    }

    #[Route("/folders/{folderId}/move", name: "lle_entityfile_folder_move", methods: ["POST"])]
    public function move(string $configName, mixed $id, int $folderId, Request $request): JsonResponse
    {
        [$manager, $entity] = $this->load($configName, $id, $request, "role_write");
        $folder = $this->getFolder($manager, $entity, $folderId);
        $parent = $this->findFolder($manager, $entity, $request->request->get(self::PARAMETER_PARENT));

        return $this->apply($configName, $entity, fn () => $manager->moveFolder($folder, $parent));
    }

    #[Route("/folders/{folderId}", name: "lle_entityfile_folder_delete", methods: ["DELETE"])]
    public function delete(string $configName, mixed $id, int $folderId, Request $request): JsonResponse
    {
        [$manager, $entity] = $this->load($configName, $id, $request, "role_delete");
        $folder = $this->getFolder($manager, $entity, $folderId);

        return $this->apply($configName, $entity, fn () => $manager->deleteFolder(
            $folder,
            $request->query->getBoolean(self::PARAMETER_RECURSIVE),
        ));
    }

    #[Route("/files/{fileId}/move", name: "lle_entityfile_folder_movefile", methods: ["POST"])]
    public function moveFile(string $configName, mixed $id, int $fileId, Request $request): JsonResponse
    {
        [$manager, $entity] = $this->load($configName, $id, $request, "role_write");
        $folder = $this->findFolder($manager, $entity, $request->request->get(self::PARAMETER_FOLDER));

        $file = $manager->getFile($entity, $fileId);

        if ($file === null) {
            throw $this->createNotFoundException();
        }

        return $this->apply($configName, $entity, fn () => $manager->moveToFolder($file, $folder));
    }

    /**
     * Checks the configuration, the entity, the role and the CSRF token.
     *
     * @return array{EntityFileManager, object}
     */
    private function load(string $configName, mixed $id, Request $request, string $roleKey): array
    {
        $manager = $this->entityFileLoader->get($configName);

        if (!$manager->hasFolders()) {
            throw $this->createNotFoundException();
        }

        /** @var class-string $entityClass */
        $entityClass = $manager->getConfig()["class"];
        $entity = $this->em->find($entityClass, $id);

        if ($entity === null) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted($manager->getConfig()[$roleKey], $entity);

        $token = new CsrfToken(EntityFileListing::CSRF_TOKEN_ID, $request->headers->get(self::CSRF_HEADER));
        if ($this->csrfTokenManager !== null && !$this->csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException("Invalid CSRF token.");
        }

        return [$manager, $entity];
    }

    private function getFolder(EntityFileManager $manager, object $entity, int $folderId): EntityFileFolder
    {
        return $manager->getFolder($entity, $folderId) ?? throw $this->createNotFoundException();
    }

    /**
     * @param mixed $folderId a folder id, empty for the root
     */
    private function findFolder(EntityFileManager $manager, object $entity, mixed $folderId): ?EntityFileFolder
    {
        if ($folderId === null || $folderId === "") {
            return null;
        }

        return $this->getFolder($manager, $entity, (int)$folderId);
    }

    private function apply(string $configName, object $entity, callable $change): JsonResponse
    {
        try {
            $change();
            $this->em->flush();
        } catch (EntityFileFolderException $exception) {
            $message = $this->translator?->trans($exception->getMessage(), [], "LleEntityFileBundle")
                ?? $exception->getMessage();

            return new JsonResponse(["error" => $message], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($this->listing->getListing($configName, $entity));
    }
}
