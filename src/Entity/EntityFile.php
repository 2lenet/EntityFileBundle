<?php

namespace Lle\EntityFileBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Blameable\Traits\BlameableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Lle\EntityFileBundle\Entity\Trait\EntityFileTrait;

#[ORM\Entity]
#[ORM\Table(name: "lle_entity_file")]
class EntityFile implements EntityFileInterface
{
    use EntityFileTrait;
    use TimestampableEntity;
    use BlameableEntity;

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
