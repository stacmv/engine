<?php
/**
 * Absolute path to a file under the engine's src/ directory.
 */
function engine_src(string $relative): string
{
    return dirname(__DIR__, 2) . '/src/' . ltrim($relative, '/');
}

/**
 * require_once an engine source file by its path relative to src/.
 */
function engine_require(string $relative): void
{
    require_once engine_src($relative);
}
