<?php

namespace Lle\EntityFileBundle\Crudit\Brick;

use Lle\CruditBundle\Brick\AbstractBasicBrickFactory;
use Lle\CruditBundle\Contracts\BrickConfigInterface;
use Lle\CruditBundle\Dto\BrickView;
use Lle\CruditBundle\Resolver\ResourceResolver;
use Lle\EntityFileBundle\Entity\EntityFileInterface;
use Lle\EntityFileBundle\Service\EntityFileLoader;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EntityFileBrickFactory extends AbstractBasicBrickFactory
{
    public function __construct(
        ResourceResolver $resourceResolver,
        RequestStack $requestStack,
        private EntityFileLoader $loader,
        private UrlGeneratorInterface $urlGenerator,
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

        $resource = $brickConfigurator->getDataSource()->get($this->getRequest()->get("id"));
        $files = $this->getFiles($brickConfigurator->getConfigName(), $resource);

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

    protected function getFiles(string $configName, object $resource): array
    {
        $manager = $this->loader->get($configName);
        $files = $manager->get($resource);

        $result = [];

        /** @var EntityFileInterface $file */
        foreach ($files as $file) {
            $url = $this->urlGenerator->generate("lle_entityfile_entityfile_readbypath", [
                "configName" => $configName,
                "path" => $file->getPath(),
            ]);
            $isImage = str_starts_with($file->getMimeType(), "image/");

            $result[] = [
                "url" => $url,
                "name" => $file->getName(),
                "size" => $file->getSize(),
                "resizeThumbnail" => $isImage,
                "disablePreview" => !$isImage,
            ];
        }

        return $result;
    }
}
