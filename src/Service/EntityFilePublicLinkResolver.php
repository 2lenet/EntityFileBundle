<?php

namespace Lle\EntityFileBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Lle\EntityFileBundle\Entity\EntityFileInterface;
use Lle\EntityFileBundle\Entity\EntityFilePublicLink;
use Lle\EntityFileBundle\Exception\EntityFileException;

/**
 * Finds the file of a public link token.
 */
class EntityFilePublicLinkResolver
{
    public function __construct(
        private EntityFileLoader $loader,
        private EntityManagerInterface $em,
    )
    {
    }

    /**
     * @return array{EntityFileManager, EntityFileInterface}|null null if the token is unknown, its configuration
     *     no longer exists or no longer has "public_links", or its file was deleted
     */
    public function resolve(string $token): ?array
    {
        $link = $this->em->getRepository(EntityFilePublicLink::class)->findOneBy(["token" => $token]);

        if ($link === null) {
            return null;
        }

        try {
            $manager = $this->loader->get((string)$link->getConfigName());
        } catch (EntityFileException) {
            return null;
        }

        if (!$manager->hasPublicLinks()) {
            return null;
        }

        /** @var class-string $entityFileClass */
        $entityFileClass = $manager->getConfig()["entity_file_class"];

        /** @var EntityFileInterface|null $file */
        $file = $this->em->getRepository($entityFileClass)->findOneBy([
            "id" => $link->getFileId(),
            "configName" => $link->getConfigName(),
        ]);

        return $file === null ? null : [$manager, $file];
    }
}
