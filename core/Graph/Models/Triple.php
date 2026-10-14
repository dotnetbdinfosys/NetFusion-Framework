<?php

/**
 * DotNetBD Enterprise Framework
 *
 * @package    DotNetBD
 * @author     DotNetBD Development Team
 * @copyright  2026 DotNetBD
 * @license    MIT License
 * @version    1.0.0
 */

declare(strict_types=1);

namespace NetFusion\Core\Graph\Models;

class Triple
{
    /**
     * @param string $subject
     * @param string $predicate
     * @param string $object
     * @param bool $inferred
     */
    public function __construct(
        protected string $subject,
        protected string $predicate,
        protected string $object,
        protected bool $inferred = false
    ) {
    }

    public static function create(string $subject, string $predicate, string $object, bool $inferred = false): self
    {
        return new self($subject, $predicate, $object, $inferred);
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getPredicate(): string
    {
        return $this->predicate;
    }

    public function getObject(): string
    {
        return $this->object;
    }

    public function isInferred(): bool
    {
        return $this->inferred;
    }

    public function matches(?string $subject = null, ?string $predicate = null, ?string $object = null): bool
    {
        if ($subject !== null && $subject !== '?' && $subject !== '*' && $this->subject !== $subject) {
            return false;
        }
        if ($predicate !== null && $predicate !== '?' && $predicate !== '*' && $this->predicate !== $predicate) {
            return false;
        }
        if ($object !== null && $object !== '?' && $object !== '*' && $this->object !== $object) {
            return false;
        }
        return true;
    }

    public function getKey(): string
    {
        return "{$this->subject}|{$this->predicate}|{$this->object}";
    }

    public function toArray(): array
    {
        return [
            'subject' => $this->subject,
            'predicate' => $this->predicate,
            'object' => $this->object,
            'inferred' => $this->inferred,
        ];
    }
}
