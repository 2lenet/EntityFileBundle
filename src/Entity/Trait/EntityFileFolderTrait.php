<?php

namespace Lle\EntityFileBundle\Entity\Trait;

use Doctrine\ORM\Mapping as ORM;
use Lle\EntityFileBundle\Entity\EntityFileFolder;

/**
 * Folder of a file, null for the root. To use with EntityFileTrait in a FolderAwareEntityFileInterface.
 */
trait EntityFileFolderTrait
{
    #[ORM\ManyToOne(targetEntity: EntityFileFolder::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?EntityFileFolder $folder = null;

    public function getFolder(): ?EntityFileFolder
    {
        return $this->folder;
    }

    public function setFolder(?EntityFileFolder $folder): self
    {
        $this->folder = $folder;

        return $this;
    }
}
