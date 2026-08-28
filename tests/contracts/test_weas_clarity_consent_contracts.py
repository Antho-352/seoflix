from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[2]
PLUGIN = ROOT / "plugin/seoflix-core"
MAIN = PLUGIN / "seoflix-core.php"
LOADER = PLUGIN / "includes/class-plugin.php"
CONSENT = PLUGIN / "includes/class-analytics-consent.php"
SECURITY = PLUGIN / "includes/class-security.php"
SETTINGS = PLUGIN / "admin/class-admin-settings.php"
LEGAL = PLUGIN / "admin/class-legal-pages.php"


class WeasClarityConsentContracts(unittest.TestCase):
    def test_plugin_version_and_module_registration(self):
        main = MAIN.read_text()
        loader = LOADER.read_text()
        self.assertIn("Version:           0.27.5", main)
        self.assertIn("define( 'SEOFLIX_VERSION', '0.27.5' )", main)
        self.assertTrue(CONSENT.is_file())
        self.assertIn("class-analytics-consent.php", loader)
        self.assertIn("Analytics_Consent::init();", loader)

    def test_clarity_is_fail_closed_and_loaded_only_after_grant(self):
        php = CONSENT.read_text()
        self.assertIn("seoflix_clarity_project_id", php)
        self.assertRegex(php, re.compile(r"get_option\(\s*self::OPTION_PROJECT_ID,\s*''\s*\)"))
        self.assertIn("weas_analytics_consent_v1", php)
        self.assertIn("choice !== 'granted'", php)
        self.assertIn("https://www.clarity.ms/tag/", php)
        self.assertIn("createElement('script')", php)
        self.assertLess(php.index("choice !== 'granted'"), php.index("createElement('script')"))
        self.assertIn("script.async = true", php)
        self.assertIn("script.dataset.weasClarity", php)
        self.assertIn("consentv2", php)
        self.assertIn("analytics_Storage: value", php)
        self.assertIn("consentV2('granted')", php)
        self.assertIn("ad_Storage: 'denied'", php)
        self.assertNotRegex(php, re.compile(r"<script[^>]+src=[^>]*clarity", re.I))

    def test_refusal_persists_and_withdrawal_stops_future_loading(self):
        php = CONSENT.read_text()
        self.assertIn("localStorage.setItem(STORAGE_KEY, choice)", php)
        self.assertIn("consentV2('denied')", php)
        self.assertIn("expireCookie('_clck')", php)
        self.assertIn("expireCookie('_clsk')", php)
        self.assertIn("location.reload()", php)
        self.assertIn("Accepter les statistiques", php)
        self.assertIn("Refuser", php)
        self.assertIn("Gérer mes cookies", php)
        self.assertIn("aria-labelledby", php)
        self.assertIn("focus()", php)

    def test_admin_setting_csp_and_privacy_disclosure(self):
        settings = SETTINGS.read_text()
        security = SECURITY.read_text()
        legal = LEGAL.read_text()
        consent = CONSENT.read_text()
        self.assertIn("GROUP_ANALYTICS", settings)
        self.assertIn("Analytics_Consent::OPTION_PROJECT_ID", settings)
        self.assertIn("sanitize_project_id", consent)
        self.assertIn("https://*.clarity.ms", security)
        self.assertIn("https://c.bing.com", security)
        self.assertIn("Microsoft Clarity", legal)
        self.assertIn("Microsoft Clarity", consent)
        self.assertIn("cartes de chaleur", consent)
        self.assertIn("cookies de première et de tierce parties", consent)
        self.assertIn("uniquement après ton consentement", consent)
        self.assertIn("Politique de confidentialité Microsoft", consent)

    def test_project_identifier_is_not_hardcoded_in_versioned_sources(self):
        php = CONSENT.read_text()
        # A Clarity project identifier must come from the WordPress option.
        self.assertNotRegex(php, re.compile(r"clarity\.ms/tag/[a-z0-9]+", re.I))


if __name__ == "__main__":
    unittest.main()
