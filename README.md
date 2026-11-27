# EntityFileBundle

With this bundle, you can attach files to entities.

* [Installation](#installation)
* [Configuration](#configuration)
  * [Basic configuration](#basic-configuration)
  * [Change the storage adapter](#change-the-storage-adapter)
* [Usage](#usage)
  * [Retrieve files](#retrieve-files)
  * [Retrieve files from URL](#retrieve-files-from-url)
  * [Restrict accepted file types](#restrict-accepted-file-types)
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
$manager = $entityFileLoader->get("seller_logos");

$entityFile = $manager->save($seller, $data, $path);

$this->em->persist($entityFile);
$this->em->flush();
```

$data may be a string, a Symfony File object (including UploadedFile) or a resource.

> :warning: **Never forget to persist and flush the EntityFile.**

* I want my EntityFile to contain additional properties!

You can use your own Entity class, it needs to be a Doctrine entity that implements `Lle\EntityFileBundle\Entity\EntityFileInterface`. For your convenience, the trait `LleEntityFileBundle\Entity\Trait\EntityFileTrait` exists.

You will also have to update your configuration:
```yaml
unicorn:
    # ...
    entity_file_class: "App\\Entity\\UnicornEntityFile"
```

* I want to edit my new properties!

```php
$manager = $entityFileLoader->get("unicorn");

$entityFile = $manager->save($seller, $data, "unicorn.png");

$entityFile->setDescription("Picture of a very sexy unicorn");
$this->em->persist($entityFile);
$this->em->flush();
```

* I want to have a dynamic path in my file structure!

```php
$manager = $entityFileLoader->get("seller_logos");

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
$manager = $entityFileLoader->get("seller_logos");

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

A file is only found through the configuration it was uploaded with: its `role`, `disposition`, `included_mime_types` and `excluded_mime_types` always apply.

#### Protect your urls
By default, only logged in users can access those urls. You can change the `role` key in the configuration:
```yaml
operation_reports:
    # ...
    role: "ROLE_OPERATOR"
```

Uploading and deleting have their own roles, `role_write` and `role_delete`. Both default to `ROLE_USER`, and are independent of `role`:
```yaml
operation_reports:
    # ...
    role: "ROLE_USER"
    role_write: "ROLE_OPERATOR"
    role_delete: "ROLE_ADMIN"
```

`role_write` is checked against the entity the file is attached to, `role` and `role_delete` against the file itself (matters for a custom voter).

* I want the files to be public !

You can use "PUBLIC_ACCESS" in the `role` key.

* I want to do something more complex !

[Create a custom voter.](https://symfony.com/doc/current/security/voters.html)

#### Change content disposition (Show in browser or automatically download)
By default, files are served inline. You can change the disposition key under your configuration:
```yaml
zip_reports:
    # ...
    disposition: "attachment"
```

### Restrict accepted file types

Two options restrict the types the upload route accepts. **Declare only one of them**: a configuration declaring both
throws an `InvalidConfigurationException`, even if one of the lists is empty.

#### Exclude types (default)

`excluded_mime_types` lists the refused types, every other type is accepted.
By default, it refuses executable and script types: PHP, SQL, shell, Python, Perl, Ruby, Windows and Linux executables, Java archives,
macOS executables, and the types that run scripts in a browser (HTML, XHTML, SVG, JavaScript, XML).
See `Configuration::DEFAULT_EXCLUDED_MIME_TYPES` for the complete list.

Replace the list:
```yaml
seller_logos:
    # ...
    excluded_mime_types: ["text/x-php", "application/x-php", "application/sql"]
```

Use an empty list to accept any file (be careful, see the warning below):
```yaml
seller_logos:
    # ...
    excluded_mime_types: []
```

An uploaded file is then refused (HTTP 415, the message is shown in the dropzone) if:
* its type is in the list,
* or its extension belongs to one of the listed types (`php`, `sql`... here), even if its contents are of another type,
* or its extension is in `Configuration::DEFAULT_EXCLUDED_EXTENSIONS` (`phtml`, `phar`, `cgi`, `asp`, `jsp`, `htaccess`...),
  whatever the list. These extensions are executed by a web server and most belong to no type known to `symfony/mime`:
  a GIF holding PHP named `shell.phtml` is detected as `image/gif`, only its extension refuses it.

Unlike `included_mime_types`, a type unknown to `symfony/mime` is accepted here: `finfo` detects types such as `text/x-php` or
`text/x-shellscript`, which `symfony/mime` has no extension for. Such a type is only refused from the contents, so also list a type
with extensions (`application/x-php` for `.php`).

XML (`application/xml`, `text/xml`) is refused by default: served inline, an XML file with the XHTML namespace runs scripts.
To accept XML files, replace the list without these types, and prefer `disposition: attachment`.

The dropzone proposes every extension, the server check remains the one that counts.

#### Include types

`included_mime_types` lists the accepted types, every other type is refused. The default exclusions no longer apply.
```yaml
seller_logos:
    # ...
    included_mime_types: ["image/jpeg", "image/png", "image/webp", "application/pdf"]
```

An uploaded file is then refused (HTTP 415, the message is shown in the dropzone) unless:
* its type is in the list,
* and its extension belongs to one of the listed types (`jpg`, `jpeg`, `png`, `webp`, `pdf` here), not necessarily to its own type.

The dropzone only proposes and accepts the listed extensions, the server check remains the one that counts.

A type unknown to `symfony/mime` (a typo like `image/jpg` for instance) makes `cache:clear` fail, since every file would be refused.

#### Detection

The type is detected from the contents. When the contents are inconclusive (`text/plain`, `application/octet-stream`, empty file),
the extension decides: a CSV is `text/csv`, not `text/plain`, so list the precise type (`text/csv`, `application/json`...).
This detected type is also the one stored and sent as `Content-Type`.

Files already stored with a type that is no longer accepted are no longer displayed in the browser, only downloaded.

> :warning: **Do not accept `image/svg+xml`, `text/html` or `application/xhtml+xml` on a configuration served inline:** they could run scripts on your domain.
> They are excluded by default. Files are sent with `Content-Security-Policy: sandbox` (except PDF, which browsers do not display in a sandbox),
> which blocks these scripts in current browsers, but do not rely on it alone.

#### Upgrade from 1.3

The `mime_types` option is replaced by `included_mime_types`. Without it, a configuration no longer accepts only images and PDF:
it accepts everything but the default exclusions. To keep the previous behavior:
```yaml
seller_logos:
    # ...
    included_mime_types: ["image/jpeg", "image/png", "image/webp", "application/pdf"]
```

The check is also available in your own code:
```php
$manager->isAllowed($file); // Symfony File or UploadedFile
```

### Access file contents

```php
$manager = $entityFileLoader->get("seller_logos");

$manager->read($file);
$manager->readStream($file);
```

### Delete a file
```php
$manager = $entityFileLoader->get("seller_logos");

// deletes the entity and the actual file
$manager->delete($file);
```

### Rename or move a file
```php
$manager = $entityFileLoader->get("seller_logos");

$manager->move($file, "actually_not_an_unicorn.png");
$this->em->flush();
```

> :warning: **Never forget to flush the EntityFile.**

### Folders

The files of a configuration can be sorted in folders and subfolders. Folders only exist in the database
(table `lle_entity_file_folder`): renaming or moving a folder does not move the stored files.

Folders need a file class with a `folder` relation. Declare one in your project, with its own table:

```php
#[ORM\Entity]
class ContractFile implements FolderAwareEntityFileInterface
{
    use EntityFileTrait;
    use EntityFileFolderTrait;
    use TimestampableEntity;
    use BlameableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
```

```yaml
contracts:
    class: App\Entity\Customer
    entity_file_class: App\Entity\ContractFile
    folders: true
    # optional, root folders created for every entity
    default_folders: ["Contracts", "Offers"]
```

`folders: true` with a file class that does not implement `FolderAwareEntityFileInterface` throws an
`InvalidConfigurationException`. Without `folders`, nothing changes: the default `EntityFile` has no folder.

```php
$manager = $entityFileLoader->get("contracts");

$contracts = $manager->createFolder($customer, "Contracts");     // persisted, not flushed
$year = $manager->createFolder($customer, "2026", $contracts);
$file = $manager->save($customer, $data, "contract.pdf", $year);
$this->em->persist($file);
$this->em->flush();

$manager->getFolders($customer);            // every folder, sorted by name
$manager->getFiles($customer, $year);       // files directly in a folder, null for the root
$manager->get($customer);                   // every file, whatever its folder

$manager->renameFolder($year, "2027");      // flush to save
$manager->moveFolder($year, null);          // to the root, flush to save
$manager->moveToFolder($file, $contracts);  // flush to save
$manager->deleteFolder($contracts);         // refused if not empty, flushes
$manager->deleteFolder($contracts, true);   // with its subfolders and files (stored files included)
```

A refused operation (invalid or duplicate name, folder moved into itself, folder of another entity...) throws an
`EntityFileFolderException`, whose message is a translation key of the `LleEntityFileBundle` domain.

The Crudit brick then shows a breadcrumb, the folders of the current folder, and only the files of the current folder.
Files dropped in the dropzone go to the current folder; files and folders are moved by dragging them onto a folder
or onto the breadcrumb. Creating, renaming and moving need `role_write`, deleting needs `role_delete`, both checked
against the entity. When `symfony/security-csrf` is installed, the folder routes also check a CSRF token
(header `X-CSRF-Token`, token id `lle_entity_file`).

### Public links

```yaml
contracts:
    # ...
    public_links: true
    role_public_link: "ROLE_CONTRACT_SHARE"   # creating a link, checked against the entity (default ROLE_USER)
```

Each file then gets a "copy the public link" button in the brick. The link is created the first time (random token,
table `lle_entity_file_public_link`), then reused: anyone who has it reads the file **without logging in, with no
expiry**. Deleting the file, or disabling `public_links`, makes it stop working.

The public route must be opened to anonymous users, before your other rules:

```yaml
# config/packages/security.yaml
access_control:
    - { path: ^/lle-entity-file-public/, roles: PUBLIC_ACCESS }
```

```php
$link = $manager->getPublicLink($file);   // persisted, flush to save it
$url = $urlGenerator->generate("lle_entityfile_public_read", ["token" => $link->getToken()], UrlGeneratorInterface::ABSOLUTE_URL);
```

### Previews and thumbnails

In the brick, PDF files and images (not SVG) have a preview button: they open in a window, displayed by the browser even
with `disposition: attachment` (read routes with `?preview=1`). Files without thumbnail show the icon of their type.

```yaml
contracts:
    # ...
    thumbnails: true
```

PDF files then show their first page. The thumbnail is generated the first time it is displayed, with the `imagick`
extension and Ghostscript, and kept in the storage (`<configName>/.thumbnails/<file id>.jpg`, deleted with the file).
Without `imagick`, or for an unreadable PDF, the icon stays.

> :warning: Ghostscript reads PDF files uploaded by your users: keep it up to date. It runs with `-dSAFER` by default
> since 9.50, and the ImageMagick policy (`policy.xml`) must allow the `PDF` coder.

#### Upgrade to 1.4

The bundle maps two new entities, `EntityFileFolder` and `EntityFilePublicLink`: generate a migration
(`bin/console make:migration`), even if you use neither folders nor public links, or `doctrine:schema:validate` reports
the missing tables `lle_entity_file_folder` and `lle_entity_file_public_link`.

The read routes no longer call `exit()` after sending the file: `kernel.terminate` listeners now run after a download.
The `lle_entity_file` table does not change.

### Exception handling

https://flysystem.thephpleague.com/docs/usage/exception-handling/

## Crudit

This bundle is compatible with 2LE's Crudit bundle.

You can use the EntityFileBrick, for example in tabs:

```php
  public function getTabs(): array
  {
      return [
          "tab.files" => EntityFileBrickConfig::new("seller_logos"),
      ];
  }
```

It features a dropzone where you can see, add, remove and download files.
