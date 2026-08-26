from __future__ import annotations

import os
import re
import shutil
import subprocess
import sys
import tempfile
import textwrap
import unittest
from pathlib import Path

from php_source import REPO_ROOT, source


FOCUS = "plugin/seoflix-core/includes/class-focus.php"
FUNCTIONS = "theme/seoflix/functions.php"
HEADER = "theme/seoflix/header.php"
CSS = "theme/seoflix/assets/css/focus.css"


def compact(text: str) -> str:
    return re.sub(r"\s+", " ", text).strip()


class MadiasFocusContracts(unittest.TestCase):
    def test_focus_module_is_booted(self) -> None:
        self.assertTrue((REPO_ROOT / FOCUS).is_file())
        plugin = source("plugin/seoflix-core/includes/class-plugin.php")
        self.assertIn("includes/class-focus.php", plugin)
        self.assertIn("Focus::init();", plugin)

    def test_focus_banner_runtime_class_guard_resolves_the_loaded_class(self) -> None:
        functions = source(FUNCTIONS)
        banner = functions[functions.index("function seoflix_render_focus_banner") :]
        match = re.search(r"class_exists\(\s*('(?:\\\\.|[^'])*')\s*\)", banner)
        self.assertIsNotNone(match, "class_exists guard missing")
        literal = match.group(1)
        harness = textwrap.dedent(
            f"""
            <?php
            namespace Seoflix {{ class Focus {{}} }}
            namespace {{
                $name = {literal};
                echo json_encode([ 'name' => $name, 'exists' => class_exists($name) ]);
            }}
            """
        ).lstrip()
        result = subprocess.run(["php"], input=harness, text=True, capture_output=True, check=False)
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual({"name": "\\Seoflix\\Focus", "exists": True}, __import__("json").loads(result.stdout))

    def test_personalized_surfaces_are_explicitly_excluded_from_full_page_cache(self) -> None:
        php = compact(source(FOCUS))
        self.assertIn("template_redirect", php)
        self.assertIn("prevent_personalized_cache", php)
        self.assertIn("function is_focus_surface", php)
        self.assertIn("is_search()", php)
        self.assertIn("get_query_var( 'post_type' )", php)
        self.assertIn("self::is_focus_surface()", php)
        self.assertIn("DONOTCACHEPAGE", php)
        self.assertIn("nocache_headers()", php)
        self.assertIn("Vary: Cookie", php)

    def test_video_taxonomies_and_explicit_secondary_helpers_are_filtered(self) -> None:
        php = compact(source(FOCUS))
        functions = source(FUNCTIONS)
        homepage = source("theme/seoflix/front-page.php")
        self.assertIn("is_tax( [ Taxonomies::TOPIC, Taxonomies::FORMAT ] )", php)
        self.assertIn("self::QUERY_VAR_APPLY", php)
        self.assertGreaterEqual(functions.count("'seoflix_focus_apply' => 1"), 2)
        self.assertEqual(2, homepage.count("'seoflix_focus_apply' => 1"))
        self.assertIn("$get_path_videos", homepage)
        self.assertIn("$new_videos = get_posts", homepage)

    def test_existing_tax_query_is_grouped_under_mandatory_and(self) -> None:
        php = compact(source(FOCUS))
        self.assertIn("'relation' => 'AND'", php)
        self.assertIn("$tax_query, $focus_clause", php)
        self.assertNotIn("$tax_query[] = [ 'taxonomy' => Taxonomies::PATH", php)

    def test_focus_catalog_is_exact_ordered_and_migration_safe(self) -> None:
        php = source(FOCUS)
        self.assertNotIn("PATH_SLUGS", php)
        available = php[php.index("function available_paths") : php.index("function valid_term_for_slug")]
        self.assertIn("get_terms", available)
        self.assertIn("seoflix_path_public_order", available)
        self.assertIn("seoflix_focus_enabled", available)
        activator = source("plugin/seoflix-core/includes/class-activator.php")
        homepage = source("plugin/seoflix-core/includes/class-homepage.php")
        catalog = homepage[homepage.index("function path_definitions") : homepage.index("function public_path_terms")]
        self.assertEqual(6, catalog.count("'slug' =>"))
        self.assertIn("get_objects_in_term", activator)
        self.assertIn("wp_set_object_terms", activator)
        seed = re.search(r"TERMS_SEED_VERSION\s*=\s*(\d+)", activator)
        self.assertIsNotNone(seed)
        self.assertGreaterEqual(int(seed.group(1)), 4)

    def test_validated_internal_path_destination_never_accepts_a_posted_url(self) -> None:
        php = compact(source(FOCUS))
        self.assertIn("'seoflix_focus_destination'", php)
        self.assertIn("'path' === $destination ? $term : null", php)
        self.assertIn("get_term_link( $destination )", php)
        self.assertNotRegex(php, re.compile(r"\$_POST\[['\"](?:url|redirect|redirect_to|return)"))

    def test_handlers_cover_authenticated_and_anonymous_set_and_reset(self) -> None:
        php = source(FOCUS)
        for hook in (
            "admin_post_seoflix_focus_set",
            "admin_post_nopriv_seoflix_focus_set",
            "admin_post_seoflix_focus_reset",
            "admin_post_nopriv_seoflix_focus_reset",
            "pre_get_posts",
            "wp_login",
        ):
            self.assertIn(hook, php)
        self.assertIn("wp_verify_nonce", php)
        self.assertIn("'response' => 403", php)
        self.assertIn("wp_safe_redirect", php)
        self.assertRegex(php, r"wp_safe_redirect\s*\([^;]+303")

    def test_preference_storage_is_private_bounded_and_functional_only(self) -> None:
        php = compact(source(FOCUS))
        self.assertIn("'_seoflix_focus_path'", php)
        self.assertIn("'seoflix_focus_path'", php)
        self.assertIn("update_user_meta", php)
        self.assertIn("delete_user_meta", php)
        self.assertIn("setcookie", php)
        for cookie_flag in ("'secure' => is_ssl()", "'httponly' => true", "'samesite' => 'Lax'"):
            self.assertIn(cookie_flag, php)
        self.assertRegex(php, r"COOKIE_TTL\s*=\s*(?:[1-9][0-9]{4,7})")
        self.assertNotRegex(php.lower(), r"remote_addr|user_agent|analytics|tracking|pixel")

    def test_set_handler_requires_scalar_sanitized_valid_path(self) -> None:
        php = compact(source(FOCUS))
        self.assertIn("is_string( $_POST['seoflix_focus_path'] )", php)
        self.assertIn("wp_unslash", php)
        self.assertIn("sanitize_title", php)
        self.assertIn("get_term_by( 'slug'", php)
        self.assertIn("Taxonomies::PATH", php)
        self.assertIn("'post_type' => CPT::VIDEO", php)
        self.assertIn("'post_status' => 'publish'", php)
        self.assertIn("'posts_per_page' => 1", php)
        self.assertIn("'fields' => 'ids'", php)
        self.assertIn("suppress_filters", php)

    def test_authenticated_meta_is_authoritative_and_cookie_promotion_is_guarded(self) -> None:
        php = compact(source(FOCUS))
        self.assertIn("is_user_logged_in()", php)
        self.assertIn("get_user_meta", php)
        self.assertIn("promote_cookie_on_login", php)
        self.assertRegex(
            php,
            re.compile(r"promote_cookie_on_login.*?get_user_meta.*?if \( '' !== \$stored \).*?return;.*?update_user_meta", re.S),
        )

    def test_redirect_is_derived_from_same_site_referrer_not_submitted_url(self) -> None:
        php = compact(source(FOCUS))
        self.assertIn("wp_get_referer", php)
        self.assertIn("wp_parse_url", php)
        self.assertIn("home_url", php)
        self.assertIn("get_post_type_archive_link", php)
        self.assertNotRegex(php, re.compile(r"\$_(?:POST|GET)\[['\"](?:redirect|redirect_to|url|return)"))

    def test_filter_fails_closed_to_exact_video_queries(self) -> None:
        php = compact(source(FOCUS))
        for guard in (
            "is_admin()",
            "REST_REQUEST",
            "is_feed()",
            "is_singular( CPT::VIDEO )",
            "is_tax( Taxonomies::PATH )",
            "is_main_query()",
            "QUERY_VAR_APPLY",
            "QUERY_VAR_BYPASS",
        ):
            self.assertIn(guard, php)
        self.assertIn("self::is_exact_video_post_type( $query->get( 'post_type' ) )", php)
        self.assertNotRegex(php, r"seoflix_product[^;]{0,100}(?:tax_query|pre_get_posts)")

    def test_existing_tax_query_is_preserved_when_focus_clause_is_added(self) -> None:
        php = compact(source(FOCUS))
        self.assertIn("$query->get( 'tax_query' )", php)
        self.assertIn("$query->set( 'tax_query', $tax_query )", php)
        self.assertIn("$focus_clause = [", php)
        self.assertIn("'relation' => 'AND'", php)
        self.assertIn("$tax_query, $focus_clause", php)
        self.assertIn("'taxonomy' => Taxonomies::PATH", php)
        self.assertIn("'field' => 'slug'", php)
        self.assertIn("'terms' => [ $slug ]", php)

    def test_only_exact_video_post_type_is_accepted(self) -> None:
        source_file = REPO_ROOT / FOCUS
        harness = textwrap.dedent(
            f"""
            <?php
            define('ABSPATH', __DIR__);
            eval('namespace Seoflix; final class CPT {{ public const VIDEO = "seoflix_video"; }}');
            require {str(source_file)!r};
            $cases = [
                ['seoflix_video', true],
                ['post', false],
                ['seoflix_product', false],
                [['seoflix_video'], false],
                [['seoflix_video', 'post'], false],
                ['', false],
                [null, false],
            ];
            foreach ($cases as [$value, $expected]) {{
                $actual = \\Seoflix\\Focus::is_exact_video_post_type($value);
                if ($actual !== $expected) {{ fwrite(STDERR, json_encode([$value, $expected, $actual])); exit(1); }}
            }}
            echo "FOCUS_POST_TYPE_OK\\n";
            """
        )
        with tempfile.NamedTemporaryFile("w", suffix=".php", encoding="utf-8") as handle:
            handle.write(harness)
            handle.flush()
            result = subprocess.run(["php", handle.name], text=True, capture_output=True, check=False)
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertIn("FOCUS_POST_TYPE_OK", result.stdout)

    def test_return_urls_and_focus_surfaces_run_in_php(self) -> None:
        source_file = REPO_ROOT / FOCUS
        harness = textwrap.dedent(
            """
            <?php
            namespace {
                define('ABSPATH', __DIR__);
                $GLOBALS['sx_referrer'] = '';
                $GLOBALS['sx_surface'] = [];
                function get_post_type_archive_link($type) { return 'https://example.test/videos/'; }
                function home_url($path = '/') { return 'https://example.test' . ('/' === $path ? '/' : $path); }
                function wp_get_referer() { return $GLOBALS['sx_referrer']; }
                function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
                function wp_validate_redirect($url, $fallback = '') { return $url ?: $fallback; }
                function is_front_page() { return !empty($GLOBALS['sx_surface']['front']); }
                function is_post_type_archive($type) { return !empty($GLOBALS['sx_surface']['archive']) && 'seoflix_video' === $type; }
                function is_tax($tax) { return !empty($GLOBALS['sx_surface']['tax']); }
                function is_search() { return !empty($GLOBALS['sx_surface']['search']); }
                function get_query_var($key) { return $GLOBALS['sx_surface'][$key] ?? null; }
            }
            namespace Seoflix {
                final class CPT { public const VIDEO = 'seoflix_video'; }
                final class Taxonomies { public const PATH='seoflix_path'; public const TOPIC='seoflix_topic'; public const FORMAT='seoflix_format'; }
            }
            namespace {
                require __SOURCE__;
                $method = new ReflectionMethod('Seoflix\\Focus', 'same_site_return_url');
                $method->setAccessible(true);
                $redirects = [];
                foreach (['/sujet/seo-technique/?page=2', 'https://example.test/recherche/?s=seo', 'https://evil.test/phish'] as $ref) {
                    $GLOBALS['sx_referrer'] = $ref;
                    $redirects[] = $method->invoke(null);
                }
                $surfaceCases = [
                    ['front'=>1], ['archive'=>1], ['tax'=>1],
                    ['search'=>1, 'post_type'=>'seoflix_video'],
                    ['search'=>1, 'post_type'=>'post'],
                ];
                $surfaces = [];
                foreach ($surfaceCases as $case) {
                    $GLOBALS['sx_surface'] = $case;
                    $surfaces[] = \\Seoflix\\Focus::is_focus_surface();
                }
                echo json_encode(['redirects'=>$redirects, 'surfaces'=>$surfaces]);
            }
            """
        ).lstrip().replace("__SOURCE__", repr(str(source_file)))
        result = subprocess.run(["php"], input=harness, text=True, capture_output=True, check=False)
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual(
            {
                "redirects": [
                    "https://example.test/sujet/seo-technique/?page=2",
                    "https://example.test/recherche/?s=seo",
                    "https://example.test/videos/",
                ],
                "surfaces": [True, True, True, True, False],
            },
            __import__("json").loads(result.stdout),
        )

    def test_query_filter_behavior_for_taxonomies_helpers_and_or_groups(self) -> None:
        source_file = REPO_ROOT / FOCUS
        harness = textwrap.dedent(
            f"""
            <?php
            namespace {{
                define('ABSPATH', __DIR__);
                class WP_Term {{ public string $slug = 'apprendre-l-affiliation'; public string $name = 'Affiliation'; public string $taxonomy = 'seoflix_path'; public int $term_id = 7; }}
                class WP_Query {{
                    public array $vars; public bool $main; public string $taxonomy;
                    public function __construct(array $vars, bool $main, string $taxonomy = '') {{ $this->vars=$vars; $this->main=$main; $this->taxonomy=$taxonomy; }}
                    public function get($key) {{ return $this->vars[$key] ?? null; }}
                    public function set($key, $value): void {{ $this->vars[$key] = $value; }}
                    public function is_main_query(): bool {{ return $this->main; }}
                    public function is_feed(): bool {{ return false; }}
                    public function is_singular($type = ''): bool {{ return false; }}
                    public function is_post_type_archive($type): bool {{ return ($this->vars['post_type_archive'] ?? '') === $type; }}
                    public function is_tax($tax = ''): bool {{ return is_array($tax) ? in_array($this->taxonomy, $tax, true) : $this->taxonomy === $tax; }}
                }}
                function is_admin(): bool {{ return false; }}
                function is_user_logged_in(): bool {{ return false; }}
                function sanitize_title($value): string {{ return strtolower((string) $value); }}
                function wp_unslash($value) {{ return $value; }}
                function get_term_by($field, $slug, $taxonomy) {{ return new WP_Term(); }}
                function get_term_meta($term_id, $key, $single = false) {{ return 'seoflix_path_public_order' === $key ? 1 : ''; }}
                function get_posts($args): array {{ return [101]; }}
                function is_wp_error($value): bool {{ return false; }}
            }}
            namespace Seoflix {{
                final class CPT {{ public const VIDEO = 'seoflix_video'; }}
                final class Taxonomies {{ public const PATH='seoflix_path'; public const TOPIC='seoflix_topic'; public const FORMAT='seoflix_format'; }}
            }}
            namespace {{
                require {str(source_file)!r};
                $_COOKIE['seoflix_focus_path'] = 'apprendre-l-affiliation';
                $existing = ['relation' => 'OR', ['taxonomy' => 'seoflix_topic', 'terms' => ['seo']], ['taxonomy' => 'seoflix_format', 'terms' => ['tuto']]];
                $topic = new WP_Query(['tax_query' => $existing], true, 'seoflix_topic');
                $helper = new WP_Query(['post_type' => 'seoflix_video', 'seoflix_focus_apply' => 1, 'tax_query' => $existing], false);
                $unmarked = new WP_Query(['post_type' => 'seoflix_video'], false);
                $path = new WP_Query(['post_type' => 'seoflix_video'], true, 'seoflix_path');
                \\Seoflix\\Focus::filter_video_query($topic);
                \\Seoflix\\Focus::filter_video_query($helper);
                \\Seoflix\\Focus::filter_video_query($unmarked);
                \\Seoflix\\Focus::filter_video_query($path);
                $ok = static function($query): bool {{
                    $tax = $query->vars['tax_query'] ?? null;
                    return is_array($tax) && ($tax['relation'] ?? '') === 'AND'
                        && ($tax[0]['relation'] ?? '') === 'OR'
                        && ($tax[1]['taxonomy'] ?? '') === 'seoflix_path';
                }};
                echo json_encode([
                    'topic_grouped' => $ok($topic),
                    'helper_grouped' => $ok($helper),
                    'unmarked_unchanged' => ! isset($unmarked->vars['tax_query']),
                    'path_unchanged' => ! isset($path->vars['tax_query']),
                    'topic_tax' => $topic->vars['tax_query'] ?? null,
                    'helper_tax' => $helper->vars['tax_query'] ?? null,
                ]);
            }}
            """
        ).lstrip()
        result = subprocess.run(["php"], input=harness, text=True, capture_output=True, check=False)
        self.assertEqual(0, result.returncode, result.stderr)
        payload = __import__("json").loads(result.stdout)
        topic_tax = payload.pop("topic_tax")
        helper_tax = payload.pop("helper_tax")
        self.assertEqual(
            {"topic_grouped": True, "helper_grouped": True, "unmarked_unchanged": True, "path_unchanged": True},
            payload,
            {"topic_tax": topic_tax, "helper_tax": helper_tax},
        )

    def test_header_menu_has_real_path_chooser_status_reset_and_empty_state(self) -> None:
        functions = source(FUNCTIONS)
        header = source(HEADER)
        for token in (
            "function seoflix_render_focus_banner()",
            "Focus::available_paths()",
            "Focus::active_path()",
			'<details class="sx-focus-menu">',
			"<select",
			"disabled",
            'name="action" value="seoflix_focus_set"',
            'name="action" value="seoflix_focus_reset"',
            'name="seoflix_focus_path"',
            "wp_nonce_field",
            "FOCUS :",
            "Voir toutes les vidéos",
            "Aucune vidéo dans ce FOCUS",
            "get_term_link",
        ):
            self.assertIn(token, functions)
        self.assertNotIn("<script", functions)
        self.assertNotRegex(functions, r"\son(?:click|change|submit)=")
        banner = header.index("seoflix_render_focus_banner();")
        self.assertLess(banner, header.index("</header>"))
        self.assertNotIn("seoflix_render_focus_banner();", header[header.index("</header>"):])

    def test_focus_styles_are_enqueued_accessible_and_mobile_safe(self) -> None:
        functions = source(FUNCTIONS)
        css = source(CSS)
        self.assertIn("assets/css/focus.css", functions)
        for token in (
            ".sx-focus-menu",
            ".sx-focus-menu__form",
			".sx-focus-menu__panel",
			"position: absolute",
            ":focus-visible",
            "min-height: 44px",
            "min-width: 0",
            "max-width: 100%",
            "overflow-wrap: anywhere",
            "@media (max-width: 480px)",
        ):
            self.assertIn(token, css)
        self.assertNotIn("animation:", css)
        self.assertNotIn("transition:", css)

    def test_fixture_root_detects_a_fail_open_post_type_mutation(self) -> None:
        original = source(FOCUS)
        needle = "return CPT::VIDEO === $post_type;"
        mutant = "return in_array( CPT::VIDEO, (array) $post_type, true );"
        self.assertEqual(1, original.count(needle), "mutation target must remain narrow")
        with tempfile.TemporaryDirectory() as tmp:
            fixture = Path(tmp)
            target = fixture / FOCUS
            target.parent.mkdir(parents=True)
            target.write_text(original.replace(needle, mutant), encoding="utf-8")
            env = os.environ.copy()
            env["SEOFLIX_FIXTURE_ROOT"] = str(fixture)
            result = subprocess.run(
                [sys.executable, __file__, "MadiasFocusContracts.test_only_exact_video_post_type_is_accepted"],
                env=env,
                text=True,
                capture_output=True,
                check=False,
            )
        self.assertNotEqual(0, result.returncode)
        self.assertIn("FAIL", result.stderr + result.stdout)
        self.assertEqual(original, source(FOCUS), "mutation must never touch the worktree")


if __name__ == "__main__":
    unittest.main()
