from __future__ import annotations

import re
import unittest
from pathlib import Path

from php_source import REPO_ROOT, source


TEMPLATE_PATH = "theme/seoflix/single-seoflix_video.php"
FUNCTIONS_PATH = "theme/seoflix/functions.php"
CSS_PATH = "theme/seoflix/assets/css/pages.css"
VIDEO_META_PATH = "plugin/seoflix-core/includes/class-video-meta.php"


def compact(text: str) -> str:
    return re.sub(r"\s+", " ", text).strip()


def function_body(text: str, name: str) -> str:
    """Extract a PHP function body while ignoring quoted/commented braces."""
    signature = re.search(rf"function\s+{re.escape(name)}\s*\(", text)
    if not signature:
        raise AssertionError(f"Missing function {name}()")
    opening = text.find("{", signature.end())
    if opening < 0:
        raise AssertionError(f"Missing body for {name}()")

    depth = 0
    quote: str | None = None
    line_comment = False
    block_comment = False
    escaped = False
    index = opening
    while index < len(text):
        char = text[index]
        following = text[index + 1] if index + 1 < len(text) else ""
        if line_comment:
            if char == "\n":
                line_comment = False
        elif block_comment:
            if char == "*" and following == "/":
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
        elif char == "/" and following == "/":
            line_comment = True
            index += 1
        elif char == "/" and following == "*":
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
    raise AssertionError(f"Unbalanced body for {name}()")


def css_rule(css: str, selector: str) -> str:
    match = re.search(rf"{re.escape(selector)}\s*\{{(?P<body>[^}}]*)\}}", css, re.S)
    return compact(match.group("body")) if match else ""


class MadiasVideoPageContracts(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        cls.template = source(TEMPLATE_PATH)
        cls.functions = source(FUNCTIONS_PATH)
        cls.css = source(CSS_PATH)

    def test_scoped_files_exist(self) -> None:
        for relative_path in (TEMPLATE_PATH, FUNCTIONS_PATH, CSS_PATH):
            with self.subTest(path=relative_path):
                self.assertTrue(Path(REPO_ROOT, relative_path).is_file())

    def test_source_player_is_named_labeled_and_precedes_madias_capsule(self) -> None:
        source_marker = 'data-sx-player="source"'
        madias_marker = 'data-sx-player="madias"'
        self.assertIn(source_marker, self.template)
        self.assertIn(madias_marker, self.template)
        self.assertLess(self.template.index(source_marker), self.template.index(madias_marker))
        source_region = self.template[
            self.template.index(source_marker) : self.template.index(madias_marker)
        ]
        self.assertIn('id="sx-source-player"', source_region)
        self.assertIn('name="sx-source-player"', source_region)
        self.assertRegex(source_region, re.compile(r"title=\"<\?php\s+echo\s+esc_attr\("))
        self.assertIn("Vidéo source", source_region)
        self.assertIn("www.youtube-nocookie.com", function_body(self.functions, "seoflix_source_embed_url"))

    def test_timestamps_use_exact_meta_key_duration_and_shared_normalizer(self) -> None:
        body = compact(function_body(self.functions, "seoflix_video_timestamps"))
        self.assertIn("\\Seoflix\\Meta_Keys::VIDEO_TIMESTAMPS", body)
        self.assertIn("\\Seoflix\\Meta_Keys::VIDEO_DURATION", body)
        self.assertIn("json_decode", body)
        self.assertIn("is_array", body)
        self.assertIn("\\Seoflix\\Video_Meta::sanitize_timestamps", body)
        self.assertRegex(
            body,
            re.compile(r"sanitize_timestamps\(\s*\$decoded\s*,\s*\$duration\s*\)"),
        )
        self.assertRegex(body, re.compile(r"return\s+\[\]\s*;"))

    def test_timestamp_links_seek_only_the_source_youtube_player(self) -> None:
        self.assertIn('class="sx-video-passages"', self.template)
        self.assertIn('class="sx-video-page__description"', self.template)
        passages_start = self.template.index('class="sx-video-passages"')
        passages_end = self.template.index('class="sx-video-page__description"')
        passages = compact(self.template[passages_start:passages_end])
        self.assertRegex(
            self.template,
            re.compile(
                r"if\s*\(\s*\$timestamps\s*&&\s*\$source_embed_url\s*&&\s*!\s*\$show_locked\s*\)\s*:"
            ),
        )
        self.assertTrue(
            "seoflix_source_embed_url( $yid, $seconds, true )" in passages,
            "Timestamp links must be built from the source YouTube ID",
        )
        self.assertIn('target="sx-source-player"', passages)
        self.assertIn("$seconds = (int) $passage['seconds']", passages)
        self.assertNotIn("$editorial", passages)
        self.assertNotIn("VIDEO_EDITORIAL_URL", passages)
        self.assertIn("esc_url( $seek_url )", passages)
        embed_helper = compact(function_body(self.functions, "seoflix_source_embed_url"))
        self.assertIn("$seconds > 0 || $autoplay", embed_helper)
        self.assertRegex(embed_helper, re.compile(r"\$args\['start'\]\s*=\s*\$seconds"))
        for token in ("esc_html( $time_label )", "esc_html( $passage['label'] )", "esc_html( $passage['takeaway'] )"):
            with self.subTest(token=token):
                self.assertIn(token, passages)

    def test_key_concepts_accept_legacy_and_structured_rows_without_blank_chrome(self) -> None:
        body = compact(function_body(self.functions, "seoflix_video_key_concepts"))
        self.assertIn("\\Seoflix\\Meta_Keys::VIDEO_KEY_CONCEPTS", body)
        self.assertIn("json_decode", body)
        self.assertIn("is_array", body)
        self.assertIn("\\Seoflix\\Video_Meta::sanitize_key_concepts", body)
        normalizer = compact(function_body(source(VIDEO_META_PATH), "sanitize_key_concepts"))
        self.assertIn("is_string( $point )", normalizer)
        self.assertIn("is_array( $point )", normalizer)
        self.assertIn("$point['text']", normalizer)
        concepts_guard = re.search(
            r"<\?php\s+if\s*\(\s*\$key_concepts\s*\)\s*:\s*\?>.*?Points à retenir.*?<\?php\s+endif\s*;\s*\?>",
            self.template,
            re.S,
        )
        self.assertIsNotNone(concepts_guard)
        self.assertIn("esc_html( $concept['text'] )", concepts_guard.group(0))

    def test_complete_editorial_order_is_source_passages_summary_concepts_then_madias(self) -> None:
        markers = (
            'data-sx-player="source"',
            'class="sx-video-passages"',
            "the_content();",
            'class="sx-video-concepts"',
            'data-sx-player="madias"',
        )
        positions = [self.template.index(marker) for marker in markers]
        self.assertEqual(positions, sorted(positions))
        self.assertEqual(len(positions), len(set(positions)))

    def test_summary_remains_the_content_before_optional_madias_block(self) -> None:
        self.assertIn('data-sx-player="madias"', self.template)
        summary = self.template.index("the_content();")
        madias = self.template.index('data-sx-player="madias"')
        self.assertLess(summary, madias)
        self.assertEqual(self.template.count("the_content();"), 1)
        self.assertIn("Ce que couvre cette vidéo", self.template)

    def test_editorial_capsule_uses_exact_meta_key_strict_normalizer_and_guard(self) -> None:
        body = compact(function_body(self.functions, "seoflix_video_editorial_embed_url"))
        self.assertIn("\\Seoflix\\Meta_Keys::VIDEO_EDITORIAL_URL", body)
        self.assertIn("\\Seoflix\\Video_Meta::normalize_editorial_youtube_url", body)
        self.assertRegex(body, re.compile(r"youtube-nocookie(?:\\\\)?\.com/embed/"))
        self.assertRegex(body, re.compile(r"return\s+''\s*;"))
        block = re.search(
            r"<\?php\s+if\s*\(\s*\$editorial_url\s*\)\s*:\s*\?>.*?data-sx-player=\"madias\".*?<\?php\s+endif\s*;\s*\?>",
            self.template,
            re.S,
        )
        self.assertIsNotNone(block)
        self.assertIn("L’essentiel par WEAS", block.group(0))
        self.assertIn("esc_url( $editorial_url )", block.group(0))
        self.assertIn("esc_attr(", block.group(0))
        self.assertRegex(
            self.template,
            re.compile(r"if\s*\(\s*!\s*\$show_locked\s*&&\s*\$source_embed_url\s*\)\s*:")
        )
        self.assertNotIn("vimeo", compact(self.template + self.functions).lower())

    def test_output_is_escaped_and_contains_no_unsafe_client_side_shortcuts(self) -> None:
        self.assertIn('data-sx-player="source"', self.template)
        editorial_layer = self.template[self.template.index('data-sx-player="source"') :]
        for forbidden in ("data-json=", "onclick=", "onload=", "alert(", "<script"):
            with self.subTest(forbidden=forbidden):
                self.assertNotIn(forbidden, editorial_layer.lower())
        self.assertNotRegex(editorial_layer, re.compile(r"<\?php\s+echo\s+\$(?:seek_url|time_label|editorial_url)"))

    def test_editorial_css_is_touch_accessible_responsive_and_motion_safe(self) -> None:
        for selector in (
            ".sx-video-source",
            ".sx-video-source__label",
            ".sx-video-passages",
            ".sx-video-passages__link",
            ".sx-video-concepts",
            ".sx-madias-capsule",
            ".sx-madias-capsule__player",
        ):
            with self.subTest(selector=selector):
                self.assertTrue(selector in self.css, f"Missing CSS selector {selector}")
        link_rule = css_rule(self.css, ".sx-video-passages__link")
        self.assertRegex(link_rule, r"min-height\s*:\s*44px")
        self.assertRegex(self.css, r"\.sx-video-passages__link:focus-visible")
        self.assertRegex(self.css, r"@media\s*\(\s*max-width\s*:\s*480px\s*\)")
        self.assertRegex(self.css, r"grid-template-columns\s*:\s*minmax\(\s*0\s*,\s*1fr\s*\)")
        self.assertRegex(self.css, r"overflow-wrap\s*:\s*anywhere")
        self.assertRegex(self.css, r"@media\s*\(\s*prefers-reduced-motion\s*:\s*reduce\s*\)")


if __name__ == "__main__":
    unittest.main()
