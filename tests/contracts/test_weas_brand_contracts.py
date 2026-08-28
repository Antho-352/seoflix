from __future__ import annotations

import hashlib
import re
import struct
import unittest
from pathlib import Path

from php_source import REPO_ROOT, source


RUNTIME_BRAND_FILES = (
    "plugin/seoflix-core/seoflix-core.php",
    "plugin/seoflix-core/admin/class-admin-homepage.php",
    "plugin/seoflix-core/admin/class-admin-rgpd.php",
    "plugin/seoflix-core/includes/class-frontend.php",
    "plugin/seoflix-core/includes/class-user-accounts.php",
    "plugin/seoflix-core/includes/class-business-finder.php",
    "plugin/seoflix-core/includes/class-video-comments.php",
    "plugin/seoflix-core/includes/class-video-meta.php",
    "plugin/seoflix-core/includes/class-homepage.php",
    "plugin/seoflix-core/includes/class-seo.php",
    "theme/seoflix/header.php",
    "theme/seoflix/footer.php",
    "theme/seoflix/front-page.php",
    "theme/seoflix/page-paths-index.php",
    "theme/seoflix/page-business-finder.php",
    "theme/seoflix/single-seoflix_video.php",
    "theme/seoflix/functions.php",
    "theme/seoflix/style.css",
)

ACTIVE_PUBLIC_DOCS = (
    "README.md",
    "docs/DEPLOY.md",
    "docs/IMPORT_FORMAT.md",
    "docs/LEGAL_PAGES.md",
    "docs/RGPD_PROCEDURE.md",
    "docs/SECURITY.md",
)


class WeasBrandContracts(unittest.TestCase):
    def test_wordpress_package_metadata_uses_weas_and_reserved_domain(self) -> None:
        plugin = source("plugin/seoflix-core/seoflix-core.php")
        theme = source("theme/seoflix/style.css")
        self.assertRegex(plugin, r"Plugin Name:\s+WEAS Core\b")
        self.assertRegex(plugin, r"Plugin URI:\s+https://weas\.fr\b")
        self.assertRegex(plugin, r"Description:\s+.*\bWEAS\b")
        self.assertRegex(plugin, r"Version:\s+0\.27\.4\b")
        self.assertRegex(theme, r"Theme Name:\s+WEAS\b")
        self.assertRegex(theme, r"Theme URI:\s+https://weas\.fr\b")
        self.assertRegex(theme, r"Description:\s+.*\bWEAS\b")
        self.assertRegex(theme, r"Version:\s+0\.14\.7\b")

    def test_every_runtime_brand_surface_has_dropped_visible_madias_copy(self) -> None:
        combined = "\n".join(source(path) for path in RUNTIME_BRAND_FILES)
        self.assertNotRegex(combined, r"\bMADIAS\b")
        self.assertNotIn("madias.fr", combined.lower())
        self.assertGreaterEqual(len(re.findall(r"\bWEAS\b", combined)), 20)

    def test_all_runtime_text_files_reject_old_public_brand_patterns(self) -> None:
        roots = (REPO_ROOT / "plugin/seoflix-core", REPO_ROOT / "theme/seoflix")
        suffixes = {".php", ".css", ".js", ".json", ".svg", ".txt", ".md"}
        patterns = (
            re.compile(r"MADIAS|MADIGAS"),
            re.compile(r"placeholder=[\"'](?:seoflix|madias|madigas)[\"']", re.I),
            re.compile(r"contact@(?:seoflix|madias|madigas)\.fr", re.I),
            re.compile(r"https?://(?:www\.)?(?:seoflix|madias|madigas)\.fr", re.I),
            re.compile(r"\b(?:sur|par|de|newsletter|formulaire privé|base)\s+Seoflix\b"),
            re.compile(r"Seoflix/1\.0"),
            re.compile(r">\s*Seoflix\s*<"),
        )
        for root in roots:
            for path in sorted(p for p in root.rglob("*") if p.is_file() and p.suffix.lower() in suffixes):
                text = path.read_text(encoding="utf-8", errors="replace")
                relative = path.relative_to(REPO_ROOT).as_posix()
                for pattern in patterns:
                    self.assertIsNone(pattern.search(text), f"{pattern.pattern} in {relative}")

    def test_active_public_documentation_uses_weas(self) -> None:
        visible_patterns = (
            re.compile(r"^# [^#].*\bSeoflix\b", re.M),
            re.compile(r"\b(?:admin|WP Admin).*Seoflix\s*→", re.I),
            re.compile(r"\b(?:sur|par|de|base|site name)\s+Seoflix\b", re.I),
            re.compile(r"\bSeoflix\s+(?:utilise|collecte|peut|est|reste|traite)\b", re.I),
        )
        for relative in ACTIVE_PUBLIC_DOCS:
            text = (REPO_ROOT / relative).read_text(encoding="utf-8")
            self.assertNotIn("MADIAS", text, relative)
            self.assertNotIn("MADIGAS", text, relative)
            self.assertNotIn("seoflix.fr", text, relative)
            self.assertNotIn("contact@seoflix.fr", text, relative)
            for pattern in visible_patterns:
                self.assertIsNone(pattern.search(text), f"{pattern.pattern} in {relative}")
            self.assertIn("WEAS", text, relative)

    def test_visible_theme_copy_and_capsule_are_weas_source_first(self) -> None:
        header = source("theme/seoflix/header.php")
        footer = source("theme/seoflix/footer.php")
        homepage = source("theme/seoflix/front-page.php")
        video = source("theme/seoflix/single-seoflix_video.php")
        self.assertGreaterEqual(header.count("WEAS"), 1)
        self.assertNotIn('class="sx-logo__text">WEAS', header)
        self.assertGreaterEqual(footer.count("WEAS"), 3)
        self.assertIn("Pourquoi WEAS", homepage)
        self.assertIn("L’essentiel par WEAS", video)
        self.assertLess(video.index('data-sx-player="source"'), video.index('data-sx-player="madias"'))

    def test_historical_technical_identifiers_are_preserved(self) -> None:
        plugin = source("plugin/seoflix-core/seoflix-core.php")
        theme = source("theme/seoflix/style.css")
        self.assertIn("SEOFLIX_VERSION", plugin)
        self.assertIn("Text Domain:       seoflix-core", plugin)
        self.assertIn("Text Domain: seoflix", theme)
        self.assertTrue((REPO_ROOT / "plugin/seoflix-core").is_dir())
        self.assertTrue((REPO_ROOT / "theme/seoflix").is_dir())

    def test_brand_assets_use_weas_and_social_png_has_expected_dimensions(self) -> None:
        asset_dir = REPO_ROOT / "theme/seoflix/assets/images"
        logo = (asset_dir / "logo-full.svg").read_text(encoding="utf-8")
        mark = (asset_dir / "logo-mark.svg").read_text(encoding="utf-8")
        og = (asset_dir / "og-default.svg").read_text(encoding="utf-8")
        combined = logo + mark + og
        self.assertNotIn("Seoflix", combined)
        self.assertNotIn("seoflix", combined)
        self.assertGreaterEqual(combined.count("WEAS"), 5)
        self.assertIn("WEAS.FR", og)
        self.assertIn("Le business web, sans perdre des heures", og)

        png = (asset_dir / "og-default.png").read_bytes()
        self.assertTrue(png.startswith(b"\x89PNG\r\n\x1a\n"))
        width, height = struct.unpack(">II", png[16:24])
        self.assertEqual((1200, 630), (width, height))

    def test_supplied_wordmark_and_favicon_are_integrated_exactly(self) -> None:
        asset_dir = REPO_ROOT / "theme/seoflix/assets/images"
        header = source("theme/seoflix/header.php")
        layout = source("theme/seoflix/assets/css/layout.css")
        favicon = (asset_dir / "favicon.ico").read_bytes()
        logo = (asset_dir / "logo-weas.png").read_bytes()

        self.assertEqual(
            "e4a711d5300f92360d1e19a54570a1e7ed822117661facd81dd074ff91a37064",
            hashlib.sha256(favicon).hexdigest(),
        )
        self.assertTrue(logo.startswith(b"\x89PNG\r\n\x1a\n"))
        self.assertEqual((942, 168), struct.unpack(">II", logo[16:24]))
        self.assertEqual(2, header.count("assets/images/logo-weas.png"))
        self.assertEqual(2, header.count('class="sx-logo__image"'))
        self.assertIn("assets/images/favicon.ico", header)
        self.assertNotIn("<svg class=\"sx-logo__mark\"", header)
        self.assertIn(".sx-logo__image", layout)
        self.assertIn("width: 80px", layout)
        self.assertIn("height: auto", layout)

    def test_weas_cutover_document_is_fail_closed(self) -> None:
        path = REPO_ROOT / "docs/WEAS_DOMAIN_MIGRATION.md"
        self.assertTrue(path.is_file())
        document = path.read_text(encoding="utf-8")
        for required in (
            "weas.fr",
            "seoflix.fr",
            "sauvegarde",
            "rollback",
            "DNS",
            "TLS",
            "301",
            "Search Console",
            "MySQL",
            "aucune redirection",
        ):
            with self.subTest(required=required):
                self.assertIn(required, document)
        self.assertRegex(document, re.compile(r"DNS.*TLS.*QA publique.*301", re.S))

    def test_cutover_migrates_persistent_branding_and_legal_pages_deterministically(self) -> None:
        text = (REPO_ROOT / "docs/WEAS_DOMAIN_MIGRATION.md").read_text(encoding="utf-8")
        required = (
            "wp option update blogname 'WEAS'",
            "wp option update seoflix_seo_org_name 'WEAS'",
            "wp option update seoflix_seo_twitter ''",
            "wp option update seoflix_seo_og_image 'https://weas.fr/wp-content/themes/seoflix/assets/images/og-default.png'",
            "mentions-legales",
            "confidentialite",
            "affiliation",
            "contact",
            "wp_update_post",
            "Seoflix",
            "WEAS",
            "contact@seoflix.fr",
            "contact@weas.fr",
        )
        for needle in required:
            self.assertIn(needle, text)


if __name__ == "__main__":
    unittest.main()
