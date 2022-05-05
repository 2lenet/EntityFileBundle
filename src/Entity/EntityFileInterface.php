<?php

namespace Lle\EntityFileBundle\Entity;

interface EntityFileInterface
{
    public function __toString(): string;

    public function getObjectId(): mixed;

    public function setObjectId(mixed $objectId): self;

    public function getObjectClass(): ?string;

    public function setObjectClass(?string $objectClass): self;

    public function getObjectProperty(): ?string;

    public function setObjectProperty(?string $objectProperty): self;

    public function getPath(): ?string;

    public function setPath(?string $path): self;

    public function getMimeType(): ?string;

    public function setMimeType(?string $mimeType): self;

    public function getSize(): ?float;

    public function setSize(?float $size): self;
}
