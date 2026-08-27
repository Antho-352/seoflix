import re
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


def source(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


class WeasUxCorrectionsContracts(unittest.TestCase):
    def test_path_taxonomy_is_normalized_non_destructively_to_six_terms(self) -> None:
        homepage = source("plugin/seoflix-core/includes/class-homepage.php")
        activator = source("plugin/seoflix-core/includes/class-activator.php")
        definitions = homepage[homepage.index("function path_definitions"):homepage.index("function defaults")]
        self.assertEqual(6, definitions.count("'slug' =>"))
        for token in (
            "'focus_label' => \"Apprendre l'affiliation\"",
            "'focus_label' => 'Apprendre la vente de liens'",
            "'focus_label' => 'Apprendre la vente de leads'",
            "seoflix_path_public_order",
            "seoflix_focus_enabled",
            "seoflix_focus_label",
            "get_objects_in_term",
            "wp_set_object_terms",
            "wp_delete_term",
            "Meta_Keys::VIDEO_PATH_ORDERS",
            "Path_Order::sanitize_order_map",
        ):
            self.assertIn(token, activator + homepage)
        for legacy in (
            "apprendre-le-seo",
            "apprendre-le-netlinking",
            "apprendre-le-business",
            "apprendre-lia-et-lautomatisation",
        ):
            self.assertIn(legacy, activator)
        version = re.search(r"TERMS_SEED_VERSION\s*=\s*(\d+)", activator)
        self.assertIsNotNone(version)
        self.assertGreaterEqual(int(version.group(1)), 4)

    def test_path_term_metadata_writes_fail_closed_with_readback(self) -> None:
        activator = source("plugin/seoflix-core/includes/class-activator.php")
        self.assertIn("function set_term_meta_verified", activator)
        self.assertIn("function delete_term_meta_verified", activator)
        self.assertIn("function set_post_meta_verified", activator)
        self.assertIn("function set_seed_version_verified", activator)
        self.assertIn("get_term_meta( $term_id, $meta_key, true )", activator)
        self.assertIn("metadata_exists( 'term', $term_id, $meta_key )", activator)
        self.assertIn("get_post_meta( $post_id, $meta_key, true )", activator)
        self.assertIn("get_option( 'seoflix_terms_seed_version'", activator)
        normalization = activator[activator.index("function normalize_path_terms"):activator.index("function seed_default_options")]
        self.assertIn("self::set_term_meta_verified", normalization)
        self.assertIn("self::delete_term_meta_verified", normalization)
        self.assertIn("self::set_post_meta_verified", normalization)
        self.assertNotRegex(normalization, r"(?<!verified\()update_term_meta\(")
        self.assertNotRegex(normalization, r"(?<!verified\()update_post_meta\(")
        self.assertEqual(2, activator.count("self::set_seed_version_verified();"))

    def test_importer_maps_legacy_paths_and_fails_closed_on_unknown_paths(self) -> None:
        importer = source("plugin/seoflix-core/includes/class-importer.php")
        docs = source("docs/IMPORT_FORMAT.md")
        canonical = (
            "apprendre-l-affiliation",
            "apprendre-youtube",
            "apprendre-la-vente-de-liens",
            "apprendre-ia-automatisation",
            "apprendre-la-vente-de-leads",
            "apprendre-le-freelancing",
        )
        legacy_map = {
            "apprendre-le-seo": "apprendre-l-affiliation",
            "apprendre-le-netlinking": "apprendre-la-vente-de-liens",
            "apprendre-le-business": "apprendre-l-affiliation",
            "apprendre-lia-et-lautomatisation": "apprendre-ia-automatisation",
        }
        self.assertIn("function normalize_import_path_slugs", importer)
        self.assertIn("function resolve_video_term_ids", importer)
        self.assertIn("Taxonomies::PATH === $taxonomy", importer)
        self.assertIn("Parcours d’import inconnu", importer)
        self.assertIn("is_wp_error( $assigned )", importer)
        upsert_video = importer[importer.index("function upsert_video"):importer.index("function should_persist_editorial_rows")]
        self.assertLess(
            upsert_video.index("self::resolve_video_term_ids"),
            upsert_video.index("self::find_video_id_by_youtube_id"),
        )
        self.assertEqual(3, importer.count("$report['ok'] = false;"))
        for legacy, target in legacy_map.items():
            self.assertRegex(
                importer,
                rf"'{re.escape(legacy)}'\s*=>\s*'{re.escape(target)}'",
            )
        path_docs = docs[docs.index("### `seoflix_path`"):docs.index("Les exports historiques")]
        for slug in canonical:
            self.assertIn(f"`{slug}`", path_docs)
        for legacy in legacy_map:
            self.assertNotIn(f"`{legacy}`", path_docs)

    def test_focus_is_a_discrete_dynamic_three_choice_account_menu(self) -> None:
        focus = source("plugin/seoflix-core/includes/class-focus.php")
        functions = source("theme/seoflix/functions.php")
        header = source("theme/seoflix/header.php")
        dashboard = source("theme/seoflix/page-mon-parcours.php")
        css = source("theme/seoflix/assets/css/focus.css")
        self.assertNotIn("PATH_SLUGS", focus)
        self.assertIn("seoflix_focus_enabled", focus)
        self.assertIn("seoflix_path_public_order", focus)
        self.assertIn("get_terms", focus)
        self.assertIn("<details class=\"sx-focus-menu\"", functions)
        self.assertIn("<select", functions)
        self.assertIn('disabled', functions)
        self.assertNotIn("Choisir mon FOCUS vidéo", functions)
        self.assertNotIn("seoflix_render_focus_banner();", header)
        self.assertIn("seoflix_render_focus_banner();", dashboard)
        self.assertIn("is_user_logged_in()", functions[functions.index("function seoflix_render_focus_banner"):])
        self.assertIn(".sx-focus-menu__panel", css)
        self.assertIn("position: absolute", css)
        self.assertNotRegex(css, r"\.sx-focus\s*\{[^}]*width:\s*100%")

    def test_primary_navigation_and_footer_use_the_six_current_paths(self) -> None:
        functions = source("theme/seoflix/functions.php")
        footer = source("theme/seoflix/footer.php")
        homepage = source("plugin/seoflix-core/includes/class-homepage.php")
        self.assertIn("Homepage::public_path_terms()", functions)
        self.assertIn("Homepage::public_path_terms()", footer)
        self.assertIn("function public_path_terms", homepage)
        fallback = functions[functions.index("function seoflix_default_primary_menu"):functions.index("FOCUS — préférence")]
        for old in ("/sujet/seo-technique/", "/sujet/affiliation/", ">SEO<", ">Affiliation<"):
            self.assertNotIn(old, fallback)
        self.assertIn("<h3>Parcours</h3>", footer)
        self.assertIn("if ( $has_subject_widgets || $footer_topics )", footer)
        self.assertIn("'seo-technique'", footer)
        self.assertIn("'netlinking'", footer)

    def test_home_uses_real_description_fallback_and_hides_empty_rows(self) -> None:
        front = source("theme/seoflix/front-page.php")
        css = source("theme/seoflix/style.css")
        self.assertIn("$definition['description']", front)
        self.assertNotIn("Description indisponible.", front)
        self.assertNotIn("Aucune vidéo publiée dans ce parcours.", front)
        self.assertRegex(front, re.compile(r"if \( ! \$featured_videos \).*?continue;", re.S))
        self.assertIn("Explore les six parcours WEAS", front)
        self.assertNotIn("Tu sais déjà ce que tu veux apprendre ?", front)
        content = re.search(r"\.sx-home__content\s*\{([^}]*)\}", css, re.S)
        self.assertIsNotNone(content)
        self.assertIn("gap: var(--sx-space-16)", content.group(1))
        boxes = re.findall(r"\.sx-home-promise,\s*\.sx-home-about,\s*\.sx-home-paths-cta\s*\{([^}]*)\}", css, re.S)
        self.assertTrue(boxes)
        self.assertTrue(any(re.search(r"padding:\s*var\(--sx-space-(?:8|12)\)", block) for block in boxes))

    def test_tools_catalog_is_compact_hierarchical_and_preserves_excerpts(self) -> None:
        archive = source("theme/seoflix/archive-seoflix_product.php")
        functions = source("theme/seoflix/functions.php")
        css = source("theme/seoflix/assets/css/components.css") + source("theme/seoflix/assets/css/pages.css")
        self.assertIn("Tous les outils recommandés", archive)
        self.assertNotIn("Tous les outils SEO recommandés", archive)
        self.assertIn("sx-tools-catalog", archive)
        self.assertIn("'catalog' => true", archive)
        self.assertNotRegex(archive, r"\sstyle=")
        self.assertIn("'catalog'      => false", functions)
        for token in (
            "sx-card-product--catalog",
            "sx-card-product__meta",
            "sx-card-product__category",
            "sx-card-product__pricing",
            "sx-card-product__excerpt",
        ):
            self.assertIn(token, functions + css)
        self.assertIn("wp_trim_words", functions)
        self.assertNotRegex((archive + functions + css).lower(), r"surench|enchère|sponsorisé")
        self.assertRegex(css, r"\.sx-card-product--catalog[^}]*min-height")
        self.assertRegex(css, r"@media\s*\(max-width:\s*\d+px\)")

    def test_header_keeps_the_original_compact_geometry(self) -> None:
        css = source("theme/seoflix/assets/css/layout.css")
        rule = re.search(r"\.sx-site-header__inner\s*\{([^}]*)\}", css, re.S)
        self.assertIsNotNone(rule)
        self.assertRegex(rule.group(1), r"padding-block:\s*6px")
        self.assertRegex(rule.group(1), r"min-height:\s*40px")


if __name__ == "__main__":
    unittest.main()
