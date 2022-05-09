<?php

namespace Lle\EntityFileBundle\Crudit\Brick;

use Lle\CruditBundle\Brick\AbstractBrickConfig;

class EntityFileBrickConfig extends AbstractBrickConfig
{
    private string $configName;

    public static function new(string $configName): self
    {
        $result = new EntityFileBrickConfig();
        $result->setConfigName($configName);

        return $result;
    }

    public function getConfigName(): string
    {
        return $this->configName;
    }

    public function setConfigName(string $configName): self
    {
        $this->configName = $configName;

        return $this;
    }
}
