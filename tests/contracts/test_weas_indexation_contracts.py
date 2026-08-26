from __future__ import annotations

import unittest

from php_source import source


class WeasIndexationContracts(unittest.TestCase):
    def test_weas_seo_is_the_single_canonical_owner(self) -> None:
        seo = source("plugin/seoflix-core/includes/class-seo.php")
        frontend = source("plugin/seoflix-core/includes/class-frontend.php")
        self.assertIn("remove_action( 'wp_head', 'rel_canonical' )", seo)
        self.assertEqual((seo + frontend).count('rel="canonical"'), 1)
        self.assertNotIn("render_canonical", frontend)
        canonical = seo[seo.index("private static function current_canonical"):seo.index("private static function apply_template")]
        for token in (
            "Frontend::is_view( 'topics' )",
            "home_url( '/categories/' )",
            "Frontend::is_view( 'paths' )",
            "home_url( '/parcours/' )",
            "Frontend::is_view( 'business-finder' )",
            "home_url( '/commencer/' )",
            "Auth_Pages::QV_LOGIN",
            "home_url( '/connexion/' )",
            "Auth_Pages::QV_REGISTER",
            "home_url( '/inscription/' )",
            "Auth_Pages::QV_LOSTPASS",
            "home_url( '/mot-de-passe-oublie/' )",
            "get_query_var( 'seoflix_dashboard' )",
            "home_url( '/mon-parcours/' )",
        ):
            self.assertIn(token, canonical)

    def test_seo_uses_the_native_wordpress_robots_pipeline_once(self) -> None:
        php = source("plugin/seoflix-core/includes/class-seo.php")
        self.assertIn("add_filter( 'wp_robots'", php)
        start = php.index("public static function render_meta_tags")
        end = php.index("public static function render_open_graph", start)
        self.assertNotIn('<meta name="robots"', php[start:end])

    def test_native_robots_filter_clears_conflicting_directives(self) -> None:
        php = source("plugin/seoflix-core/includes/class-seo.php")
        start = php.index("public static function filter_wp_robots")
        end = php.index("private static function current_canonical", start)
        block = php[start:end]
        self.assertIn("self::current_seo_robots()", block)
        for token in ("'index'", "'noindex'", "'follow'", "'nofollow'"):
            self.assertIn(token, block)

    def test_blog_public_zero_forces_noindex_meta_before_route_logic(self) -> None:
        php = source("plugin/seoflix-core/includes/class-seo.php")
        start = php.index("private static function current_seo_robots")
        end = php.index("private static function current_canonical", start)
        block = php[start:end]
        blog_public = "get_option( 'blog_public', '1' )"
        self.assertIn(blog_public, block)
        self.assertIn("return 'noindex, follow';", block)
        self.assertLess(block.index(blog_public), block.index("is_search()"))

    def test_blog_public_zero_forces_closed_virtual_robots_txt(self) -> None:
        php = source("plugin/seoflix-core/admin/class-admin-seo-tools.php")
        start = php.index("public static function filter_robots_txt")
        end = php.index("public static function render_robots_page", start)
        block = php[start:end]
        blog_public = "get_option( 'blog_public', '1' )"
        self.assertIn(blog_public, block)
        self.assertIn('return "User-agent: *\\nDisallow: /\\n";', block)
        self.assertLess(block.index(blog_public), block.index("self::OPTION_ROBOTS"))


if __name__ == "__main__":
    unittest.main()
