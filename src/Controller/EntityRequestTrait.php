<?php

namespace Lle\EntityFileBundle\Controller;

use Lle\EntityFileBundle\Service\EntityFileListing;
use Lle\EntityFileBundle\Service\EntityFileManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;

/**
 * Loads the entity a route acts on. For an AbstractController with the properties
 * $em (EntityManagerInterface), $entityFileLoader (EntityFileLoader) and $csrfTokenManager (?CsrfTokenManagerInterface).
 */
trait EntityRequestTrait
{
    /**
     * Checks the configuration, the entity, the role (checked against the entity) and the CSRF token
     * (header CSRF_HEADER, when symfony/security-csrf is installed).
     *
     * @param string $roleKey the configuration key of the role: "role_write", "role_delete"...
     * @return array{EntityFileManager, object}
     */
    private function loadEntity(string $configName, mixed $id, Request $request, string $roleKey): array
    {
        $manager = $this->entityFileLoader->get($configName);

        /** @var class-string $entityClass */
        $entityClass = $manager->getConfig()["class"];
        $entity = $this->em->find($entityClass, $id);

        if ($entity === null) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted($manager->getConfig()[$roleKey], $entity);

        $token = new CsrfToken(EntityFileListing::CSRF_TOKEN_ID, $request->headers->get(EntityFileListing::CSRF_HEADER));
        if ($this->csrfTokenManager !== null && !$this->csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException("Invalid CSRF token.");
        }

        return [$manager, $entity];
    }
}
