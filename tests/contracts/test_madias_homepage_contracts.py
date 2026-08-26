from __future__ import annotations

import json
import re
import subprocess
import textwrap
import unittest
from pathlib import Path

from php_source import REPO_ROOT, source


HOMEPAGE = "plugin/seoflix-core/includes/class-homepage.php"
ADMIN = "plugin/seoflix-core/admin/class-admin-homepage.php"
FRONTEND = "plugin/seoflix-core/includes/class-frontend.php"
SEO = "plugin/seoflix-core/includes/class-seo.php"
FRONT_PAGE = "theme/seoflix/front-page.php"
PATHS_INDEX = "theme/seoflix/page-paths-index.php"
HEADER = "theme/seoflix/header.php"
FOOTER = "theme/seoflix/footer.php"
STYLE = "theme/seoflix/style.css"
TOKENS = "theme/seoflix/assets/css/tokens.css"

PROMISE = "Apprends le business web sans perdre des heures sur YouTube."
PANEL_TITLE = "Apprends l’édition de sites sans y laisser 500€."
PANEL_PARAGRAPH = (
    "La sélection complète des meilleures vidéos SEO, affiliation, vente de liens "
    "et YouTube business, déjà triées et organisées en parcours."
)
PANEL_DISCLAIMER = (
    "Sans formation à vendre à la fin, sans code obligatoire, sans compte premium caché."
)
ABOUT = (
    "Cela fait plus de 5 ans que je fais du freelancing et de l'édition de sites "
    "(SEO, Affiliation, Youtube) et que je regarde tous les contenus sur ces sujets. "
    "Je voulais permettre aux débutants et aux initiés de perdre le moins de temps "
    "possible en sélectionnant les vidéos qui apportent de la valeur."
)
EXPECTED_PATHS = [
    ("apprendre-l-affiliation", "Affiliation SEO"),
    ("apprendre-youtube", "Youtube"),
    ("apprendre-la-vente-de-liens", "Vente de liens"),
    ("apprendre-ia-automatisation", "IA et automatisation"),
    ("apprendre-la-vente-de-leads", "Vente de leads"),
    ("apprendre-le-freelancing", "Freelancing"),
]
FIXED_MARKERS = [
    'id="parcours"',
    'id="nouveautes"',
    'id="meilleurs-outils"',
    'id="promesse"',
    'id="parcours-selectionnes"',
    'id="tous-les-parcours"',
    'id="a-propos"',
    "seoflix_render_newsletter(",
    'id="derniers-articles"',
]


def optional_source(relative_path: str) -> str:
    path = Path(REPO_ROOT, relative_path)
    return path.read_text(encoding="utf-8") if path.exists() else ""


def method_body(text: str, name: str) -> str:
    signature = re.search(rf"function\s+{re.escape(name)}\s*\(", text)
    if not signature:
        raise AssertionError(f"Missing method {name}()")
    opening = text.find("{", signature.end())
    if opening < 0:
        raise AssertionError(f"Missing method body for {name}()")
    depth = 0
    quote: str | None = None
    escaped = False
    line_comment = False
    block_comment = False
    index = opening
    while index < len(text):
        char = text[index]
        next_char = text[index + 1] if index + 1 < len(text) else ""
        if line_comment:
            line_comment = char != "\n"
        elif block_comment:
            if char == "*" and next_char == "/":
                block_comment = False
                index += 1
        elif quote:
            if escaped:
                escaped = False
            elif char == "\\":
                escaped = True
            elif char == quote:
                quote = None
        elif char in ("'", '"', "`"):
            quote = char
        elif char == "/" and next_char == "/":
            line_comment = True
            index += 1
        elif char == "/" and next_char == "*":
            block_comment = True
            index += 1
        elif char == "#":
            line_comment = True
        elif char == "{":
            depth += 1
        elif char == "}":
            depth -= 1
            if depth == 0:
                return text[opening + 1 : index]
        index += 1
    raise AssertionError(f"Unbalanced method {name}()")


class MadiasHomepageContracts(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        cls.homepage = source(HOMEPAGE)
        cls.admin = source(ADMIN)
        cls.frontend = source(FRONTEND)
        cls.seo = source(SEO)
        cls.front_page = source(FRONT_PAGE)
        cls.paths_index = optional_source(PATHS_INDEX)
        cls.header = source(HEADER)
        cls.footer = source(FOOTER)
        cls.style = source(STYLE)
        cls.tokens = source(TOKENS)

    def test_fixed_path_catalog_has_exact_order_names_and_slugs(self) -> None:
        catalog = method_body(self.homepage, "path_definitions")
        pairs = re.findall(
            r"'slug'\s*=>\s*'([^']+)'\s*,\s*'name'\s*=>\s*'([^']+)'",
            catalog,
        )
        self.assertEqual(pairs, EXPECTED_PATHS)
        self.assertEqual(catalog.count("'slug'"), 6)
        self.assertIn("'icon'", catalog)

    def test_launch_paths_are_normalized_with_relationship_and_order_migration(self) -> None:
        activator = source("plugin/seoflix-core/includes/class-activator.php")
        for legacy_slug in (
            "apprendre-le-seo",
            "apprendre-le-netlinking",
            "apprendre-le-business",
            "apprendre-lia-et-lautomatisation",
        ):
            self.assertIn(legacy_slug, activator)
        version = re.search(r"TERMS_SEED_VERSION\s*=\s*(\d+)", activator)
        self.assertIsNotNone(version)
        self.assertGreaterEqual(int(version.group(1)), 4)
        for token in ("Homepage::path_definitions", "get_objects_in_term", "wp_set_object_terms", "wp_delete_term", "Meta_Keys::VIDEO_PATH_ORDERS"):
            self.assertIn(token, activator)

    def test_config_is_targeted_bounded_and_backward_compatible(self) -> None:
        defaults = method_body(self.homepage, "defaults")
        config = method_body(self.homepage, "get_config")
        self.assertIn(PROMISE, defaults)
        self.assertIn("'cta_text'", defaults)
        self.assertIn("Commencer à apprendre", defaults)
        self.assertIn("'best_tool_ids'", defaults)
        self.assertIn("'featured_path_slugs'", defaults)
        for slug in (
            "apprendre-ia-automatisation",
            "apprendre-youtube",
            "apprendre-la-vente-de-liens",
        ):
            self.assertIn(slug, defaults)
        for legacy in ("ia-et-automatisation", "'youtube'", "'vente-de-liens'"):
            self.assertIn(legacy, method_body(self.homepage, "normalize_path_slugs"))
        self.assertIn("'fixed_blocks'", defaults)
        self.assertNotIn("rotating_words", defaults)
        self.assertNotIn("show_stats", defaults)
        self.assertIn("$saved['sections']", config, "legacy option sections must be read safely")
        self.assertIn("normalize_tool_ids", self.homepage)
        self.assertIn("normalize_path_slugs", self.homepage)
        self.assertRegex(self.homepage, r"array_slice\s*\([^;]+,\s*0\s*,\s*self::MAX_BEST_TOOLS")
        self.assertRegex(self.homepage, r"array_slice\s*\([^;]+,\s*0\s*,\s*3\s*\)")

    def test_admin_exposes_only_targeted_fixed_configuration(self) -> None:
        for field in (
            'name="hero[title]"',
            'name="hero[subtitle]"',
            'name="hero[cta_text]"',
            'name="best_tool_ids"',
            'name="featured_path_slugs[]"',
            'name="fixed_blocks[',
        ):
            with self.subTest(field=field):
                self.assertIn(field, self.admin)
        self.assertIn("absint", self.admin)
        self.assertIn("sanitize_key", self.admin)
        self.assertNotIn('name="sections[', self.admin)
        self.assertNotIn("section_labels", self.admin)
        self.assertNotIn("rotating_words", self.admin)
        self.assertNotRegex(self.admin, r"onclick\s*=")

    def test_homepage_uses_fixed_assembly_in_exact_order(self) -> None:
        for marker in FIXED_MARKERS:
            with self.subTest(marker=marker):
                self.assertIn(marker, self.front_page)
        positions = [self.front_page.index(marker) for marker in FIXED_MARKERS if marker in self.front_page]
        self.assertEqual(len(positions), len(FIXED_MARKERS))
        self.assertEqual(positions, sorted(positions), "fixed homepage blocks changed order")
        self.assertIn(PROMISE, self.front_page)
        self.assertIn("Commencer à apprendre", self.front_page)
        self.assertRegex(self.front_page, r"home_url\s*\(\s*'/commencer/'\s*\)")
        self.assertNotIn("visible_sections", self.front_page)
        self.assertNotRegex(self.front_page, r"foreach\s*\(\s*\$sections\s+as")
        self.assertNotRegex(self.front_page, r"switch\s*\(\s*\$type")
        self.assertNotIn("rotating_words", self.front_page)
        self.assertNotRegex(self.front_page, r"orderby['\"]?\s*=>\s*['\"]rand")
        self.assertNotRegex(self.front_page, r"\sstyle\s*=")

    def test_homepage_rotates_the_six_path_labels_accessibly(self) -> None:
        catalog = method_body(self.homepage, "path_definitions")
        self.assertEqual(catalog.count("'hero_label'"), 6)
        for token in (
            'id="sx-rotate"',
            'class="sx-rotate"',
            'class="screen-reader-text"',
            'aria-hidden="true"',
            "wp_json_encode",
            "prefers-reduced-motion: reduce",
            "setInterval",
            "2200",
        ):
            self.assertIn(token, self.front_page)
        self.assertNotIn('aria-live=', self.front_page)

    def test_homepage_queries_real_ordered_content_without_fake_data(self) -> None:
        self.assertIn("Homepage::path_definitions", self.front_page)
        self.assertIn("get_term_by( 'slug'", self.front_page)
        self.assertIn("Path_Order::ordered_video_ids_for_term", self.front_page)
        self.assertIn("'post_status'", self.front_page)
        self.assertIn("'publish'", self.front_page)
        self.assertIn("'post__in'", self.front_page)
        self.assertIn("'orderby'", self.front_page)
        self.assertIn("'post__in'", self.front_page)
        self.assertIn("Parcours indisponible", self.front_page)
        self.assertIn("Aucune vidéo publiée", self.front_page)
        self.assertIn("$definition['description']", self.front_page)
        self.assertNotIn("Description indisponible", self.front_page)

    def test_manual_tools_and_native_posts_are_bounded_and_ordered(self) -> None:
        self.assertRegex(self.front_page, r"'post_type'\s*=>\s*'seoflix_product'")
        self.assertRegex(self.front_page, r"'post__in'\s*=>\s*\$best_tool_ids")
        self.assertRegex(self.front_page, r"'orderby'\s*=>\s*'post__in'")
        self.assertRegex(self.front_page, r"'post_status'\s*=>\s*'publish'")
        self.assertIn("seoflix_render_product_card", self.front_page)
        self.assertRegex(self.front_page, r"'post_type'\s*=>\s*'post'")
        self.assertRegex(self.front_page, r"'posts_per_page'\s*=>\s*4\b")
        self.assertIn("seoflix_render_post_card", self.front_page)

    def test_exact_promise_about_copy_and_single_homepage_newsletter(self) -> None:
        for copy in (PANEL_TITLE, PANEL_PARAGRAPH, PANEL_DISCLAIMER, ABOUT):
            with self.subTest(copy=copy[:24]):
                self.assertIn(copy, self.front_page)
        self.assertRegex(
            self.front_page,
            re.compile(rf"<em[^>]*>\s*{re.escape(PANEL_DISCLAIMER)}\s*</em>", re.S),
        )
        self.assertEqual(self.front_page.count("seoflix_render_newsletter("), 1)
        self.assertIn("! empty( $blocks['newsletter'] )", self.front_page)
        self.assertIn("! is_front_page()", self.footer)
        self.assertEqual(self.footer.count("seoflix_render_newsletter("), 1)

    def test_visible_brand_is_weas_without_internal_renames(self) -> None:
        self.assertGreaterEqual(self.header.count("WEAS"), 2)
        self.assertGreaterEqual(self.footer.count("WEAS"), 2)
        self.assertRegex(self.style, r"Theme Name:\s*WEAS\b")
        self.assertIn("Text Domain: seoflix", self.style)
        combined = self.homepage + self.admin + self.frontend + self.seo + self.front_page
        for identifier in (
            "seoflix_homepage_config",
            "seoflix_video",
            "seoflix_product",
            "seoflix_path",
            "namespace Seoflix",
        ):
            with self.subTest(identifier=identifier):
                self.assertIn(identifier, combined)
        self.assertNotIn("seo<em>flix</em>", self.header)
        self.assertNotRegex(self.footer, r">\s*Seoflix\s*<")

    def test_parcours_rewrite_upgrade_is_versioned_for_zip_replacements(self) -> None:
        activator = source("plugin/seoflix-core/includes/class-activator.php")
        self.assertIn("REWRITE_SCHEMA_VERSION", self.frontend)
        self.assertIn("seoflix_frontend_rewrite_version", self.frontend)
        self.assertIn("maybe_upgrade_rewrites", self.frontend)
        self.assertIn("flush_rewrite_rules( false )", self.frontend)
        self.assertIn("Frontend::REWRITE_SCHEMA_VERSION", activator)

    def test_parcours_route_query_var_template_and_activation_strategy(self) -> None:
        rewrite = method_body(self.frontend, "register_rewrite")
        loader = method_body(self.frontend, "load_template")
        self.assertRegex(rewrite, r"add_rewrite_rule\s*\(\s*'\^parcours/\?\$'")
        self.assertIn("=paths", rewrite)
        self.assertIn("case 'paths':", loader)
        self.assertIn("page-paths-index.php", loader)
        self.assertIn("status_header( 200 )", loader)
        self.assertIn("self::QUERY_VAR", method_body(self.frontend, "add_query_var"))
        self.assertIn("self::is_view", method_body(self.frontend, "fix_404"))
        self.assertIn("pre_handle_404", method_body(self.frontend, "init"))
        handler_404 = method_body(self.frontend, "pre_handle_404")
        self.assertIn("return true", handler_404)
        self.assertIn("status_header( 200 )", handler_404)
        upgrade = method_body(self.frontend, "maybe_upgrade_rewrites")
        self.assertEqual(upgrade.count("flush_rewrite_rules"), 1)
        self.assertIn("REWRITE_SCHEMA_VERSION", upgrade)
        activator = source("plugin/seoflix-core/includes/class-activator.php")
        self.assertLess(activator.index("Frontend::register_rewrite()"), activator.index("flush_rewrite_rules()"))

    def test_parcours_preempts_wordpress_handle_404_in_php(self) -> None:
        source_path = json.dumps(str(REPO_ROOT / FRONTEND))
        harness = textwrap.dedent(
            """
            <?php
            namespace {
                define('ABSPATH', __DIR__);
                $GLOBALS['sx_view'] = 'paths';
                $GLOBALS['sx_status'] = 0;
                $GLOBALS['sx_template_present'] = true;
                function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value)); }
                function get_query_var($key) { return $GLOBALS['sx_view']; }
                function is_admin() { return false; }
                function status_header($code) { $GLOBALS['sx_status'] = (int) $code; }
                function locate_template($template) { return $GLOBALS['sx_template_present'] ? '/theme/' . $template : ''; }
                class WP_Query {
                    public bool $is_404 = true;
                    public bool $is_home = true;
                    public function is_main_query() { return true; }
                }
            }
            namespace Seoflix {}
            namespace {
                require __SOURCE__;
                $paths = new WP_Query();
                $handled = \\Seoflix\\Frontend::pre_handle_404(false, $paths);
                $paths_status = $GLOBALS['sx_status'];
                $GLOBALS['sx_template_present'] = false;
                $GLOBALS['sx_status'] = 0;
                $missing = new WP_Query();
                $missing_untouched = \\Seoflix\\Frontend::pre_handle_404(false, $missing);
                $GLOBALS['sx_view'] = 'other';
                $other = new WP_Query();
                $untouched = \\Seoflix\\Frontend::pre_handle_404(false, $other);
                echo json_encode([
                    'handled'=>$handled,
                    'paths'=>[$paths->is_404, $paths->is_home, $paths_status],
                    'missing'=>[$missing_untouched, $missing->is_404, $missing->is_home, $GLOBALS['sx_status']],
                    'other'=>[$untouched, $other->is_404, $other->is_home],
                ]);
            }
            """
        ).lstrip().replace("__SOURCE__", source_path)
        result = subprocess.run(["php"], input=harness, text=True, capture_output=True, check=False)
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual(
            {
                "handled": True,
                "paths": [False, False, 200],
                "missing": [False, True, True, 0],
                "other": [False, True, True],
            },
            json.loads(result.stdout),
        )

    def test_parcours_template_has_six_real_cards_one_h1_and_optional_progress(self) -> None:
        self.assertTrue(Path(REPO_ROOT, PATHS_INDEX).is_file())
        self.assertEqual(len(re.findall(r"<h1\b", self.paths_index, re.I)), 1)
        self.assertIn("Homepage::path_definitions", self.paths_index)
        self.assertIn("get_term_by( 'slug'", self.paths_index)
        self.assertIn("get_term_link", self.paths_index)
        self.assertIn("Path_Order::ordered_video_ids_for_term", self.paths_index)
        self.assertIn("'post_status'", self.paths_index)
        self.assertIn("Meta_Keys::VIDEO_DURATION", self.paths_index)
        self.assertIn("$definition['description']", self.paths_index)
        self.assertNotIn("Description indisponible", self.paths_index)
        self.assertIn("Parcours indisponible", self.paths_index)
        self.assertIn("seoflix_user_accounts_enabled", self.paths_index)
        self.assertIn("is_user_logged_in", self.paths_index)
        self.assertIn("User_Accounts::path_progress", self.paths_index)
        self.assertIn("<progress", self.paths_index)
        self.assertIn("next_video_id", self.paths_index)
        self.assertIn("$definition['name']", self.paths_index)
        self.assertNotIn("$term->name", self.paths_index)
        self.assertIn("$definition['name']", self.front_page)
        self.assertNotIn("$term->name", self.front_page)
        self.assertNotIn("$featured_term->name", self.front_page)
        self.assertIn("$start_url", self.paths_index)
        self.assertIn('class="sx-path-card__continue"', self.paths_index)
        self.assertIn("$start_label = 'Commencer'", self.paths_index)
        self.assertIn("if ( $count > 0 && $start_url )", self.paths_index)
        self.assertNotRegex(self.paths_index, r"\sstyle\s*=")
        self.assertNotIn("<script", self.paths_index.lower())

    def test_parcours_has_canonical_breadcrumbs_and_one_itemlist_owner(self) -> None:
        canonical = method_body(self.seo, "current_canonical")
        jsonld = method_body(self.seo, "render_jsonld")
        self.assertIn("Frontend::is_view( 'paths' )", canonical)
        self.assertRegex(canonical, r"home_url\s*\(\s*'/parcours/'\s*\)")
        self.assertIn("Frontend::is_view( 'paths' )", jsonld)
        self.assertIn("build_paths_index_item_list", jsonld)
        self.assertIn("build_paths_index_breadcrumbs", jsonld)
        itemlist = method_body(self.seo, "build_paths_index_item_list")
        self.assertIn("Homepage::path_definitions", itemlist)
        self.assertIn("get_term_by", itemlist)
        self.assertIn("'@type'", itemlist)
        self.assertIn("'ItemList'", itemlist)
        self.assertNotIn("application/ld+json", self.paths_index)
        self.assertRegex(self.paths_index, re.compile(r"aria-label=[\"']Fil d[’']Ariane", re.I))

    def test_homepage_and_parcours_css_is_accessible_responsive_and_on_brand(self) -> None:
        combined_css = self.tokens + "\n" + self.style
        for token in ("#0B0B0F", "#16161D", "#FF2D3F", "#F5F5F7"):
            with self.subTest(token=token):
                self.assertIn(token, combined_css)
        for selector in (
            ".sx-home",
            ".sx-home-hero",
            ".sx-home-paths",
            ".sx-path-card",
            ".sx-home-promise",
            ".sx-home-about",
            ".sx-paths-index",
            ".sx-path-card__progress",
        ):
            with self.subTest(selector=selector):
                self.assertIn(selector, self.style)
        self.assertRegex(self.style, r"\.sx-(?:home-cta|home-hero__cta)[^{]*\{[^}]*min-height\s*:\s*44px")
        self.assertRegex(self.style, r":focus-visible")
        self.assertRegex(self.style, r"@media\s*\(\s*max-width\s*:\s*20rem\s*\)")
        self.assertRegex(self.style, r"@media\s*\(\s*prefers-reduced-motion\s*:\s*reduce\s*\)")
        self.assertRegex(self.style, r"overflow-wrap\s*:\s*anywhere")
        self.assertNotRegex(
            self.style,
            re.compile(r"background(?:-color)?\s*:\s*(?:#FF2D3F|var\(--sx-color-accent\))\s*;[^}]*color\s*:\s*(?:#fff(?:fff)?|white|var\(--sx-color-text\))", re.I | re.S),
        )
        self.assertRegex(
            self.style,
            re.compile(r"background(?:-color)?\s*:\s*var\(--sx-color-accent\)\s*;[^}]*color\s*:\s*(?:#0B0B0F|var\(--sx-color-bg\))", re.I | re.S),
        )


if __name__ == "__main__":
    unittest.main()
