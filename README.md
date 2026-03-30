# InSquare OpenDXP Sitemap Bundle

Static XML sitemap generator for OpenDXP (Documents + DataObjects) with multi-site and multi-locale support.

## Requirements

- PHP 8.3+
- OpenDXP 1.x / Symfony 7.4
- Symfony Messenger (for queue processing)
- Elements Process Manager (commands integrate with Process Manager)

## Installation

```bash
composer require in-square/opendxp-sitemap-bundle
```

Enable the bundle in `config/bundles.php`:

```php
return [
    InSquare\OpendxpSitemapBundle\InSquareOpendxpSitemapBundle::class => ['all' => true],
];
```

## Configuration

Create `config/packages/in_square_opendxp_sitemap.yaml`:

```yaml
in_square_opendxp_sitemap:
  sites:
    - id: 0
      host: 'example.com'
      languages: ['pl']
      objects:
        - 'OpenDXP\Model\DataObject\Post'
        - 'OpenDXP\Model\DataObject\PostCategory'

    - id: 1
      host: 'example.org'
      languages: ['pl', 'en']
      objects:
        - 'OpenDXP\Model\DataObject\Product'

  object_generators:
    post: 'App\\Sitemap\\PostGenerator'
    postCategory: 'App\\Sitemap\\PostCategoryGenerator'
    product: 'App\\Sitemap\\ProductGenerator'

  output:
    dir: '%kernel.project_dir%/public/sitemap'
    max_urls_per_file: 50000
```

Notes:
- `sites[*].objects` defines DataObject classes to collect for each site.
- `object_generators` keys must match `ObjectGeneratorInterface::getId()`; keys are used in sitemap filenames.

## Commands

- `bin/console insquare:sitemap:install` – create `sitemap_item` table.
- `bin/console insquare:sitemap:collect` – dispatch sitemap messages to Messenger.
- `bin/console insquare:sitemap:dump` – generate XML files from database.
- `bin/console insquare:sitemap:delete` – delete XML files and truncate the table.

Add routing for Messenger in `config/packages/framework.yaml`:

```yaml
framework:
  messenger:
    routing:
      'InSquare\OpendxpSitemapBundle\Message\SitemapItemCreateMessage': async
```

Run Messenger worker for the queue:

```bash
bin/console messenger:consume async
```

## Controller

The bundle exposes `/sitemap.xml`. The controller selects the correct site and serves the
pre-generated file from `public/sitemap/sitemap.{siteId}.xml`.

## Object generators

Implement `InSquare\OpendxpSitemapBundle\Generator\ObjectGeneratorInterface` in your app:

```php
<?php

declare(strict_types=1);

namespace App\Sitemap;

use InSquare\OpendxpSitemapBundle\Generator\ObjectGeneratorInterface;
use InSquare\OpendxpSitemapBundle\Generator\SitemapItemData;
use OpenDXP\Model\DataObject\Post;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PostGenerator implements ObjectGeneratorInterface
{
    public function getId(): string
    {
        return 'post';
    }

    public function getObjectClass(): string
    {
        return Post::class;
    }

    public function buildItem(object $object, int $siteId, string $locale): ?SitemapItemData
    {
        if (!$object instanceof Post) {
            return null;
        }

        if (!$object->isPublished()) {
            return null;
        }

        $linkGenerator = $object->getClass()?->getLinkGenerator();
        if ($linkGenerator === null) {
            return null;
        }

        $url = $linkGenerator->generate($object, [
            'locale' => $locale,
            'siteId' => $siteId,
            'referenceType' => UrlGeneratorInterface::ABSOLUTE_URL,
        ]);

        $lastmod = (new \DateTimeImmutable())->setTimestamp($object->getModificationDate());

        return new SitemapItemData(
            $object->getId(),
            $object::class,
            $url,
            $lastmod
        );
    }
}
```

The generator returns a `SitemapItemData` DTO used to persist rows in `sitemap_item`.
