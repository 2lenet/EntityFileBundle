# EntityFileBundle

With this bundle, you can attach files to entities.

* [Installation](#installation)
* [Configuration](#configuration)
  * [Basic example](#basic-example)
  * [Change the storage adapter](#change-the-storage-adapter)
* [Usage](#usage)
  * [Retrieve files](#retrieve-files)
  * [Retrieve files from URL](#retrieve-files-from-url)
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

First of all, you need to get the manager for your configuration. For that, use the `Lle\EntityFileBundle\Service\EntityFileLoader`

```php
$manager = $entityFileLoader->get("seller_logos");
```

### Create a file

```php
$entityFile = $manager->save($seller, $data, $path);

$this->em->persist($entityFile);
$this->em->flush();
```

$data may be a string, a Symfony File object (including UploadedFile) or a resource.

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

* I want to have a dynamic path in my file structure!

```php
$manager->save($order, $data, "you/can/do/this");

// example:

$dir = $order->getDate()->format("Y-m");
$name = $order->getId() . ".xml";

$manager->save($order, $data, $dir . "/" . $name)
```

* For some reason, I want to save my files somewhere else than data

Create your own storage adapter in `flysystem.yaml`, which is basically a copy of the default one with different directory option.
```yaml
flysystem:
    storages:
        unicorn.storage:
            adapter: "local"
            options:
                directory: "%kernel.project_dir%/unicorns"
                permissions:
                    file:
                        public: 511
                        private: 511
                    dir:
                        public: 511
                        private: 511
```

### Retrieve files

```php
$manager->get($seller);
$manager->getOne($seller);
```

### Retrieve files from URL

If you didn't use Symfony Flex, you need to add the routes in routes.yaml:
```yaml
lle_entity_file:
    resource: "@LleEntityFileBundle/Resources/config/routes.yaml"
```

Two routes are available:

* lle_entityfile_entityfile_read (requires configName and id)  
Example: /lle-entity-file/seller_logos/1
* lle_entityfile_entityfile_readbypath (requires configName and path)  
  Example: /lle-entity-file/seller_logos?path=2le.png

#### Protect your urls
By default, only logged in users can access those urls. You can change the `role` key in the configuration:
```yaml
operation_reports:
    # ...
    role: "ROLE_OPERATOR"
```

* I want to do something more complex !

[Create a custom voter.](https://symfony.com/doc/current/security/voters.html)

#### Change content disposition
By default, files are served inline. You can change the disposition key under your configuration:
```yaml
zip_reports:
    # ...
    disposition: "attachment"
```

### Access file contents

```php
$manager->read($file);
$manager->readStream($file);
```

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
