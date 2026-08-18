<?php
/**
 * PHPUnit bootstrap.
 *
 * Loads the pure, dependency-free functions under test. No WordPress
 * bootstrap is required for the unit suite — the core logic lives in
 * src/functions.php and never touches WordPress functions directly.
 */

require_once __DIR__ . '/../src/functions.php';
