<?php

namespace Lle\EntityFileBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Lle\EntityFileBundle\Service\EntityFileLoader;
use Lle\EntityFileBundle\Service\EntityFilePublicLinkResolver;
use Lle\EntityFileBundle\Service\EntityFileStreamer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Public links ("public_links" option) and thumbnails ("thumbnails" option) of the files.
 */
class EntityFileLinkController extends AbstractController
{
    use EntityRequestTrait;

    /** The public route: to open to anonymous users in access_control */
    public const PUBLIC_PATH_PREFIX = "/lle-entity-file-public";

    private const THUMBNAIL_MAX_AGE = 3600;

    public function __construct(
        private EntityManagerInterface $em,
        private EntityFileLoader $entityFileLoader,
        private EntityFilePublicLinkResolver $publicLinkResolver,
        private EntityFileStreamer $streamer,
        private ?CsrfTokenManagerInterface $csrfTokenManager = null,
    )
    {
    }

    /**
     * Creates the public link of a file the first time, needs "role_public_link" on the entity.
     */
    #[Route(
        "/lle-entity-file/{configName}/{id}/files/{fileId}/public-link",
        name: "lle_entityfile_file_publiclink",
        methods: ["POST"],
    )]
    public function publicLink(string $configName, mixed $id, int $fileId, Request $request): JsonResponse
    {
        [$manager, $entity] = $this->loadEntity($configName, $id, $request, "role_public_link");
        $file = $manager->getFile($entity, $fileId);

        if (!$manager->hasPublicLinks() || $file === null) {
            throw $this->createNotFoundException();
        }

        $link = $manager->getPublicLink($file);
        $this->em->flush();

        return new JsonResponse([
            "url" => $this->generateUrl(
                "lle_entityfile_public_read",
                ["token" => $link->getToken()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
        ]);
    }

    /**
     * Reads a file without logging in.
     */
    #[Route(self::PUBLIC_PATH_PREFIX . "/{token}", name: "lle_entityfile_public_read", methods: ["GET"])]
    public function publicRead(string $token): StreamedResponse
    {
        $resolved = $this->publicLinkResolver->resolve($token) ?? throw $this->createNotFoundException();

        return $this->streamer->stream($resolved[0], $resolved[1]);
    }

    /**
     * Image of the first page of a PDF, needs the read "role" on the file.
     */
    #[Route(
        "/lle-entity-file/{configName}/{id}/files/{fileId}/thumbnail",
        name: "lle_entityfile_file_thumbnail",
        methods: ["GET"],
    )]
    public function thumbnail(string $configName, mixed $id, int $fileId): Response
    {
        $manager = $this->entityFileLoader->get($configName);

        /** @var class-string $entityClass */
        $entityClass = $manager->getConfig()["class"];
        $entity = $this->em->find($entityClass, $id) ?? throw $this->createNotFoundException();
        $file = $manager->getFile($entity, $fileId) ?? throw $this->createNotFoundException();

        $this->denyAccessUnlessGranted($manager->getConfig()["role"], $file);

        $thumbnail = $manager->getThumbnail($file) ?? throw $this->createNotFoundException();

        $response = new Response($thumbnail, Response::HTTP_OK, [
            "Content-Type" => "image/jpeg",
            "X-Content-Type-Options" => "nosniff",
        ]);
        $response->setPrivate();
        $response->setMaxAge(self::THUMBNAIL_MAX_AGE);

        return $response;
    }
}
