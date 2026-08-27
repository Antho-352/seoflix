from __future__ import annotations

import re
import unittest

from php_source import source


class WeasHomeToolsRevisionContracts(unittest.TestCase):
    def test_home_hero_copy_is_two_explicit_lines_without_legacy_dashes_or_brand(self) -> None:
        front = source("theme/seoflix/front-page.php")
        homepage = source("plugin/seoflix-core/includes/class-homepage.php")
        self.assertNotIn('sx-home-hero__brand', front)
        self.assertIn('class="sx-home-hero__line sx-home-hero__line--business"', front)
        self.assertIn('class="sx-home-hero__line sx-home-hero__line--promise"', front)
        self.assertIn('<span>Apprends </span>', front)
        self.assertIn('sans perdre des heures sur YouTube.', front)
        rendered_title = front[front.index('id="madias-home-title"'):front.index('</h1>', front.index('id="madias-home-title"'))]
        self.assertNotIn('business web', rendered_title)
        self.assertNotIn('—', rendered_title)
        self.assertIn("Apprends l’affiliation sans perdre des heures sur YouTube.", homepage)
        self.assertIn("Apprends l’affiliation sans perdre des heures sur YouTube.", front)
        self.assertIn("Apprends le business web sans perdre des heures sur YouTube.", homepage)
        self.assertIn("LEGACY_HERO_TITLE", homepage)

    def test_home_hero_geometry_reduces_top_bottom_space_and_caps_desktop_type(self) -> None:
        css = source("theme/seoflix/style.css")
        hero = re.search(r"\.sx-home-hero\s*\{([^}]*)\}", css, re.S)
        inner = re.search(r"\.sx-home-hero__inner\s*\{([^}]*)\}", css, re.S)
        title = re.search(r"\.sx-home-hero__title\s*\{([^}]*)\}", css, re.S)
        self.assertIsNotNone(hero)
        self.assertIsNotNone(inner)
        self.assertIsNotNone(title)
        self.assertNotIn("76vh", hero.group(1))
        self.assertNotIn("48rem", hero.group(1))
        self.assertRegex(inner.group(1), r"padding-block:\s*clamp\([^;]*3\.5rem[^;]*\)\s+clamp\([^;]*2\.5rem")
        self.assertRegex(title.group(1), r"font-size:\s*clamp\([^;]*4\.5rem\)")
        fluid_size = re.search(r"font-size:\s*clamp\([^,]+,\s*([0-9.]+)vw,\s*4\.5rem\)", title.group(1))
        self.assertIsNotNone(fluid_size)
        self.assertLessEqual(float(fluid_size.group(1)), 4.6)
        self.assertIn("white-space: nowrap", css)
        start_60 = css.find("@media (max-width: 60rem)")
        end_60 = css.find("@media (max-width: 48rem)")
        self.assertGreaterEqual(start_60, 0)
        self.assertGreater(end_60, start_60)
        media_60 = css[start_60:end_60]
        self.assertIn("white-space: normal", media_60)

    def test_paths_heading_and_exact_new_promise_are_present_without_kicker(self) -> None:
        front = source("theme/seoflix/front-page.php")
        self.assertNotIn("Choisis ton cap", front)
        self.assertNotIn("Six parcours pour apprendre dans le bon ordre", front)
        self.assertIn("Six business en ligne à apprendre gratuitement", front)
        expected = (
            "Choisis le business en ligne qui te correspond gratuitement",
            "Apprends l’édition de sites sans y laisser 500€.",
            "Crée ta compétence et développe ton business à partir d'une sélection complète des meilleures vidéos.",
            "Affiliation, YouTube, vente de liens, IA &amp; automatisation, vente de leads, freelancing.",
            "Suis le parcours qui te permettra de générer tes premiers euros en ligne.",
            "Des ressources vidéos sélectionnées et organisées pour se former à son rythme et gratuitement.",
        )
        for copy in expected:
            self.assertIn(copy, front)

    def test_header_removes_wordmark_adds_arsenal_and_focus_moves_to_authenticated_dashboard(self) -> None:
        header = source("theme/seoflix/header.php")
        dashboard = source("theme/seoflix/page-mon-parcours.php")
        functions = source("theme/seoflix/functions.php")
        self.assertNotIn('class="sx-logo__text">WEAS', header)
        self.assertNotIn("seoflix_render_focus_banner();", header)
        self.assertGreaterEqual(header.count("L’ARSENAL"), 2)
        self.assertGreaterEqual(header.count("/outils/"), 2)
        self.assertIn("seoflix_render_focus_banner();", dashboard)
        self.assertIn("is_user_logged_in()", functions[functions.index("function seoflix_render_focus_banner"):])
        self.assertIn("sx-dashboard-focus", dashboard)
        self.assertEqual(2, header.count('class="sx-logo"'))
        self.assertEqual(2, header.count('aria-label="WEAS — accueil"'))

    def test_every_rotating_business_label_is_grammatical(self) -> None:
        homepage = source("plugin/seoflix-core/includes/class-homepage.php")
        labels = re.findall(r"'hero_label'\s*=>\s*'([^']+)'", homepage)
        self.assertEqual(6, len(labels))
        self.assertIn("le business YouTube", labels)
        self.assertNotIn("YouTube", labels)

    def test_desktop_menu_gap_is_1_5rem_without_mutating_global_space_token(self) -> None:
        layout = source("theme/seoflix/assets/css/layout.css")
        tokens = source("theme/seoflix/assets/css/tokens.css")
        nav = re.search(r"\.sx-nav\s*\{([^}]*)\}", layout, re.S)
        self.assertIsNotNone(nav)
        self.assertIn("gap: 1.5rem", nav.group(1))
        self.assertIn("--sx-space-4: 1rem", tokens)
        self.assertNotRegex(
            layout,
            re.compile(r"@media\s*\(max-width:\s*(?:1380px|1180px)\).*?\.sx-nav\s*\{[^}]*\bgap\s*:", re.S),
        )
        self.assertGreaterEqual(layout.count("@media (max-width: 1100px)"), 2)
        self.assertNotIn("@media (max-width: 960px)", layout)
        start_1300 = layout.find("@media (max-width: 1300px)")
        end_1300 = layout.find("@media (max-width: 1180px)")
        self.assertGreaterEqual(start_1300, 0)
        self.assertGreater(end_1300, start_1300)
        media_1300 = layout[start_1300:end_1300]
        self.assertIn(".sx-search-form--desktop { display: none; }", media_1300)

    def test_tools_catalog_is_single_column_row_layout_with_promotion_not_clicks_or_date(self) -> None:
        archive = source("theme/seoflix/archive-seoflix_product.php")
        functions = source("theme/seoflix/functions.php")
        css = source("theme/seoflix/assets/css/components.css")
        self.assertIn("sx-tools-catalog", archive)
        catalog_css = css[css.index(".sx-tools-catalog"):css.index("/* Variante compacte")]
        self.assertRegex(catalog_css, r"\.sx-tools-catalog\s*\{[^}]*grid-template-columns:\s*minmax\(0,\s*1fr\)")
        self.assertIn("sx-card-product__promotion", functions)
        self.assertIn("PRODUCT_PROMO_CODE", functions)
        self.assertIn("PRODUCT_PROMO_OFFER", functions)
        self.assertNotIn("sx-card-product__action", functions[functions.index("function seoflix_render_product_card"):functions.index("function seoflix_render_video_row")])
        for forbidden in ("clics", "hier", "aujourd’hui", "date"):
            self.assertNotIn(forbidden, archive.lower())
            self.assertNotIn(forbidden, catalog_css.lower())

    def test_promotion_fields_are_admin_editable_sanitized_and_optional(self) -> None:
        keys = source("plugin/seoflix-core/includes/class-meta-keys.php")
        affiliate = source("plugin/seoflix-core/includes/class-affiliate.php")
        importer = source("plugin/seoflix-core/includes/class-importer.php")
        self.assertIn("PRODUCT_PROMO_CODE", keys)
        self.assertIn("PRODUCT_PROMO_OFFER", keys)
        for field in ("seoflix_promo_code", "seoflix_promo_offer"):
            self.assertIn(f'name="{field}"', affiliate)
            self.assertRegex(affiliate, rf"'{field}'\s*=>\s*Meta_Keys::PRODUCT_PROMO_")
        self.assertIn("$_POST[ $field ]", affiliate)
        self.assertIn("sanitize_text_field", affiliate)
        self.assertIn("delete_post_meta", affiliate)
        self.assertIn("promo_code", importer)
        self.assertIn("promo_offer", importer)
        self.assertRegex(importer, re.compile(r"promo_code.*?40", re.S))
        self.assertRegex(importer, re.compile(r"promo_offer.*?80", re.S))

    def test_public_robots_filter_is_fail_closed_outside_admin(self) -> None:
        seo = source("plugin/seoflix-core/includes/class-seo.php")
        admin = source("plugin/seoflix-core/admin/class-admin-seo-tools.php")
        self.assertIn("add_filter( 'robots_txt',", seo)
        self.assertIn("[ self::class, 'filter_robots_txt' ]", seo)
        start = seo.index("public static function filter_robots_txt")
        end = seo.index("public static function filter_wp_robots", start)
        robots_filter = seo[start:end]
        self.assertIn("get_option( 'blog_public', '1' ) === '0'", robots_filter)
        self.assertIn('return "User-agent: *\\nDisallow: /\\n";', robots_filter)
        self.assertIn("get_option( 'seoflix_robots_txt', '' )", robots_filter)
        self.assertNotIn("add_filter( 'robots_txt',", admin)
        current_robots = seo[seo.index("private static function current_seo_robots"):start]
        self.assertIn("Custom_Auth::QV_AUTH_ACTION", current_robots)

    def test_public_contact_and_newsletter_routes_do_not_depend_on_accounts_flag(self) -> None:
        auth = source("plugin/seoflix-core/includes/class-custom-auth.php")
        for method in ("register_public_rewrite", "register_account_rewrites", "register_public_query_vars", "register_account_query_vars"):
            self.assertIn(f"function {method}", auth)
        init = auth[auth.index("public static function init"):auth.index("public static function register_public_rewrite")]
        route = auth[auth.index("public static function route_frontend_action"):auth.index("public static function frontend_action_url")]
        gate = init.index("if ( ! FeatureFlags::user_accounts_enabled() )")
        self.assertLess(init.index("add_action( 'parse_request'"), init.index("FeatureFlags::user_accounts_enabled()"))
        self.assertLess(init.index("add_action( 'init'"), init.index("FeatureFlags::user_accounts_enabled()"))
        self.assertIn("register_public_rewrite", init[:gate])
        self.assertIn("register_public_query_vars", init[:gate])
        self.assertIn("register_account_rewrites", init[gate:])
        self.assertIn("register_account_query_vars", init[gate:])
        self.assertIn("'contact'    => [ Contact::class, 'handle_submit' ]", route)
        self.assertIn("'newsletter' => [ Newsletter::class, 'handle_subscribe' ]", route)
        self.assertIn("if ( FeatureFlags::user_accounts_enabled() )", route)

        public_rewrite = auth[auth.index("public static function register_public_rewrite"):auth.index("public static function register_account_rewrites")]
        account_rewrites = auth[auth.index("public static function register_account_rewrites"):auth.index("public static function register_public_query_vars")]
        public_vars = auth[auth.index("public static function register_public_query_vars"):auth.index("public static function register_account_query_vars")]
        account_vars = auth[auth.index("public static function register_account_query_vars"):auth.index("public static function route_frontend_action")]
        self.assertIn("QV_AUTH_ACTION", public_rewrite + public_vars)
        self.assertNotIn("QV_ACTIVATE", public_rewrite + public_vars)
        self.assertNotIn("QV_SETPWD", public_rewrite + public_vars)
        self.assertIn("QV_ACTIVATE", account_rewrites + account_vars)
        self.assertIn("QV_SETPWD", account_rewrites + account_vars)

    def test_frontend_action_route_rejects_non_post_and_unknown_actions(self) -> None:
        auth = source("plugin/seoflix-core/includes/class-custom-auth.php")
        route = auth[auth.index("public static function route_frontend_action"):auth.index("public static function frontend_action_url")]
        self.assertIn("$_SERVER['REQUEST_METHOD'] !== 'POST'", route)
        self.assertIn("status_header( 405 )", route)
        self.assertIn("header( 'Allow: POST' )", route)
        self.assertIn("status_header( 404 )", route)
        self.assertGreaterEqual(route.count("exit;"), 3)

    def test_disabled_account_page_routes_explicitly_return_404(self) -> None:
        auth = source("plugin/seoflix-core/includes/class-custom-auth.php")
        route = auth[auth.index("public static function route_frontend_action"):auth.index("public static function frontend_action_url")]
        self.assertIn("! FeatureFlags::user_accounts_enabled()", route)
        self.assertIn("$wp->request", route)
        self.assertIn("activer|definir-mot-de-passe", route)
        self.assertIn("status_header( 404 )", route)
        self.assertIn("nocache_headers()", route)

    def test_focus_dashboard_trigger_is_touch_safe(self) -> None:
        focus_css = source("theme/seoflix/assets/css/focus.css")
        summary = re.search(r"\.sx-focus-menu__trigger\s*\{([^}]*)\}", focus_css, re.S)
        self.assertIsNotNone(summary)
        self.assertRegex(summary.group(1), r"min-height\s*:\s*44px")


if __name__ == "__main__":
    unittest.main()
