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
use Symfony\Component\Routing\Annotation\Route;

#[Route("/lle-entity-file")]
class EntityFileController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private EntityFileLoader $entityFileLoader,
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

        if (!$entityFile) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted($config["role"], $entityFile);

        return $this->getStreamedResponse($entityFile, $manager);
    }

    #[Route("/{configName}", methods: ["GET"])]
    public function readByPath(string $configName, Request $request): StreamedResponse
    {
        $path = $request->get("path");
        $manager = $this->entityFileLoader->get($configName);

        $config = $manager->getConfig();

        /** @var class-string $entityClass */
        $entityClass = $config["entity_file_class"];

        /** @var EntityFileInterface|null $entityFile */
        $entityFile = $this->em
            ->getRepository($entityClass)
            ->findOneBy([
                "path" => $path,
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

        if (!$entityFile) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted($config["role"], $entityFile);

        $manager->delete($entityFile);

        return new JsonResponse();
    }

    #[Route("/{configName}", methods: ["DELETE"])]
    public function deleteByPath(string $configName, Request $request): JsonResponse
    {
        $path = $request->get("path");
        $manager = $this->entityFileLoader->get($configName);

        $config = $manager->getConfig();

        /** @var class-string $entityClass */
        $entityClass = $config["entity_file_class"];

        /** @var EntityFileInterface|null $entityFile */
        $entityFile = $this->em
            ->getRepository($entityClass)
            ->findOneBy([
                "path" => $path,
            ]);

        if (!$entityFile) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted($config["role"], $entityFile);

        $manager->delete($entityFile);

        return new JsonResponse();
    }

    private function getStreamedResponse(EntityFileInterface $entityFile, EntityFileManager $manager): StreamedResponse
    {
        $resource = $manager->readStream($entityFile);

        $parts = explode("/", (string)$entityFile->getPath());
        $disposition = HeaderUtils::makeDisposition(
            $manager->getConfig()["disposition"],
            (string)$entityFile->getName(),
            "file",
        );

        return new StreamedResponse(function () use ($resource) {
            fpassthru($resource);
            exit();
        }, 200, [
            "Content-Type" => $entityFile->getMimeType(),
            "Content-Length" => $entityFile->getSize(),
            "Content-Disposition" => $disposition,
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

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $file */
            $file = $form->getData();
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
}
