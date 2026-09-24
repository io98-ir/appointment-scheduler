<?php

declare(strict_types=1);

namespace Vaqtyar\Tools\Rename;

/**
 * A technical identity (ADR-000). Only name, slug, namespace and prefix are
 * chosen; the rest is derived from the slug so that a later rename can map
 * every token back without ambiguity.
 */
final class RenameSpec
{
    public readonly string $constPrefix;

    public function __construct(
        public readonly string $name,
        public readonly string $slug,
        public readonly string $namespace,
        public readonly string $prefix,
    ) {
        // The name goes into a PHP single-quoted string and the plugin header comment.
        if (
            '' === $name
            || \mb_strlen($name) > 64
            || 1 === \preg_match('/[\'\\\\\p{Cc}]|\*\//u', $name)
        ) {
            throw new RenameException(
                'The name must be 1-64 characters without quotes, backslashes, "*/" or line breaks.'
            );
        }
        // Used in PHP identifiers (constants, global prefixes), so no "-" or "_".
        if (1 !== \preg_match('/^[a-z][a-z0-9]{2,31}$/D', $slug)) {
            throw new RenameException('The slug must be 3-32 characters of a-z and 0-9, starting with a letter.');
        }
        if (1 !== \preg_match('/^[A-Z][A-Za-z0-9]{2,31}$/D', $namespace)) {
            throw new RenameException(
                'The namespace must be 3-32 characters of A-Z, a-z and 0-9, starting with a capital.'
            );
        }
        if (1 !== \preg_match('/^[a-z][a-z0-9]{2,5}$/D', $prefix)) {
            throw new RenameException('The prefix must be 3-6 characters of a-z and 0-9, starting with a letter.');
        }

        $this->constPrefix = \strtoupper($slug);
        if ($namespace === $this->constPrefix) {
            throw new RenameException('The namespace must differ from the constant prefix ' . $this->constPrefix . '.');
        }
        foreach ([$slug, \strtolower($namespace)] as $token) {
            if (\str_contains($token, $prefix) || \str_contains($prefix, $token)) {
                throw new RenameException(
                    \sprintf('The prefix "%s" and "%s" must not contain each other.', $prefix, $token)
                );
            }
        }
    }

    /**
     * @param array<mixed> $identity Decoded identity.json.
     */
    public static function fromIdentity(array $identity): self
    {
        $keys = ['name', 'slug', 'namespace', 'prefix', 'const_prefix', 'hook_prefix', 'text_domain', 'rest_namespace'];
        foreach ($keys as $key) {
            if (!isset($identity[$key]) || !\is_string($identity[$key])) {
                throw new RenameException(\sprintf('identity.json: "%s" is missing or not a string.', $key));
            }
        }
        /** @var array<string, string> $identity */
        $spec = new self($identity['name'], $identity['slug'], $identity['namespace'], $identity['prefix']);

        foreach ($spec->identity() as $key => $value) {
            if ($identity[$key] !== $value) {
                throw new RenameException(\sprintf(
                    'identity.json: "%s" is "%s" but must be "%s" (derived from the slug).',
                    $key,
                    $identity[$key],
                    $value
                ));
            }
        }

        return $spec;
    }

    /**
     * @return array<string, string> The identity.json content.
     */
    public function identity(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'namespace' => $this->namespace,
            'const_prefix' => $this->constPrefix,
            'prefix' => $this->prefix,
            'hook_prefix' => $this->slug,
            'text_domain' => $this->slug,
            'rest_namespace' => $this->slug . '/v1',
        ];
    }

    /**
     * Every spelling that appears in code, mapped to the same spelling of $to.
     * The name is not here: it is edited only where it is known to be.
     *
     * @return array<string, string>
     */
    public function tokenMap(self $to): array
    {
        return [
            $this->namespace => $to->namespace,
            $this->constPrefix => $to->constPrefix,
            $this->slug => $to->slug,
            \strtoupper($this->prefix) => \strtoupper($to->prefix),
            $this->prefix => $to->prefix,
        ];
    }

    /**
     * Case-insensitive tokens that must not remain after a rename away from this identity.
     *
     * @return list<string>
     */
    public function searchTokens(): array
    {
        return \array_values(\array_unique([$this->slug, \strtolower($this->namespace), $this->prefix]));
    }
}
