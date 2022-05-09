<?php

namespace Lle\EntityFileBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Lle\EntityFileBundle\Entity\EntityFileInterface;
use Lle\EntityFileBundle\Service\EntityFileLoader;
use Lle\EntityFileBundle\Service\EntityFileManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
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

    #[Route("/{configName}/{id}", requirements: ["id" => "\d+"])]
    public function read(string $configName, $id): StreamedResponse
    {
        $manager = $this->entityFileLoader->get($configName);

        $config = $manager->getConfig();

        $entityFile = $this->em
            ->getRepository($config["entity_file_class"])
            ->find($id);

        if (!$entityFile) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted($config["role"], $entityFile);

        return $this->getStreamedResponse($entityFile, $manager);
    }

    #[Route("/{configName}")]
    public function readByPath(string $configName, Request $request): StreamedResponse
    {
        $path = $request->get("path");
        $manager = $this->entityFileLoader->get($configName);

        $config = $manager->getConfig();

        $entityFile = $this->em
            ->getRepository($config["entity_file_class"])
            ->findOneBy([
                "path" => $path,
            ]);

        if (!$entityFile) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted($config["role"], $entityFile);

        return $this->getStreamedResponse($entityFile, $manager);
    }

    private function getStreamedResponse(EntityFileInterface $entityFile, EntityFileManager $manager): StreamedResponse
    {
        $resource = $manager->readStream($entityFile);

        $parts = explode("/", $entityFile->getPath());
        $disposition = HeaderUtils::makeDisposition(
            $manager->getConfig()["disposition"],
            end($parts)
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
}
