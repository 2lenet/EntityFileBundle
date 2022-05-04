<?php

namespace Lle\EntityFileBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Lle\EntityFileBundle\Entity\Trait\EntityFileTrait;
use Gedmo\Blameable\Traits\BlameableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;

class EntityFile implements EntityFileInterface
{
    use EntityFileTrait;
    use BlameableEntity;
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }
}
