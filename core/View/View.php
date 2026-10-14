<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion  Framework
|--------------------------------------------------------------------------
|
| File      : core/View/View.php
| Module    : Core\View
| Namespace : NetFusion\Core\View
| Purpose   : Template renderer supporting layout inheritance, sections, escaping, and partial includes.
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

namespace NetFusion\Core\View;

use RuntimeException;

/**
 * NetFusion View Instance
 *
 * Represents an individual template evaluation context.
 *
 * @package NetFusion\Core\View
 * @version 1.0.0
 */
class View
{
    /**
     * Parent layout template name (if extending a layout).
     */
    protected ?string $layout = null;

    /**
     * Captured template sections.
     *
     * @var array<string, string>
     */
    protected array $sections = [];

    /**
     * Current open section name being buffered.
     */
    protected ?string $currentSection = null;

    /**
     * @param ViewFactory          $factory
     * @param string               $filePath Absolute path to template file.
     * @param array<string, mixed> $data     View data variables.
     */
    public function __construct(
        protected readonly ViewFactory $factory,
        protected readonly string      $filePath,
        protected array                $data = []
    ) {}

    /**
     * Declare parent layout to extend.
     *
     * @param string $layout e.g. 'layouts.app' or 'system::layouts.main'
     */
    public function layout(string $layout): void
    {
        $this->layout = $layout;
    }

    /**
     * Start buffering content for a named section.
     */
    public function section(string $name): void
    {
        $this->currentSection = $name;
        ob_start();
    }

    /**
     * Stop buffering content for the current section.
     *
     * @throws RuntimeException
     */
    public function endSection(): void
    {
        if ($this->currentSection === null) {
            throw new RuntimeException('Cannot call endSection() without an active section.');
        }

        $this->sections[$this->currentSection] = ob_get_clean();
        $this->currentSection = null;
    }

    /**
     * Yield the content of a named section.
     */
    public function yield(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    /**
     * Escape variable for safe HTML output.
     */
    public function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Include a partial view inside the current template.
     *
     * @param  string               $view Partial view name.
     * @param  array<string, mixed> $data Extra data variables.
     * @return string
     */
    public function include(string $view, array $data = []): string
    {
        return $this->factory->render($view, array_merge($this->data, $data));
    }

    /**
     * Render the template and return the evaluated string.
     */
    public function render(): string
    {
        if (!is_file($this->filePath)) {
            throw new RuntimeException("View file [{$this->filePath}] does not exist.");
        }

        // Merge global shared data from factory
        $mergedData = array_merge($this->factory->getSharedData(), $this->data);

        // Extract variables into current local symbol table
        extract($mergedData, EXTR_SKIP);

        ob_start();
        include $this->filePath;
        $content = ob_get_clean();

        // If child template defined a layout, render the parent layout
        if ($this->layout !== null) {
            $layoutPath = $this->factory->resolvePath($this->layout);

            // Re-render parent with child sections populated
            $layoutView = new self($this->factory, $layoutPath, $mergedData);
            $layoutView->sections = $this->sections;

            // In case the child template didn't wrap content in a section, make it default to 'content'
            if (!isset($layoutView->sections['content'])) {
                $layoutView->sections['content'] = $content;
            }

            return $layoutView->render();
        }

        return $content;
    }

    /**
     * String casting helper.
     */
    public function __toString(): string
    {
        try {
            return $this->render();
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }
}
