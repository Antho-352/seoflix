from __future__ import annotations

import json
import re
import subprocess
import unittest
from pathlib import Path

from php_source import REPO_ROOT, source


FINDER = "plugin/seoflix-core/includes/class-business-finder.php"
PLUGIN = "plugin/seoflix-core/includes/class-plugin.php"
FRONTEND = "plugin/seoflix-core/includes/class-frontend.php"
TEMPLATE = "theme/seoflix/page-business-finder.php"
FUNCTIONS = "theme/seoflix/functions.php"
CSS = "theme/seoflix/assets/css/business-finder.css"

EXPECTED_ANSWERS = {
    "model": ["asset", "service", "open"],
    "horizon": ["rapid", "months", "no_urgency"],
    "budget": ["zero", "recurring", "invest"],
    "clients": ["willing", "limited", "no"],
    "exposure": ["face", "voice", "discreet"],
    "time": ["low", "medium", "high"],
    "technical": ["low", "tools", "continuous"],
    "potential": ["stable", "high_uncertain"],
}

PROFILES = {
    "affiliation-seo": {
        "model": "asset", "horizon": "months", "budget": "recurring",
        "clients": "no", "exposure": "discreet", "time": "medium",
        "technical": "tools", "potential": "stable",
    },
    "youtube": {
        "model": "asset", "horizon": "no_urgency", "budget": "zero",
        "clients": "no", "exposure": "face", "time": "high",
        "technical": "continuous", "potential": "high_uncertain",
    },
    "vente-de-liens": {
        "model": "asset", "horizon": "rapid", "budget": "invest",
        "clients": "limited", "exposure": "discreet", "time": "medium",
        "technical": "tools", "potential": "stable",
    },
    "ia-automatisation": {
        "model": "service", "horizon": "rapid", "budget": "recurring",
        "clients": "willing", "exposure": "discreet", "time": "high",
        "technical": "continuous", "potential": "high_uncertain",
    },
    "vente-de-leads": {
        "model": "open", "horizon": "months", "budget": "invest",
        "clients": "limited", "exposure": "discreet", "time": "high",
        "technical": "tools", "potential": "high_uncertain",
    },
    "freelancing": {
        "model": "service", "horizon": "rapid", "budget": "zero",
        "clients": "willing", "exposure": "voice", "time": "medium",
        "technical": "low", "potential": "stable",
    },
}


def php_eval(body: str) -> object:
    finder_path = str(Path(REPO_ROOT, FINDER))
    script = "<?php\ndefine('ABSPATH', __DIR__);\nrequire " + json.dumps(finder_path) + ";\n" + body
    completed = subprocess.run(
        ["php"], input=script, text=True, capture_output=True, check=False
    )
    if completed.returncode != 0:
        raise AssertionError(f"PHP harness failed: {completed.stderr}\n{completed.stdout}")
    return json.loads(completed.stdout)


def php_array(value: object) -> str:
    encoded = json.dumps(json.dumps(value, ensure_ascii=False))
    return f"json_decode({encoded}, true)"


class MadiasBusinessFinderContracts(unittest.TestCase):
    def test_scoped_artifacts_exist_and_boot_loads_finder(self) -> None:
        for relative in (FINDER, TEMPLATE, CSS):
            with self.subTest(path=relative):
                self.assertTrue(Path(REPO_ROOT, relative).is_file())
        plugin = source(PLUGIN)
        self.assertIn("includes/class-business-finder.php", plugin)

    def test_rewrite_upgrade_is_versioned_for_zip_replacements(self) -> None:
        frontend = source(FRONTEND)
        activator = source("plugin/seoflix-core/includes/class-activator.php")
        self.assertIn("REWRITE_SCHEMA_VERSION", frontend)
        self.assertIn("maybe_upgrade_rewrites", frontend)
        self.assertIn("seoflix_frontend_rewrite_version", frontend)
        self.assertIn("flush_rewrite_rules( false )", frontend)
        self.assertIn("update_option", frontend)
        self.assertIn("Frontend::REWRITE_SCHEMA_VERSION", activator)

    def test_commencer_is_an_exact_dedicated_fail_closed_route(self) -> None:
        frontend = source(FRONTEND)
        self.assertRegex(
            frontend,
            r"add_rewrite_rule\(\s*'\^commencer/\?\$'\s*,\s*"
            r"'index\.php\?'\s*\.\s*self::QUERY_VAR\s*\.\s*'=business-finder'",
        )
        self.assertIn("page-business-finder.php", frontend)
        self.assertRegex(frontend, r"case\s+'business-finder'\s*:")
        upgrade = frontend[frontend.index("function maybe_upgrade_rewrites") : frontend.index("function add_query_var")]
        self.assertEqual(upgrade.count("flush_rewrite_rules"), 1)
        self.assertIn("REWRITE_SCHEMA_VERSION", upgrade)
        self.assertIn("status_header( 200 )", frontend)
        seo = source("plugin/seoflix-core/includes/class-seo.php")
        self.assertIn("Frontend::is_view( 'business-finder' )", seo)
        self.assertIn("home_url( '/commencer/' )", seo)
        self.assertNotIn("render_canonical", frontend)

    def test_exact_eight_allowlists_and_complete_strict_validation_run_in_php(self) -> None:
        payload = php_eval(
            "$valid = " + php_array(PROFILES["affiliation-seo"]) + ";\n"
            "$incomplete = $valid; unset($incomplete['time']);\n"
            "$array_value = $valid; $array_value['time'] = ['medium'];\n"
            "$unknown = $valid; $unknown['time'] = 'weekends';\n"
            "echo json_encode([\n"
            " 'allowlists' => \\Seoflix\\Business_Finder::ANSWER_ALLOWLISTS,\n"
            " 'valid' => \\Seoflix\\Business_Finder::validate_answers($valid),\n"
            " 'incomplete' => \\Seoflix\\Business_Finder::validate_answers($incomplete),\n"
            " 'array' => \\Seoflix\\Business_Finder::validate_answers($array_value),\n"
            " 'unknown' => \\Seoflix\\Business_Finder::validate_answers($unknown),\n"
            "]);"
        )
        self.assertEqual(payload["allowlists"], EXPECTED_ANSWERS)
        self.assertEqual(payload["valid"], PROFILES["affiliation-seo"])
        self.assertIsNone(payload["incomplete"])
        self.assertIsNone(payload["array"])
        self.assertIsNone(payload["unknown"])

    def test_versioned_table_hard_exclusions_and_stable_tie_order(self) -> None:
        finder = source(FINDER)
        self.assertRegex(finder, r"SCORING_VERSION\s*=\s*'1\.0'\s*;")
        self.assertIn("SCORING_TABLE", finder)
        payload = php_eval(
            "$no_clients = " + php_array(PROFILES["affiliation-seo"]) + ";\n"
            "$low_tech = " + php_array(PROFILES["freelancing"]) + ";\n"
            "echo json_encode([\n"
            " 'no_clients' => \\Seoflix\\Business_Finder::hard_exclusions($no_clients),\n"
            " 'low_tech' => \\Seoflix\\Business_Finder::hard_exclusions($low_tech),\n"
            " 'tie' => \\Seoflix\\Business_Finder::sort_scores([\n"
            "   'freelancing' => 8, 'youtube' => 8, 'affiliation-seo' => 8\n"
            " ]),\n"
            " 'threshold_tie' => \\Seoflix\\Business_Finder::tie_break_details([\n"
            "   'freelancing' => 10, 'youtube' => 8, 'affiliation-seo' => 8\n"
            " ]),\n"
            "]);"
        )
        self.assertIn("freelancing", payload["no_clients"])
        self.assertIn("ia-automatisation", payload["low_tech"])
        self.assertEqual(
            list(payload["tie"].keys()),
            ["affiliation-seo", "youtube", "freelancing"],
        )
        self.assertFalse(payload["threshold_tie"]["primary_tie"])
        self.assertTrue(payload["threshold_tie"]["alternative_tie"])
        self.assertEqual(
            payload["threshold_tie"]["order"],
            ["Affiliation SEO", "Youtube", "Freelancing"],
        )

    def test_six_representative_profiles_produce_deterministic_distinct_results(self) -> None:
        payload = php_eval(
            "$profiles = " + php_array(PROFILES) + ";\n"
            "$eligible = array_column(\\Seoflix\\Business_Finder::PATHS, 'slug');\n"
            "$out = []; foreach ($profiles as $expected => $answers) {\n"
            " $first = \\Seoflix\\Business_Finder::recommend($answers, $eligible);\n"
            " $second = \\Seoflix\\Business_Finder::recommend($answers, $eligible);\n"
            " $out[$expected] = [$first, $second];\n"
            "}\n echo json_encode($out);"
        )
        for expected, pair in payload.items():
            with self.subTest(expected=expected):
                self.assertEqual(pair[0], pair[1], "same answers must be deterministic")
                self.assertEqual(pair[0]["primary"]["id"], expected)
                self.assertNotEqual(
                    pair[0]["primary"]["id"], pair[0]["alternative"]["id"]
                )
                for result in (pair[0]["primary"], pair[0]["alternative"]):
                    self.assertTrue(result["reasons"])
                    self.assertTrue(result["constraints"])

    def test_recommendations_are_limited_to_real_eligible_slugs(self) -> None:
        paths = {
            "affiliation-seo": "apprendre-l-affiliation",
            "youtube": "apprendre-youtube",
            "vente-de-liens": "apprendre-la-vente-de-liens",
            "ia-automatisation": "apprendre-ia-automatisation",
            "vente-de-leads": "apprendre-la-vente-de-leads",
            "freelancing": "apprendre-le-freelancing",
        }
        payload = php_eval(
            "$answers = " + php_array(PROFILES["youtube"]) + ";\n"
            "$one = ['apprendre-youtube'];\n"
            "$two = ['apprendre-l-affiliation', 'apprendre-la-vente-de-liens'];\n"
            "echo json_encode([\n"
            " 'paths' => \\Seoflix\\Business_Finder::PATHS,\n"
            " 'one' => \\Seoflix\\Business_Finder::recommend($answers, $one),\n"
            " 'two' => \\Seoflix\\Business_Finder::recommend($answers, $two),\n"
            "]);"
        )
        self.assertEqual(
            {key: value["slug"] for key, value in payload["paths"].items()}, paths
        )
        homepage = source("plugin/seoflix-core/includes/class-homepage.php")
        activator = source("plugin/seoflix-core/includes/class-activator.php")
        for slug in paths.values():
            self.assertIn(slug, homepage)
        seed = re.search(r"TERMS_SEED_VERSION\s*=\s*(\d+)", activator)
        self.assertIsNotNone(seed)
        self.assertGreaterEqual(int(seed.group(1)), 3)
        self.assertIsNone(payload["one"])
        self.assertEqual(
            {payload["two"]["primary"]["slug"], payload["two"]["alternative"]["slug"]},
            {"apprendre-l-affiliation", "apprendre-la-vente-de-liens"},
        )

    def test_template_guards_nonempty_published_terms_and_has_honest_fallback(self) -> None:
        template = source(TEMPLATE)
        self.assertIn("get_terms", template)
        self.assertRegex(template, r"'taxonomy'\s*=>\s*'seoflix_path'")
        self.assertRegex(template, r"'hide_empty'\s*=>\s*true")
        self.assertRegex(template, r"\(int\)\s*\$term->count\s*>\s*0")
        self.assertIn("get_term_link", template)
        self.assertIn("is_wp_error", template)
        self.assertIn("Moins de deux parcours", template)
        self.assertIn("home_url( '/parcours/' )", template)

    def test_initial_choices_and_no_js_semantic_post_form(self) -> None:
        template = source(TEMPLATE)
        self.assertEqual(len(re.findall(r"<h1\b", template, re.I)), 1)
        self.assertIn("Trouver le business qui me correspond", template)
        self.assertIn("Je sais déjà ce que je veux apprendre", template)
        self.assertIn("home_url( '/parcours/' )", template)
        self.assertRegex(template, r"<form\b[^>]*method=\"post\"")
        self.assertEqual(len(re.findall(r"<fieldset\b", template, re.I)), 8)
        self.assertEqual(len(re.findall(r"<legend\b", template, re.I)), 8)
        self.assertGreaterEqual(len(re.findall(r"type=\"radio\"", template)), 23)
        self.assertEqual(len(re.findall(r"type=\"radio\"[^>]*required", template)), 8)
        self.assertIn("wp_nonce_field", template)
        self.assertIn("wp_verify_nonce", template)
        self.assertIn('role="alert"', template)
        for forbidden in ("<script", "onclick=", "onchange=", "style="):
            self.assertNotIn(forbidden, template.lower())

    def test_results_explain_limits_escape_links_and_offer_focus_conditionally(self) -> None:
        template = source(TEMPLATE)
        self.assertIn("Pourquoi ce parcours", template)
        self.assertIn("Points de vigilance", template)
        self.assertIn("Score d’adéquation", template)
        self.assertIn("$result['score']", template)
        self.assertIn("primary_tie", template)
        self.assertIn("alternative_tie", template)
        self.assertIn("$recommendation['tie_break']['order']", template)
        self.assertIn("Ordre de départage", template)
        self.assertIn("esc_html", template)
        self.assertIn("esc_url", template)
        self.assertIn("class_exists( '\\Seoflix\\Focus' )", template)
        self.assertIn("seoflix_focus_set", template)
        self.assertIn("Focus::NONCE_ACTION", template)
        self.assertIn("Focus::NONCE_FIELD", template)
        self.assertIn('name="seoflix_focus_path"', template)
        self.assertIn('name="seoflix_focus_destination" value="path"', template)
        self.assertNotRegex(template, r"<main\b")
        lowered = (source(FINDER) + template).lower()
        for forbidden in ("garanti", "revenu estimé", "psychométr", "personnalité"):
            self.assertNotIn(forbidden, lowered)

    def test_no_answer_persistence_pii_analytics_or_llm(self) -> None:
        combined = source(FINDER) + source(TEMPLATE)
        for forbidden in (
            "setcookie(", "update_user_meta(", "add_user_meta(", "update_option(",
            "set_transient(", "localstorage", "sessionstorage", "google-analytics",
            "gtag(", "openai", "anthropic", "chatgpt", "prompt(",
        ):
            with self.subTest(forbidden=forbidden):
                self.assertNotIn(forbidden, combined.lower())
        self.assertNotRegex(combined, r"add_query_arg\([^)]*(?:model|horizon|budget|clients)")

    def test_css_is_separate_touch_safe_responsive_and_motion_safe(self) -> None:
        css = source(CSS)
        functions = source(FUNCTIONS)
        self.assertIn("business-finder.css", functions)
        self.assertIn("is_seoflix_view( 'business-finder' )", functions)
        for selector in (
            ".sx-finder-choice", ".sx-finder-option", ".sx-finder-submit",
            ".sx-finder-result--primary", ".sx-finder-alert",
        ):
            self.assertIn(selector, css)
        self.assertRegex(css, r"min-height\s*:\s*44px")
        buttons = css[css.index(".sx-finder-submit,") : css.index(".sx-finder-submit {", css.index(".sx-finder-submit,") + 1)]
        self.assertIn("color: var(--sx-color-bg)", buttons)
        self.assertNotIn("color: var(--sx-color-text)", buttons)
        self.assertRegex(
            css,
            r"\.sx-finder-choice:hover\s*\{[^}]*color:\s*var\(--sx-color-text\)",
        )
        self.assertRegex(
            css,
            r"\.sx-finder-result__link:hover[^\{]*\{[^}]*color:\s*var\(--sx-color-text\)",
        )
        self.assertIn(":focus-visible", css)
        self.assertRegex(css, r"@media\s*\(max-width:\s*\d+px\)")
        self.assertIn("min-width: 0", css)
        self.assertRegex(css, r"@media\s*\(prefers-reduced-motion:\s*reduce\)")
        self.assertNotRegex(css, r"animation(?:-name)?\s*:")
        self.assertNotRegex(css, r"(?<!max-)width\s*:\s*[4-9]\d{2,}px")


if __name__ == "__main__":
    unittest.main()
