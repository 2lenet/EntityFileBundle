<?php

namespace Lle\EntityFileBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

class EntityFileManager
{
    public function __construct(
        private ParameterBagInterface $parameters,
        private ServiceLocator $storageLocator,
        private EntityManagerInterface $em,
    )
    {
    }

    public function get(string $configName): EntityFileStorage
    {
        return new EntityFileStorage(
            $this->parameters,
            $this->storageLocator,
            $this->em,
            $configName
        );
    }
}
