# EntityFileBundle

With this bundle, you can attach files to entities.

* [Installation](#installation)
* [Configuration](#configuration)
  * [Basic example](#basic-example)
  * [Change the storage adapter](#change-the-storage-adapter)
* [Usage](#usage)
  * [Retrieve files](#retrieve-files)
  * [Access file contents](#access-file-contents)
  * [Delete a file](#delete-a-file)
  * [Rename or move a file](#rename-or-move-a-file)
  * [Exception handling](#exception-handling)
* [Crudit](#crudit)

## Installation

```
composer require 2lenet/entity-file-bundle
```

## Configuration

This bundle works with configurations. A configuration = 1 entity 1 file system.

For example, you may have a configuration for the logo of multiple sellers, and a configuration for the pictures of the products they sell.

### Basic configuration

In  `lle_entity_file.yaml`
```yaml
lle_entity_file:
    configurations:
        seller_logos:
            class: "App\\Entity\\Seller"
            storage_adapter: "lle_entity_file.storage.default"

```

That's it! With the default storage adapter configuration, those files will be saved under data/seller_logos

### Change the storage adapter

This bundle uses the [FlySystem Symfony Bundle](https://flysystem.thephpleague.com/docs/). You can create your own storage adapters, (Local disk, FTP, Drive...).

For that, you need to [configure a new adapter](https://github.com/thephpleague/flysystem-bundle/blob/master/docs/B-configuration-reference.md). Then, change the `storage_adapter` of your configuration.

## Usage

First of all, you need to get the manager for your configuration.

```php
$manager = $entityFileLoader->get("seller_logos");
```

### Create a file

```php
$entityFile = $manager->save($seller, $data, $path);

$this->em->persist($entityFile);
$this->em->flush();
```

$data may be a string, a Symfony File object (includes UploadedFile) or a resource.

> :warning: **Never forget to persist and flush the EntityFile.**

* I want my EntityFile to contain additional properties!

You can use your own Entity class, it needs to be a Doctrine entity that implements `Lle\EntityFileBundle\Entity\EntityFileInterface`. For your convenience, the trait `LleEntityFileBundle\Entity\Trait\EntityFileTrait` exists.

* I want to edit my new properties!

```php
$entityFile = $manager->save($seller, $data, "unicorn.png");

$entityFile->setDescription("Picture of a very sexy unicorn");
$this->em->persist($entityFile);
$this->em->flush();
```

### Retrieve files

```php
$manager->get($seller);
$manager->getOne($seller);
```

### Access file contents
WIP

### Delete a file
```php
// deletes the entity and the actual file
$manager->delete($file);
```

### Rename or move a file
```php
$manager->move($file, "actually_not_an_unicorn.png");
$this->em->persist($file);
$this->em->flush();
```

> :warning: **Never forget to persist and flush the EntityFile.**

### Exception handling

https://flysystem.thephpleague.com/docs/usage/exception-handling/

## Crudit

WIP
