<?php

namespace Lle\EntityFileBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Lle\EntityFileBundle\Entity\EntityFileInterface;
use Lle\EntityFileBundle\Service\EntityFileLoader;
use Lle\EntityFileBundle\Service\EntityFileManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route("/lle-entity-file")]
class EntityFileController extends AbstractController
{
    // English message used as the translation key, so that it is also the message without translator
    private const REFUSED_TYPE_MESSAGE = "This file type is not accepted. Accepted extensions: %extensions%.";

    public function __construct(
        private EntityManagerInterface $em,
        private EntityFileLoader $entityFileLoader,
        private ?TranslatorInterface $translator = null,
    )
    {
    }

    #[Route("/{configName}/{id}", methods: ["GET"])]
    public function read(string $configName, mixed $id): StreamedResponse
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

        return $this->getStreamedResponse($entityFile, $manager);
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

        return $this->getStreamedResponse($entityFile, $manager);
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

    private function getStreamedResponse(EntityFileInterface $entityFile, EntityFileManager $manager): StreamedResponse
    {
        $resource = $manager->readStream($entityFile);
        $name = (string)$entityFile->getName() ?: "file";

        // Slug the basename only: slugging the whole name turns "toto.docx" into "toto-docx",
        // and the client then appends an extension of its own. The slug is only the ASCII
        // fallback, the original name is sent as the UTF-8 filename.
        $slugger = new AsciiSlugger();
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $fallbackName = $slugger->slug(pathinfo($name, PATHINFO_FILENAME))
            . ($extension ? "." . $extension : "");

        // A file stored before its type was refused (see "mime_types") is only offered as a download
        $allowed = $manager->isAllowedMimeType((string)$entityFile->getMimeType());

        $disposition = HeaderUtils::makeDisposition(
            $allowed ? $manager->getConfig()["disposition"] : HeaderUtils::DISPOSITION_ATTACHMENT,
            $name,
            $fallbackName ?: "file",
        );

        return new StreamedResponse(function () use ($resource) {
            fpassthru($resource);
            exit();
        }, 200, [
            "Content-Type" => $allowed ? $entityFile->getMimeType() : "application/octet-stream",
            "Content-Length" => $entityFile->getSize(),
            "Content-Disposition" => $disposition,
            "X-Content-Type-Options" => "nosniff",
            // Scripts of an HTML or SVG file cannot run on this domain, even if such a type is accepted.
            // Not for PDF: browsers refuse to display it in a sandbox.
            ...($entityFile->getMimeType() === "application/pdf" ? [] : ["Content-Security-Policy" => "sandbox"]),
        ]);
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

            $entityFile = $manager->save($entity, $file, $path);
            $entityFile->setName($file->getClientOriginalName());

            $this->em->persist($entityFile);
            $this->em->flush();

            $url = $this->generateUrl("lle_entityfile_entityfile_readbypath", [
                "configName" => $configName,
                "path" => $entityFile->getPath(),
            ]);
            $deleteUrl = $this->generateUrl("lle_entityfile_entityfile_deletebypath", [
                "configName" => $configName,
                "path" => $entityFile->getPath(),
            ]);
            $isImage = str_starts_with((string)$entityFile->getMimeType(), "image/");

            return new JsonResponse([
                "url" => $url,
                "deleteUrl" => $deleteUrl,
                "name" => $entityFile->getName(),
                "path" => $entityFile->getPath(),
                "size" => $entityFile->getSize(),
                "resizeThumbnail" => $isImage,
                "disablePreview" => !$isImage,
            ]);
        }

        return new Response(null, 400);
    }

    /**
     * Dropzone displays the "error" key of a JSON response.
     */
    private function getRefusedTypeResponse(EntityFileManager $manager): JsonResponse
    {
        $parameters = ["%extensions%" => implode(", ", $manager->getAllowedExtensions())];
        $message = $this->translator
            ? $this->translator->trans(self::REFUSED_TYPE_MESSAGE, $parameters, "LleEntityFileBundle")
            : strtr(self::REFUSED_TYPE_MESSAGE, $parameters);

        return new JsonResponse(["error" => $message], Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
    }
}
