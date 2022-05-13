<?php

namespace Lle\EntityFileBundle\Entity;

interface EntityFileInterface
{
    public function __toString(): string;

    public function getEntityId(): mixed;

    public function setEntityId(mixed $entityId): self;

    public function getConfigName(): ?string;

    public function setConfigName(?string $configName): self;

    public function getPath(): ?string;

    public function setPath(?string $path): self;

    public function getMimeType(): ?string;

    public function setMimeType(?string $mimeType): self;

    public function getSize(): ?float;

    public function setSize(?float $size): self;

    public function getName(): ?string;

    public function setName(?string $name): self;
}
