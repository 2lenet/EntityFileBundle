<?php

namespace Lle\EntityFileBundle\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class EntityFileManager
{
    public function __construct(
        private ParameterBagInterface $parameters,
    )
    {
    }
}
