<?php

namespace Lle\EntityFileBundle\Crudit\Brick;

use Lle\CruditBundle\Brick\AbstractBasicBrickFactory;
use Lle\CruditBundle\Contracts\BrickConfigInterface;
use Lle\CruditBundle\Dto\BrickView;
use Lle\CruditBundle\Resolver\ResourceResolver;
use Lle\EntityFileBundle\Service\EntityFileLoader;
use Symfony\Component\HttpFoundation\RequestStack;

class EntityFileBrickFactory extends AbstractBasicBrickFactory
{
    public function __construct(
        ResourceResolver $resourceResolver,
        RequestStack $requestStack,
        private EntityFileLoader $loader
    )
    {
        parent::__construct($resourceResolver, $requestStack);
    }

    public function support(BrickConfigInterface $brickConfigurator): bool
    {
        return (EntityFileBrickConfig::class === get_class($brickConfigurator));
    }

    public function buildView(BrickConfigInterface $brickConfigurator): BrickView
    {
        /** @var EntityFileBrickConfig $brickConfigurator */

        $manager = $this->loader->get($brickConfigurator->getConfigName());
        $resource = $brickConfigurator->getDataSource()->get($this->getRequest()->get("id"));
        $files = $manager->get($resource);

        $view = new BrickView($brickConfigurator);
        $view
            ->setTemplate("@LleEntityFile/crudit/brick/entity_file")
            ->setData([
                "files" => $files,
                "resource" => $resource,
            ])
            ->setConfig($brickConfigurator->getConfig($this->getRequest()));

        return $view;
    }
}
