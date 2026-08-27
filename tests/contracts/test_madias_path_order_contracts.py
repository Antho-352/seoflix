from __future__ import annotations

import re
import unittest

from php_source import source


def method_source(php: str, method: str) -> str:
    """Return one PHP method without depending on indentation or line layout."""
    start = re.search(
        rf"(?:public|private|protected)\s+static\s+function\s+{re.escape(method)}\s*\(",
        php,
    )
    if not start:
        raise AssertionError(f"Missing method {method}()")
    following = re.search(
        r"\n\s*(?:public|private|protected)\s+static\s+function\s+\w+\s*\(",
        php[start.end() :],
    )
    end = start.end() + following.start() if following else len(php)
    return php[start.start() : end]


class MadiasPathOrderContracts(unittest.TestCase):
    def test_release_and_database_versions_are_bumped(self) -> None:
        bootstrap = source("plugin/seoflix-core/seoflix-core.php")

        self.assertRegex(bootstrap, r"Version:\s+0\.27\.4\b")
        self.assertRegex(
            bootstrap,
            r"define\s*\(\s*'SEOFLIX_VERSION'\s*,\s*'0\.27\.4'\s*\)",
        )
        self.assertRegex(
            bootstrap,
            r"define\s*\(\s*'SEOFLIX_DB_VERSION'\s*,\s*'2'\s*\)",
        )

    def test_zip_upgrade_is_hooked_after_taxonomy_registration(self) -> None:
        plugin = source("plugin/seoflix-core/includes/class-plugin.php")
        hook = re.search(
            r"add_action\s*\(\s*'init'\s*,\s*"
            r"\[\s*DB_Schema::class\s*,\s*'maybe_upgrade'\s*\]\s*,\s*(\d+)\s*\)",
            plugin,
        )

        self.assertIsNotNone(hook, "ZIP replacements must run DB_Schema::maybe_upgrade on init")
        self.assertGreater(int(hook.group(1)), 10, "upgrade must run after taxonomy init hooks")

    def test_path_order_uses_json_map_and_keeps_legacy_fallback(self) -> None:
        php = source("plugin/seoflix-core/includes/class-path-order.php")

        self.assertRegex(
            php,
            r"public\s+const\s+META_ORDER_KEY\s*=\s*'_seoflix_path_order'\s*;",
        )
        for method in (
            "sanitize_order_map",
            "get_order_map",
            "get_explicit_order",
            "ordered_video_ids_for_term",
        ):
            self.assertIn(f"function {method}(", php)

        sanitize = method_source(php, "sanitize_order_map")
        read_map = method_source(php, "get_order_map")
        explicit = method_source(php, "get_explicit_order")
        self.assertIn("Meta_Keys::VIDEO_PATH_ORDERS", read_map)
        self.assertIn("json_decode", sanitize)
        self.assertIn("metadata_exists", explicit)
        self.assertIn("self::META_ORDER_KEY", explicit)

    def test_metabox_renders_one_mapped_numeric_input_per_assigned_path(self) -> None:
        php = source("plugin/seoflix-core/includes/class-path-order.php")
        render = method_source(php, "render_metabox")

        self.assertIn("wp_get_object_terms", render)
        self.assertRegex(render, r"foreach\s*\(\s*\$paths\s+as\s+\$[A-Za-z_]\w*\s*\)")
        self.assertIn('type="number"', render)
        self.assertIn('name="seoflix_path_orders[', render)
        self.assertIn("term_id", render)

    def test_metabox_save_is_guarded_and_reconciles_assigned_paths(self) -> None:
        php = source("plugin/seoflix-core/includes/class-path-order.php")
        save = method_source(php, "save_metabox")

        for guard in (
            "wp_verify_nonce",
            "DOING_AUTOSAVE",
            "wp_is_post_revision",
            "current_user_can",
        ):
            self.assertIn(guard, save)
        self.assertIn("wp_get_object_terms", save)
        self.assertIn("get_order_map", save)
        self.assertIn("array_key_exists", save)
        self.assertIn("Meta_Keys::VIDEO_PATH_ORDERS", save)
        self.assertIn("DB_Schema::acquire_path_order_lock", save)
        self.assertIn("DB_Schema::release_path_order_lock", save)
        self.assertIn("finally", save)
        self.assertIn("throw new \\RuntimeException", save)
        self.assertIn("catch ( \\RuntimeException", save)
        self.assertIn("wp_die", save)
        self.assertRegex(
            save,
            r"false\s*===\s*update_post_meta[\s\S]*get_post_meta",
        )
        self.assertRegex(save, r"wp_json_encode\s*\(\s*\(object\)\s*\$orders\s*\)")
        self.assertNotIn("update_post_meta( $post_id, self::META_ORDER_KEY", save)

    def test_order_helper_keeps_missing_meta_videos_and_has_deterministic_ties(self) -> None:
        php = source("plugin/seoflix-core/includes/class-path-order.php")
        ordered = method_source(php, "ordered_video_ids_for_term")

        self.assertIn("get_posts", ordered)
        self.assertNotIn("'meta_key'", ordered)
        self.assertNotIn('"meta_key"', ordered)
        self.assertRegex(ordered, r"'date'\s*=>\s*'ASC'")
        self.assertRegex(ordered, r"'ID'\s*=>\s*'ASC'")
        self.assertIn("get_explicit_order", ordered)
        self.assertIn("update_meta_cache", ordered)
        self.assertIn("_prime_post_caches", ordered)
        self.assertIn("usort", ordered)
        self.assertRegex(ordered, r"\border\b[\s\S]*<=>|<=>[\s\S]*\border\b")

    def test_progress_and_taxonomy_share_the_same_ordered_ids_helper(self) -> None:
        accounts = source("plugin/seoflix-core/includes/class-user-accounts.php")
        progress = method_source(accounts, "path_progress")
        taxonomy = source("theme/seoflix/taxonomy-seoflix_path.php")

        self.assertIn("Path_Order::ordered_video_ids_for_term", progress)
        self.assertIn("\\Seoflix\\Path_Order::ordered_video_ids_for_term", taxonomy)
        for frontend_source in (progress, taxonomy):
            self.assertNotIn("_seoflix_path_order", frontend_source)
            self.assertNotIn("'meta_key'", frontend_source)
        self.assertNotIn("self::is_video_watched", progress)
        self.assertIn("completed=1", progress)

    def test_migration_is_idempotent_and_only_advances_version_after_success(self) -> None:
        schema = source("plugin/seoflix-core/includes/class-db-schema.php")
        migration = method_source(schema, "migrate_legacy_path_orders")
        upgrade = method_source(schema, "maybe_upgrade")

        self.assertIn("Path_Order::META_ORDER_KEY", migration)
        self.assertIn("Meta_Keys::VIDEO_PATH_ORDERS", migration)
        self.assertIn("Path_Order::get_order_map", migration)
        self.assertIn("array_key_exists", migration)
        self.assertIn("wp_get_object_terms", migration)
        self.assertIn("$video_ids", migration)
        self.assertIn("MIGRATION_BATCH_SIZE", schema)
        self.assertIn("MIGRATION_CURSOR_OPTION", schema)
        self.assertIn("update_meta_cache", migration)
        self.assertNotIn("'posts_per_page'", migration)
        self.assertRegex(migration, r"LIMIT\s+%d")
        self.assertIn("wp_json_encode", migration)
        self.assertIn("self::MIGRATION_COMPLETE", migration)
        self.assertIn("self::MIGRATION_PENDING", migration)
        self.assertIn("self::MIGRATION_FAILED", migration)
        self.assertIn("self::acquire_path_order_lock", migration)
        self.assertIn("self::release_path_order_lock", migration)
        self.assertIn("finally", migration)

        self.assertIn("self::install", upgrade)
        self.assertIn("self::migrate_legacy_path_orders", upgrade)
        migration_call = upgrade.index("self::migrate_legacy_path_orders")
        version_write = upgrade.index("update_option( 'seoflix_db_version'")
        self.assertLess(migration_call, version_write)
        self.assertIn("self::MIGRATION_COMPLETE", upgrade[:version_write])
        self.assertNotRegex(schema, r"\b(?:DROP|TRUNCATE)\s+TABLE\b")

    def test_schema_install_fails_closed_for_each_dbdelta_and_verifies_tables(self) -> None:
        schema = source("plugin/seoflix-core/includes/class-db-schema.php")
        install = method_source(schema, "install")
        apply_statement = method_source(schema, "apply_schema_statement")

        self.assertGreaterEqual(install.count("self::apply_schema_statement("), 3)
        self.assertIn("dbDelta", apply_statement)
        self.assertIn("$wpdb->last_error", apply_statement)
        self.assertIn("SHOW TABLES LIKE", apply_statement)
        self.assertIn("SHOW COLUMNS", apply_statement)
        self.assertIn("SHOW INDEX", apply_statement)
        self.assertIn("$required_columns", apply_statement)
        self.assertIn("$required_indexes", apply_statement)
        self.assertIn("$wpdb->prepare", apply_statement)
        self.assertRegex(apply_statement, r"return\s+false")

    def test_course_schema_uses_the_same_multi_path_order(self) -> None:
        seo = source("plugin/seoflix-core/includes/class-seo.php")
        course = method_source(seo, "build_course")

        self.assertIn("Path_Order::ordered_video_ids_for_term", course)
        self.assertRegex(course, r"'post__in'\s*=>\s*\$video_ids")
        self.assertRegex(course, r"'orderby'\s*=>\s*'post__in'")
        self.assertNotIn("_seoflix_path_order", course)
        self.assertNotIn("meta_value_num", course)

    def test_fresh_activation_uses_the_upgrade_runner(self) -> None:
        activator = source("plugin/seoflix-core/includes/class-activator.php")
        activate = method_source(activator, "activate")

        self.assertIn("Taxonomies::register", activate)
        self.assertIn("DB_Schema::maybe_upgrade", activate)
        self.assertNotIn("DB_Schema::install", activate)


if __name__ == "__main__":
    unittest.main()
