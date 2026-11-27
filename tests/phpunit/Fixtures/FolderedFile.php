<?php

namespace Lle\EntityFileBundle\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Lle\EntityFileBundle\Entity\FolderAwareEntityFileInterface;
use Lle\EntityFileBundle\Entity\Trait\EntityFileFolderTrait;
use Lle\EntityFileBundle\Entity\Trait\EntityFileTrait;

/**
 * File class of a configuration with folders, as a project declares it.
 */
#[ORM\Entity]
#[ORM\Table(name: "test_foldered_file")]
class FolderedFile implements FolderAwareEntityFileInterface
{
    use EntityFileTrait;
    use EntityFileFolderTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
