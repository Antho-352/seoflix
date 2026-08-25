from __future__ import annotations

import re
import unittest
from pathlib import Path

from php_source import REPO_ROOT, source


SINGLE_PATH = "theme/seoflix/single-post.php"
INDEX_PATH = "theme/seoflix/index.php"
FUNCTIONS_PATH = "theme/seoflix/functions.php"
STYLE_PATH = "theme/seoflix/style.css"
FOOTER_PATH = "theme/seoflix/footer.php"


def optional_source(relative_path: str) -> str:
    path = Path(REPO_ROOT, relative_path)
    return path.read_text(encoding="utf-8") if path.exists() else ""


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


class MadiasBlogContracts(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        cls.single = optional_source(SINGLE_PATH)
        cls.index = source(INDEX_PATH)
        cls.functions = source(FUNCTIONS_PATH)
        cls.style = source(STYLE_PATH)
        cls.footer = source(FOOTER_PATH)

    def test_native_templates_own_exactly_one_h1_and_footer_owns_newsletter(self) -> None:
        self.assertTrue(Path(REPO_ROOT, SINGLE_PATH).is_file(), "single-post.php must exist")
        self.assertEqual(len(re.findall(r"<h1\b", self.single, re.I)), 1)
        self.assertEqual(len(re.findall(r"<h1\b", self.index, re.I)), 1)
        self.assertIn("get_header()", self.single)
        self.assertIn("get_footer()", self.single)
        self.assertNotIn("seoflix_render_newsletter", self.single + self.index)
        self.assertEqual(self.footer.count("seoflix_render_newsletter("), 1)

    def test_single_post_renders_editorial_article_signals(self) -> None:
        required = (
            "the_content()",
            "seoflix_post_reading_time",
            "get_the_date",
            "get_the_category",
            "has_post_thumbnail",
            "get_post_thumbnail_id",
            "_wp_attachment_image_alt",
            "get_the_post_thumbnail",
            "get_previous_post",
            "get_next_post",
            "post__not_in",
            "'post_type'",
            "'post'",
        )
        for token in required:
            with self.subTest(token=token):
                self.assertIn(token, self.single)
        self.assertRegex(self.single, re.compile(r"aria-label=[\"']Fil d[’']Ariane", re.I))
        self.assertRegex(self.single, r"esc_(?:html|url|attr)\s*\(")

    def test_reading_time_uses_visible_unicode_text(self) -> None:
        body = function_body(self.functions, "seoflix_post_reading_time")
        for signal in ("strip_shortcodes", "wp_strip_all_tags", "preg_match_all", r"\p{L}", "ceil"):
            with self.subTest(signal=signal):
                self.assertIn(signal, body)
        self.assertRegex(body, r"max\s*\(\s*1\s*,")
        self.assertIn("function_exists( 'seoflix_post_reading_time' )", self.functions)

    def test_archive_has_contextual_title_cards_pagination_and_empty_state(self) -> None:
        required = (
            "is_home()",
            "is_category()",
            "is_date()",
            "seoflix_render_post_card",
            "seoflix_render_video_card",
            "get_post_type()",
            "the_posts_pagination",
            "sx-blog-empty",
        )
        for token in required:
            with self.subTest(token=token):
                self.assertIn(token, self.index)
        self.assertRegex(self.index, r"esc_html\s*\(\s*\$archive_title\s*\)")

    def test_post_card_signals_image_category_excerpt_date_and_reading_time(self) -> None:
        self.assertIn("function_exists( 'seoflix_render_post_card' )", self.functions)
        body = function_body(self.functions, "seoflix_render_post_card")
        for token in (
            "get_the_post_thumbnail",
            "get_the_category",
            "get_the_excerpt",
            "get_the_date",
            "seoflix_post_reading_time",
            "esc_url",
            "esc_html",
            "esc_attr",
        ):
            with self.subTest(token=token):
                self.assertIn(token, body)

    def test_blog_styles_are_loaded_accessible_and_responsive(self) -> None:
        self.assertIn("get_stylesheet_uri()", self.functions)
        for selector in (
            ".sx-blog-archive",
            ".sx-post-card",
            ".sx-article",
            ".sx-article__content",
            ".sx-article-nav",
            ".sx-related-posts",
        ):
            with self.subTest(selector=selector):
                self.assertIn(selector, self.style)
        self.assertRegex(self.style, r"\.sx-article__content\s*\{[^}]*max-width\s*:\s*(?:7[0-9]{2}px|(?:6[89]|7[0-2])ch)")
        self.assertRegex(self.style, r"min-height\s*:\s*44px")
        self.assertRegex(self.style, r":focus-visible")
        self.assertRegex(self.style, r"@media\s*\(\s*max-width\s*:")
        self.assertRegex(self.style, r"grid-template-columns\s*:\s*minmax\(\s*0\s*,\s*1fr\s*\)")
        self.assertNotRegex(
            self.style,
            re.compile(r"background(?:-color)?\s*:\s*#FF2D3F\s*;[^}]*color\s*:\s*(?:#fff(?:fff)?|white)", re.I | re.S),
        )


if __name__ == "__main__":
    unittest.main()
