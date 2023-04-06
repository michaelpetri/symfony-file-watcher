# Symfony File Watcher

[![Type Coverage](https://shepherd.dev/github/michaelpetri/symfony-file-watcher/coverage.svg)](https://shepherd.dev/github/michaelpetri/symfony-file-watcher)
[![Latest Stable Version](https://poser.pugx.org/michaelpetri/symfony-file-watcher/v)](https://packagist.org/packages/michaelpetri/symfony-file-watcher)
[![License](https://poser.pugx.org/michaelpetri/symfony-file-watcher/license)](https://packagist.org/packages/michaelpetri/symfony-file-watcher)

This package contains a file watcher based on [symfony/messenger](https://github.com/symfony/messenger) and [michaelpetri/php-git](https://github.com/michaelpetri/php-git).

Since it is based on git it can emmit events for files even after it has been paused for a decent time. All changes will 
trigger a `FileCreated`, `FileChanged` or `FileDeleted` event which then can be handled by an event handler.

> **Note:**
> Keep in mind that git is not good at handling binary files and using this package can lead to bloated disk usage!  

## Installation:
```
composer require michaelpetri/symfony-file-watcher 
```

## Usage:

```yaml
# messenger.yaml
transports:
  my-file-watcher:
    dsn: 'watch:///absolute/path/to/files?timeout=60000' # timeout in milliseconds
```

```shell
bin/console messenger:setup
bin/console messenger:consume my-file-watcher
```

## Sponsor:

[![Lyska.cloud](https://avatars.githubusercontent.com/u/82085619?s=400&u=bf1fb2d6dec05e5911a190964568903b7a795592&v=4)](https://lyska.cloud)

By using cutting-edge technology, process automation and taking responsibility for integration, synchronization, quality and consistency of your data.