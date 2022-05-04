<?php

namespace Lle\EntityFileBundle\Entity\Trait;

use Doctrine\ORM\Mapping as ORM;

trait EntityFileTrait
{
    #[ORM\Column(type: "integer")]
    private mixed $objectId;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $objectClass;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $objectProperty;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $name;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $description;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $path;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $mimeType;

    #[ORM\Column(type: "float")]
    private ?float $size;

    public function __toString(): string
    {
        return (string)$this->name;
    }

    public function getObjectId(): mixed
    {
        return $this->objectId;
    }

    public function setObjectId(mixed $objectId): self
    {
        $this->objectId = $objectId;

        return $this;
    }

    public function getObjectClass(): ?string
    {
        return $this->objectClass;
    }

    public function setObjectClass(?string $objectClass): self
    {
        $this->objectClass = $objectClass;

        return $this;
    }

    public function getObjectProperty(): ?string
    {
        return $this->objectProperty;
    }

    public function setObjectProperty(?string $objectProperty): self
    {
        $this->objectProperty = $objectProperty;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): self
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getSize(): ?float
    {
        return $this->size;
    }

    public function setSize(?float $size): self
    {
        $this->size = $size;

        return $this;
    }
}
