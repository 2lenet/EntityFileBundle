<?php

namespace Lle\EntityFileBundle\Entity\Trait;

use Doctrine\ORM\Mapping as ORM;

trait EntityFileTrait
{
    #[ORM\Column(type: "integer")]
    private mixed $entityId;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $configName;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
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

    public function getEntityId(): mixed
    {
        return $this->entityId;
    }

    public function setEntityId(mixed $entityId): self
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getConfigName(): ?string
    {
        return $this->configName;
    }

    public function setConfigName(?string $configName): self
    {
        $this->configName = $configName;

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
