<?php

namespace Lle\EntityFileBundle\Exception;

/**
 * A folder operation that is refused. The messages are English translation keys (domain LleEntityFileBundle).
 */
class EntityFileFolderException extends EntityFileException
{
    public const FOLDERS_DISABLED = "Folders are not enabled for this configuration.";
    public const INVALID_NAME = "This folder name is not valid.";
    public const DUPLICATE_NAME = "A folder with this name already exists here.";
    public const FOREIGN_FOLDER = "This folder does not belong to this entity.";
    public const INVALID_MOVE = "A folder cannot be moved into itself or one of its subfolders.";
    public const NOT_EMPTY = "This folder is not empty.";
}
