<?php
/**
 * Stand-in for WordPress' wp-admin/includes/upgrade.php.
 *
 * EntityManager::__callStatic() does `require_once ABSPATH . 'wp-admin/includes/upgrade.php';`
 * to pull in dbDelta(). In tests, ABSPATH points at tests/fixtures/abspath, and dbDelta() is
 * mocked via Brain Monkey before it's ever called, so this file only needs to exist — it must
 * not define dbDelta() itself, or the mock can't replace it.
 */
