<?php

declare(strict_types=1);

namespace Vaqtyar\Kernel;

/**
 * The plugin's technical identity at runtime (ADR-000). identity.json is the
 * source; tools/rename.php rewrites both and IdentityTest keeps them equal.
 *
 * Table, option, hook, capability and REST names are built from these values
 * by the naming helpers, never written as literals elsewhere. The PHP namespace
 * and the text domain have to be literals, so they have no constant here.
 */
final class Identity
{
    /** Default brand name; the displayed name comes from the white-label settings. */
    public const NAME = 'Vaqtyar';
    public const SLUG = 'vaqtyar';
    /** Tables, options and capabilities. */
    public const PREFIX = 'vqy';
    public const HOOK_PREFIX = 'vaqtyar';
    public const REST_NAMESPACE = 'vaqtyar/v1';
    /** The maker, credited in the plugin header, the plugins list and the admin footer. Not renamed. */
    public const AUTHOR = 'io98';
    public const AUTHOR_URL = 'https://io98.ir';
}
