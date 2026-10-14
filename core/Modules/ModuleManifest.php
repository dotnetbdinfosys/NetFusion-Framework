<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Modules/ModuleManifest.php
| Module    : Core\Modules
| Namespace : NetFusion\Core\Modules
| Purpose   : Parser and validator for module.json manifest files.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.0.0
|
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Modules/ModuleManifest.php
| Module    : Core\Modules
| Namespace : NetFusion\Core\Modules
| Purpose   : Parser and validator for module.json manifest files.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.0.0
|
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/Modules/ModuleManifest.php
| Module    : Core\Modules
| Namespace : NetFusion\Core\Modules
| Purpose   : Parser and validator for module.json manifest files.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.0.0
|
|--------------------------------------------------------------------------
*/

namespace NetFusion\Core\Modules;

/**
 * NetFusion Module Manifest
 *
 * Represents and validates the metadata contained within a module's module.json file.
 *
 * @package NetFusion\Core\Modules
 * @version 1.0.0
 */
class ModuleManifest
{
    /**
     * @param string                $name         Human-readable name.
     * @param string                $slug         Unique identifier / directory name.
     * @param string                $version      Semantic version.
     * @param string                $description  Brief description.
     * @param string                $author       Author or organization.
     * @param bool                  $enabled      Whether the module is active.
     * @param int                   $priority     Load priority (lower runs earlier).
     * @param array<int, string>    $providers    List of ServiceProvider FQCNs.
     * @param array<int, string>    $dependencies List of required module slugs.
     * @param array<string, mixed>  $extra        Arbitrary extra metadata.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $slug,
        public readonly string $version      = '1.0.0',
        public readonly string $description  = '',
        public readonly string $author       = 'NetFusion',
        public readonly bool   $enabled      = true,
        public readonly int    $priority     = 100,
        public readonly array  $providers    = [],
        public readonly array  $dependencies = [],
        public readonly array  $extra        = [],
    ) {}

    /**
     * Parse and build a ModuleManifest from a module.json file.
     *
     * @param  string $filePath Absolute path to module.json.
     * @param  string $fallbackSlug Directory name used if slug is not specified.
     * @return static
     *
     * @throws \RuntimeException If the file is invalid or missing required keys.
     */
    public static function fromFile(string $filePath, string $fallbackSlug = ''): static
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new \RuntimeException(
                sprintf('Module manifest file [%s] does not exist or is not readable.', $filePath)
            );
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException(sprintf('Failed to read module manifest [%s].', $filePath));
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            throw new \RuntimeException(
                sprintf('Module manifest [%s] contains invalid JSON: %s', $filePath, json_last_error_msg())
            );
        }

        return static::fromArray($data, $fallbackSlug);
    }

    /**
     * Build manifest instance from raw array.
     *
     * @param array<string, mixed> $data
     * @param string               $fallbackSlug
     * @return static
     */
    public static function fromArray(array $data, string $fallbackSlug = ''): static
    {
        $slug = trim((string) ($data['slug'] ?? $fallbackSlug));
        $name = trim((string) ($data['name'] ?? $slug));

        if ($slug === '') {
            throw new \InvalidArgumentException('Module manifest requires a non-empty "slug" or directory name.');
        }

        return new static(
            name        : $name !== '' ? $name : $slug,
            slug        : $slug,
            version     : (string) ($data['version'] ?? '1.0.0'),
            description : (string) ($data['description'] ?? ''),
            author      : (string) ($data['author'] ?? 'NetFusion'),
            enabled     : (bool) ($data['enabled'] ?? true),
            priority    : (int) ($data['priority'] ?? 100),
            providers   : (array) ($data['providers'] ?? []),
            dependencies: (array) ($data['dependencies'] ?? []),
            extra       : (array) ($data['extra'] ?? []),
        );
    }

    /**
     * Convert manifest properties to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name'         => $this->name,
            'slug'         => $this->slug,
            'version'      => $this->version,
            'description'  => $this->description,
            'author'       => $this->author,
            'enabled'      => $this->enabled,
            'priority'     => $this->priority,
            'providers'    => $this->providers,
            'dependencies' => $this->dependencies,
            'extra'        => $this->extra,
        ];
    }
}
