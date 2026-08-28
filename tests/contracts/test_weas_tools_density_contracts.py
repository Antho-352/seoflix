from __future__ import annotations

import hashlib
import re
import unittest

from php_source import REPO_ROOT, source


class WeasToolsDensityContracts(unittest.TestCase):
    def setUp(self) -> None:
        functions = source("theme/seoflix/functions.php")
        self.card = functions[
            functions.index("function seoflix_render_product_card") :
            functions.index("function seoflix_render_video_row")
        ]
        css = source("theme/seoflix/assets/css/components.css")
        self.css = css
        self.catalog = css[
            css.index(".sx-tools-catalog") :
            css.index("/* Variante compacte")
        ]

    def test_catalog_uses_logo_meta_fallback_and_right_hand_pricing(self) -> None:
        self.assertIn("$opts['catalog'] && ! $thumb_url", self.card)
        self.assertIn("Meta_Keys::PRODUCT_LOGO_URL", self.card)
        self.assertIn("wp_http_validate_url", self.card)
        self.assertIn('class="sx-card-product__aside"', self.card)
        self.assertRegex(
            self.card,
            re.compile(
                r"sx-card-product__aside.*?sx-card-product__pricing.*?sx-card-product__promotion",
                re.S,
            ),
        )
        self.assertIn("$opts['catalog'] && $pricing_label", self.card)
        self.assertLess(
            self.card.index('class="sx-card-product__body"'),
            self.card.index('class="sx-card-product__aside"'),
        )
        self.assertGreater(self.card.rindex("</a>"), self.card.index('class="sx-card-product__aside"'))

    def test_catalog_rows_are_dense_two_line_rows_with_neutral_separators(self) -> None:
        min_height = re.search(
            r"\.sx-card-product--catalog\s*\{[^}]*min-height:\s*([0-9.]+)rem",
            self.catalog,
            re.S,
        )
        self.assertIsNotNone(min_height)
        self.assertLessEqual(float(min_height.group(1)), 4.0)
        self.assertRegex(
            self.catalog,
            re.compile(
                r"\.sx-card-product--catalog\s*\{[^}]*border-bottom:\s*1px solid var\(--sx-color-border\)",
                re.S,
            ),
        )
        self.assertNotRegex(self.catalog, r"border-color:\s*var\(--sx-color-accent\)")
        self.assertIn("$has_aside", self.card)
        self.assertIn("sx-card-product--has-aside", self.card)
        self.assertRegex(
            self.catalog,
            re.compile(
                r"\.sx-card-product--catalog \.sx-card-product__link\s*\{"
                r"[^}]*grid-template-columns:\s*minmax\(0,\s*1fr\);"
                r"[^}]*padding:\s*\.75rem \.5rem;",
                re.S,
            ),
        )
        self.assertRegex(
            self.catalog,
            re.compile(
                r"\.sx-card-product--catalog\.sx-card-product--has-aside \.sx-card-product__link\s*\{"
                r"[^}]*grid-template-columns:\s*minmax\(0,\s*1fr\) auto;",
                re.S,
            ),
        )
        self.assertRegex(
            self.catalog,
            re.compile(
                r"\.sx-card-product--catalog\.sx-card-product--has-image \.sx-card-product__link\s*\{"
                r"[^}]*grid-template-columns:\s*30px minmax\(0,\s*1fr\);",
                re.S,
            ),
        )
        self.assertRegex(
            self.catalog,
            re.compile(
                r"\.sx-card-product--catalog\.sx-card-product--has-image\.sx-card-product--has-aside \.sx-card-product__link\s*\{"
                r"[^}]*grid-template-columns:\s*30px minmax\(0,\s*1fr\) auto;",
                re.S,
            ),
        )

        self.assertNotIn("grid-column: 2", self.catalog)
        self.assertNotIn("grid-column: 3", self.catalog)

    def test_catalog_logo_type_and_description_have_compact_hierarchy(self) -> None:
        image = re.search(
            r"\.sx-card-product--catalog \.sx-card-product__image\s*\{([^}]*)\}",
            self.catalog,
            re.S,
        )
        self.assertIsNotNone(image)
        self.assertIn("width: 30px", image.group(1))
        self.assertIn("height: 30px", image.group(1))
        self.assertIn("padding: 0", image.group(1))
        self.assertIn("background: transparent", image.group(1))
        self.assertIn("border-radius: 6px", image.group(1))

        name = re.search(
            r"\.sx-card-product--catalog \.sx-card-product__name\s*\{([^}]*)\}",
            self.catalog,
            re.S,
        )
        self.assertIsNotNone(name)
        self.assertIn("font-size: 0.875rem", name.group(1))
        self.assertIn("font-weight: 600", name.group(1))
        self.assertIn("text-overflow: ellipsis", name.group(1))

        excerpt = re.search(
            r"\.sx-card-product--catalog \.sx-card-product__excerpt\s*\{([^}]*)\}",
            self.catalog,
            re.S,
        )
        self.assertIsNotNone(excerpt)
        self.assertIn("white-space: nowrap", excerpt.group(1))
        self.assertIn("text-overflow: ellipsis", excerpt.group(1))
        self.assertIn("overflow: hidden", excerpt.group(1))

    def test_pricing_badge_stays_right_aligned_on_desktop_and_mobile(self) -> None:
        aside = re.search(
            r"\.sx-card-product--catalog \.sx-card-product__aside\s*\{([^}]*)\}",
            self.catalog,
            re.S,
        )
        self.assertIsNotNone(aside)
        self.assertIn("justify-self: end", aside.group(1))
        self.assertIn("align-items: center", aside.group(1))
        self.assertRegex(
            self.catalog,
            re.compile(
                r"@media \(max-width: 640px\).*?\.sx-card-product--catalog\.sx-card-product--has-image "
                r"\.sx-card-product__link\s*\{[^}]*grid-template-columns:\s*26px minmax\(0,\s*1fr\);",
                re.S,
            ),
        )
        self.assertRegex(
            self.catalog,
            re.compile(
                r"@media \(max-width: 640px\).*?\.sx-card-product--catalog\.sx-card-product--has-image\.sx-card-product--has-aside "
                r"\.sx-card-product__link\s*\{[^}]*grid-template-columns:\s*26px minmax\(0,\s*1fr\) auto;",
                re.S,
            ),
        )
        for forbidden in ("surench", "enchère", "sponsorisé", "classement"):
            self.assertNotIn(forbidden, (self.card + self.catalog).lower())

    def test_paid_badge_is_wcag_aa_on_catalog_rest_and_hover(self) -> None:
        def rgb(hex_color: str) -> tuple[float, float, float]:
            return tuple(int(hex_color[index : index + 2], 16) / 255 for index in (1, 3, 5))

        def luminance(color: tuple[float, float, float]) -> float:
            linear = [channel / 12.92 if channel <= 0.04045 else ((channel + 0.055) / 1.055) ** 2.4 for channel in color]
            return 0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2]

        def contrast(foreground: str, background: tuple[float, float, float]) -> float:
            high, low = sorted((luminance(rgb(foreground)), luminance(background)), reverse=True)
            return (high + 0.05) / (low + 0.05)

        accent = rgb("#FF2D3F")
        text = "#FF6673"
        self.assertIn("color: #FF6673", self.css)
        for base in ("#0B0B0F", "#16161D"):
            base_rgb = rgb(base)
            blended = tuple(0.2 * front + 0.8 * back for front, back in zip(accent, base_rgb))
            self.assertGreaterEqual(contrast(text, blended), 4.5)

    def test_mobile_promotion_code_remains_complete_and_wraps(self) -> None:
        self.assertIn("sx-card-product__promotion--code", self.card)
        mobile = self.catalog[self.catalog.index("@media (max-width: 640px)") :]
        self.assertIn("overflow-wrap: anywhere", mobile)
        self.assertIn("overflow: visible", mobile)
        self.assertIn("text-overflow: clip", mobile)
        self.assertNotRegex(mobile, r"sx-card-product__promotion strong\s*\{[^}]*text-overflow:\s*ellipsis")

    def test_linkquiver_logo_and_cuik_pricing_are_seeded_without_overwrite(self) -> None:
        plugin = source("plugin/seoflix-core/seoflix-core.php")
        activator = source("plugin/seoflix-core/includes/class-activator.php")
        self.assertIn("Version:           0.27.4", plugin)
        self.assertIn("define( 'SEOFLIX_VERSION', '0.27.4' )", plugin)
        self.assertIn("TERMS_SEED_VERSION = 4", activator)
        catalog_method = activator[
            activator.index("public static function ensure_product_catalog_seeded") :
        ]
        self.assertNotIn("seed_default_terms", catalog_method)
        self.assertNotIn("set_seed_version_verified", catalog_method)
        self.assertIn("seed_product_catalog_metadata", activator)
        self.assertIn("get_page_by_path( 'linkquiver'", activator)
        self.assertIn("assets/images/linkquiver-icon.svg", activator)
        self.assertIn("wp_parse_url( home_url( '/' ), PHP_URL_SCHEME )", activator)
        self.assertIn("set_url_scheme", activator)
        self.assertIn("Meta_Keys::PRODUCT_LOGO_URL", activator)
        self.assertIn("get_page_by_path( 'cuik'", activator)
        self.assertIn("Meta_Keys::PRODUCT_PRICING", activator)
        self.assertRegex(
            activator,
            re.compile(r"get_post_meta\([^;]+PRODUCT_LOGO_URL[^;]+\)\s*===\s*''", re.S),
        )
        self.assertRegex(
            activator,
            re.compile(r"get_post_meta\([^;]+PRODUCT_PRICING[^;]+\)\s*===\s*''", re.S),
        )

        self.assertIn("PRODUCT_CATALOG_SEED_VERSION = 1", activator)
        self.assertIn("ensure_product_catalog_seeded", activator)
        self.assertIn("seoflix_product_catalog_seed_version", activator)
        self.assertIn("return false;", activator)
        plugin_runtime = source("plugin/seoflix-core/includes/class-plugin.php")
        self.assertIn("ensure_product_catalog_seeded' ], 21", plugin_runtime)
        method = activator[
            activator.index("public static function ensure_product_catalog_seeded") :
        ]
        self.assertLess(method.index("self::seed_product_catalog_metadata()"), method.index("self::set_product_catalog_seed_version_verified()"))
        self.assertRegex(
            method,
            re.compile(r"if\s*\(\s*self::seed_product_catalog_metadata\(\)\s*\)\s*\{\s*self::set_product_catalog_seed_version_verified", re.S),
        )

        icon = REPO_ROOT / "plugin/seoflix-core/assets/images/linkquiver-icon.svg"
        self.assertTrue(icon.is_file())
        self.assertEqual(
            "e5837aae476838530190388b69bdc529ed3e8e2f03eee65a3cb61af2f3d43d45",
            hashlib.sha256(icon.read_bytes()).hexdigest(),
        )

    def test_theme_version_is_bumped_for_catalog_css(self) -> None:
        style = source("theme/seoflix/style.css")
        self.assertIn("Version: 0.14.6", style)


if __name__ == "__main__":
    unittest.main()
