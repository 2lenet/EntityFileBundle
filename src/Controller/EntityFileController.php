<?php

namespace Lle\EntityFileBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Lle\EntityFileBundle\Entity\EntityFileInterface;
use Lle\EntityFileBundle\Service\EntityFileListing;
use Lle\EntityFileBundle\Service\EntityFileLoader;
use Lle\EntityFileBundle\Service\EntityFileManager;
use Lle\EntityFileBundle\Service\EntityFileStreamer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route("/lle-entity-file")]
class EntityFileController extends AbstractController
{
    /** Query parameter: display a PDF or an image in the browser, whatever the "disposition" option */
    public const PARAMETER_PREVIEW = "preview";

    // English message used as the translation key, so that it is also the message without translator
    private const REFUSED_TYPE_MESSAGE = "This file type is not accepted.";
    private const REFUSED_TYPE_WITH_EXTENSIONS_MESSAGE = "This file type is not accepted. Accepted extensions: %extensions%.";

    public function __construct(
        private EntityManagerInterface $em,
        private EntityFileLoader $entityFileLoader,
        private EntityFileListing $listing,
        private EntityFileStreamer $streamer,
        private ?TranslatorInterface $translator = null,
    )
    {
    }

    #[Route("/{configName}/{id}", methods: ["GET"])]
    public function read(string $configName, mixed $id, Request $request): StreamedResponse
    {
        $manager = $this->entityFileLoader->get($configName);

        $config = $manager->getConfig();

        /** @var class-string $entityClass */
        $entityClass = $config["entity_file_class"];

        /** @var EntityFileInterface|null $entityFile */
        $entityFile = $this->em
            ->getRepository($entityClass)
            ->find($id);

        // The role and options of another configuration must not apply to this file
        if (!$entityFile || $entityFile->getConfigName() !== $configName) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted($config["role"], $entityFile);

        return $this->streamer->stream($manager, $entityFile, $request->query->getBoolean(self::PARAMETER_PREVIEW));
    }

    #[Route("/{configName}", methods: ["GET"])]
    public function readByPath(string $configName, Request $request): StreamedResponse
    {
        $path = $request->query->get("path");
        $manager = $this->entityFileLoader->get($configName);

        $config = $manager->getConfig();

        /** @var class-string $entityClass */
        $entityClass = $config["entity_file_class"];

        /** @var EntityFileInterface|null $entityFile */
        $entityFile = $this->em
            ->getRepository($entityClass)
            ->findOneBy([
                "path" => $path,
                "configName" => $configName,
            ]);

        if (!$entityFile) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted($config["role"], $entityFile);

        return $this->streamer->stream($manager, $entityFile, $request->query->getBoolean(self::PARAMETER_PREVIEW));
    }

    #[Route("/{configName}/{id}", methods: ["DELETE"])]
    public function delete(string $configName, mixed $id): JsonResponse
    {
        $manager = $this->entityFileLoader->get($configName);

        $config = $manager->getConfig();

        /** @var class-string $entityClass */
        $entityClass = $config["entity_file_class"];

        /** @var EntityFileInterface|null $entityFile */
        $entityFile = $this->em
            ->getRepository($entityClass)
            ->find($id);

        // The role and options of another configuration must not apply to this file
        if (!$entityFile || $entityFile->getConfigName() !== $configName) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted($config["role_delete"], $entityFile);

        $manager->delete($entityFile);

        return new JsonResponse();
    }

    #[Route("/{configName}", methods: ["DELETE"])]
    public function deleteByPath(string $configName, Request $request): JsonResponse
    {
        $path = $request->query->get("path");
        $manager = $this->entityFileLoader->get($configName);

        $config = $manager->getConfig();

        /** @var class-string $entityClass */
        $entityClass = $config["entity_file_class"];

        /** @var EntityFileInterface|null $entityFile */
        $entityFile = $this->em
            ->getRepository($entityClass)
            ->findOneBy([
                "path" => $path,
                "configName" => $configName,
            ]);

        if (!$entityFile) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted($config["role_delete"], $entityFile);

        $manager->delete($entityFile);

        return new JsonResponse();
    }

    #[Route("/{configName}/{id}", methods: ["POST"])]
    public function addFile(string $configName, mixed $id, Request $request): Response
    {
        $manager = $this->entityFileLoader->get($configName);

        $form = $this->createForm(FileType::class);
        $form->handleRequest($request);

        $config = $manager->getConfig();

        /** @var class-string $entityClass */
        $entityClass = $config["class"];
        $entity = $this->em->find($entityClass, $id);

        if (!$entity) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted($config["role_write"], $entity);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $file */
            $file = $form->getData();

            if (!$manager->isAllowed($file)) {
                return $this->getRefusedTypeResponse($manager);
            }

            $path = $file->getClientOriginalName();
            $folder = null;

            // Configurations with folders: the folder the file is dropped in, empty for the root
            $folderId = $request->request->get(EntityFileFolderController::PARAMETER_FOLDER);
            if ($manager->hasFolders() && $folderId !== null && $folderId !== "") {
                $folder = $manager->getFolder($entity, (int)$folderId) ?? throw $this->createNotFoundException();
            }

            $entityFile = $manager->save($entity, $file, $path, $folder);
            $entityFile->setName($file->getClientOriginalName());

            $this->em->persist($entityFile);
            $this->em->flush();

            return new JsonResponse($this->listing->getFileRow($configName, $entityFile));
        }

        return new Response(null, 400);
    }

    /**
     * Dropzone displays the "error" key of a JSON response.
     */
    private function getRefusedTypeResponse(EntityFileManager $manager): JsonResponse
    {
        $extensions = $manager->getAllowedExtensions();
        // With "excluded_mime_types", every extension but a long list is accepted: nothing useful to list
        $id = $extensions === [] ? self::REFUSED_TYPE_MESSAGE : self::REFUSED_TYPE_WITH_EXTENSIONS_MESSAGE;
        $parameters = ["%extensions%" => implode(", ", $extensions)];
        $message = $this->translator
            ? $this->translator->trans($id, $parameters, "LleEntityFileBundle")
            : strtr($id, $parameters);

        return new JsonResponse(["error" => $message], Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
    }
}
