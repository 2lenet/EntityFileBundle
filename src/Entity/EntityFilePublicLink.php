<?php

namespace Lle\EntityFileBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Public link of a file (option "public_links"): anyone with the token reads the file, without logging in.
 * The token is random, never derived from the file. Deleted with the file.
 */
#[ORM\Entity]
#[ORM\Table(name: "lle_entity_file_public_link")]
#[ORM\Index(columns: ["config_name", "file_id"], name: "idx_public_link_file")]
class EntityFilePublicLink
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $configName = null;

    #[ORM\Column(type: "integer")]
    private ?int $fileId = null;

    #[ORM\Column(type: "string", length: 64, unique: true)]
    private ?string $token = null;

    #[ORM\Column(type: "datetime_immutable")]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getFileId(): ?int
    {
        return $this->fileId;
    }

    public function setFileId(?int $fileId): self
    {
        $this->fileId = $fileId;

        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): self
    {
        $this->token = $token;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
