<?php

namespace Lle\EntityFileBundle\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;

/**
 * Business entity the files and folders are attached to.
 */
#[ORM\Entity]
#[ORM\Table(name: "test_document")]
class Document
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
