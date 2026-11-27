<?php

namespace Lle\EntityFileBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Blameable\Traits\BlameableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;

/**
 * Folder of the files of one entity, in one configuration. Folders only exist in the database:
 * renaming or moving one does not move the stored files.
 */
#[ORM\Entity]
#[ORM\Table(name: "lle_entity_file_folder")]
#[ORM\Index(columns: ["config_name", "entity_id"], name: "idx_folder_owner")]
class EntityFileFolder
{
    use TimestampableEntity;
    use BlameableEntity;

    public const NAME_MAX_LENGTH = 255;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $configName = null;

    #[ORM\Column(type: "integer")]
    private ?int $entityId = null;

    #[ORM\Column(type: "string", length: self::NAME_MAX_LENGTH)]
    private ?string $name = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?EntityFileFolder $parent = null;

    public function __toString(): string
    {
        return (string)$this->name;
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

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    public function setEntityId(?int $entityId): self
    {
        $this->entityId = $entityId;

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

    public function getParent(): ?EntityFileFolder
    {
        return $this->parent;
    }

    public function setParent(?EntityFileFolder $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return EntityFileFolder[] the folders from the root down to this one
     */
    public function getPath(): array
    {
        $path = [];

        for ($folder = $this; $folder !== null; $folder = $folder->getParent()) {
            array_unshift($path, $folder);
        }

        return $path;
    }

    /**
     * @return bool true if this folder is the given one or one of its subfolders
     */
    public function isIn(EntityFileFolder $folder): bool
    {
        return in_array($folder, $this->getPath(), true);
    }
}
