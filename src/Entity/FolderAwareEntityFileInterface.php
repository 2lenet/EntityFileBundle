<?php

namespace Lle\EntityFileBundle\Entity;

/**
 * File class of a configuration with folders ("folders: true"), see EntityFileFolderTrait.
 */
interface FolderAwareEntityFileInterface extends EntityFileInterface
{
    public function getFolder(): ?EntityFileFolder;

    public function setFolder(?EntityFileFolder $folder): self;
}
