from __future__ import annotations

import re
import subprocess
import tempfile
import textwrap
import unittest
from pathlib import Path

from php_source import REPO_ROOT, source


VIDEO_COMMENTS = "plugin/seoflix-core/includes/class-video-comments.php"
COMMENTS_TEMPLATE = "theme/seoflix/comments-video.php"


def compact(text: str) -> str:
    return re.sub(r"\s+", " ", text).strip()


class MadiasVideoDiscussionContracts(unittest.TestCase):
    def test_video_cpt_alone_supports_native_comments(self) -> None:
        cpt = source("plugin/seoflix-core/includes/class-cpt.php")
        video = cpt[cpt.index("function register_video"):cpt.index("function register_channel")]
        channel = cpt[cpt.index("function register_channel"):cpt.index("function register_product")]
        product = cpt[cpt.index("function register_product") :]
        self.assertRegex(video, re.compile(r"'supports'\s*=>\s*\[[^\]]*'comments'", re.S))
        self.assertNotIn("'comments'", channel)
        self.assertNotIn("'comments'", product)

    def test_boot_loads_and_initializes_video_comments(self) -> None:
        plugin = source("plugin/seoflix-core/includes/class-plugin.php")
        self.assertIn("includes/class-video-comments.php", plugin)
        self.assertIn("Video_Comments::init();", plugin)

    def test_discussion_flag_is_disabled_by_default_and_depends_on_accounts(self) -> None:
        flags = compact(source("plugin/seoflix-core/includes/class-feature-flags.php"))
        self.assertRegex(
            flags,
            re.compile(
                r"function video_discussions_enabled\(\): bool \{ return self::user_accounts_enabled\(\) && \(bool\) get_option\( 'seoflix_video_discussions_enabled', false \); \}"
            ),
        )
        settings = source("plugin/seoflix-core/admin/class-admin-settings.php")
        self.assertIn("seoflix_video_discussions_enabled", settings)
        self.assertRegex(settings, re.compile(r"seoflix_video_discussions_enabled'.*?'default'\s*=>\s*false", re.S))
        for warning in ("comptes", "RGPD", "QA runtime"):
            self.assertIn(warning, settings)

    def test_custom_type_hooks_and_authenticated_admin_post_only(self) -> None:
        php = source(VIDEO_COMMENTS)
        self.assertIn("public const COMMENT_TYPE = 'seoflix_video_discussion';", php)
        self.assertIn("admin_post_seoflix_video_comment", php)
        self.assertNotIn("admin_post_nopriv_seoflix_video_comment", php)
        for hook in (
            "preprocess_comment",
            "wp_insert_comment_data",
            "rest_pre_insert_comment",
            "rest_pre_dispatch",
            "rest_comment_query",
            "comments_clauses",
        ):
            self.assertIn(hook, php)

    def test_handler_reconstructs_identity_and_checks_nonce_capability_video_and_open_state(self) -> None:
        php = compact(source(VIDEO_COMMENTS))
        for token in (
            "wp_verify_nonce",
            "seoflix_video_comment_",
            "wp_get_current_user",
            "current_user_can( 'read' )",
            "FeatureFlags::video_discussions_enabled()",
            "$post->post_type !== CPT::VIDEO",
            "$post->post_status !== 'publish'",
            "comments_open( $post_id )",
            "'comment_author' => $user->display_name",
            "'comment_author_email' => $user->user_email",
            "'user_id' => (int) $user->ID",
            "'comment_author_IP' => ''",
            "'comment_agent' => ''",
        ):
            self.assertIn(token, php)
        self.assertNotRegex(php, re.compile(r"\$_POST\[['\"](?:author|email|url|user_id|comment_author)"))

    def test_plain_text_validator_is_unicode_bounded_and_rejects_active_content(self) -> None:
        php = source(VIDEO_COMMENTS)
        self.assertRegex(php, re.compile(r"MIN_LENGTH\s*=\s*3"))
        self.assertRegex(php, re.compile(r"MAX_LENGTH\s*=\s*1500"))
        for token in (
            "wp_unslash",
            "preg_match( '//u'",
            "mb_strlen",
            "html_entity_decode",
            "wp_strip_all_tags",
            "<!--",
            "shortcode",
            "iframe",
            "script",
            "sanitize_textarea_field",
        ):
            self.assertIn(token, php)
        self.assertNotIn("make_clickable", php)
        self.assertNotIn("do_shortcode", php)
        self.assertNotRegex(php, re.compile(r"wp_kses(?:_post)?\s*\("))

    def test_validator_rejects_direct_and_obfuscated_link_signals(self) -> None:
        php = source(VIDEO_COMMENTS).lower()
        for token in (
            "http",
            "https",
            "ftp",
            "mailto",
            "data",
            "javascript",
            "file",
            "www",
            "xn--",
            "hxxp",
            "[.]",
            "(dot)",
            "zero-width",
            "unicode-dot",
            "@",
            "\\p{l}",
        ):
            self.assertIn(token, php)

    def test_handler_rejects_files_applies_rate_gate_and_uses_native_pipeline(self) -> None:
        php = compact(source(VIDEO_COMMENTS))
        self.assertIn("! empty( $_FILES )", php)
        self.assertRegex(php, re.compile(r"RATE_SECONDS\s*=\s*(?:3[0-9]|4[0-9]|5[0-9]|60)"))
        self.assertIn("get_transient", php)
        self.assertIn("wp_new_comment( $comment_data, true )", php)
        insertion = php.index("wp_new_comment( $comment_data, true )")
        marker = php.index("set_transient", insertion)
        self.assertGreater(marker, insertion)
        self.assertIn("wp_get_comment_status", php)
        self.assertNotIn("'comment_approved' => 1", php)

    def test_reply_parent_is_approved_same_video_dedicated_and_root(self) -> None:
        php = compact(source(VIDEO_COMMENTS))
        for token in (
            "get_comment( $parent_id )",
            "$parent->comment_post_ID !== $post_id",
            "$parent->comment_type !== self::COMMENT_TYPE",
            "$parent->comment_approved !== '1'",
            "(int) $parent->comment_parent !== 0",
        ):
            self.assertIn(token, php)

    def test_direct_and_rest_bypasses_fail_closed_without_affecting_other_posts(self) -> None:
        php = compact(source(VIDEO_COMMENTS))
        self.assertIn("self::$inside_validated_handler", php)
        self.assertIn("get_post_type( $post_id ) !== CPT::VIDEO", php)
        self.assertIn("WP_REST_Request", php)
        self.assertIn("DELETE", php)
        self.assertIn("rest_forbidden", php)
        self.assertIn("comment_type", php)
        self.assertIn(
            "$targets_private_discussion = self::rest_request_targets_video_discussion( $request )",
            php,
        )
        self.assertRegex(
            php,
            re.compile(
                r"'GET'\s*===\s*\$method.*?\$targets_private_discussion.*?! self::can_view_discussions\(\).*?rest_forbidden"
            ),
        )

    def test_private_read_guard_precedes_theme_query(self) -> None:
        template = source(COMMENTS_TEMPLATE)
        flag = template.index("FeatureFlags::video_discussions_enabled()")
        logged = template.index("is_user_logged_in()", flag)
        query = template.index("get_comments(")
        self.assertLess(flag, logged)
        self.assertLess(logged, query)
        pre_query = template[:query]
        self.assertIn("return;", pre_query)
        plugin = source(VIDEO_COMMENTS)
        self.assertIn("comment_type <> %s", plugin)
        self.assertIn("FeatureFlags::video_discussions_enabled()", plugin)
        self.assertIn("is_user_logged_in()", plugin)
        self.assertRegex(
            compact(plugin),
            re.compile(r"is_admin\(\).*?current_user_can\( 'moderate_comments' \).*?return \$clauses"),
        )

    def test_theme_places_discussion_between_capsule_and_suggestions(self) -> None:
        single = source("theme/seoflix/single-seoflix_video.php")
        capsule = single.index("sx-madias-capsule")
        discussion = single.index("comments-video", capsule)
        suggestions = single.index("// Suggestions", discussion)
        self.assertLess(capsule, discussion)
        self.assertLess(discussion, suggestions)
        self.assertIn("FeatureFlags::video_discussions_enabled()", single)
        self.assertNotIn(r"\\Seoflix", single)

    def test_template_has_plain_accessible_forms_and_one_rendered_reply_level(self) -> None:
        template = source(COMMENTS_TEMPLATE)
        for token in (
            'id="discussion-video"',
            'aria-labelledby="discussion-video-title"',
            'role="status"',
            'role="alert"',
            'name="action" value="seoflix_video_comment"',
            'name="parent_id"',
            "comment_parent",
            "nl2br( esc_html(",
            'minlength="3"',
            'maxlength="1500"',
            "wp_create_nonce( 'seoflix_video_comment_' . $video_id )",
        ):
            self.assertIn(token, template)
        self.assertNotIn("enctype=", template)
        self.assertNotIn('type="file"', template)
        self.assertNotIn("wp_editor", template)
        self.assertNotIn("wp_nonce_field(", template)
        self.assertNotIn("onclick=", template)
        self.assertNotIn("<script", template)

    def test_prg_uses_fixed_codes_fragment_and_never_user_content(self) -> None:
        php = compact(source(VIDEO_COMMENTS))
        self.assertIn("#discussion-video", php)
        self.assertIn("discussion_status", php)
        self.assertIn("wp_safe_redirect", php)
        self.assertIn("303", php)
        self.assertNotRegex(php, re.compile(r"add_query_arg\([^;]*\$(?:content|message|raw)", re.S))
        template = source(COMMENTS_TEMPLATE)
        for code in ("submitted", "pending", "invalid", "nonce", "closed", "rate", "duplicate"):
            self.assertIn(f"'{code}'", template)

    def test_privacy_eraser_and_tombstone_are_registered_and_bounded(self) -> None:
        php = compact(source(VIDEO_COMMENTS))
        for token in (
            "wp_privacy_personal_data_erasers",
            "personal_data_eraser",
            "ERASER_BATCH = 100",
            "comment_author_email",
            "wp_delete_comment",
            "wp_update_comment",
            "Utilisateur supprimé",
            "Message supprimé à la demande de son auteur.",
            "'comment_author_email' => ''",
            "'comment_author_url' => ''",
            "'comment_author_IP' => ''",
            "'comment_agent' => ''",
            "'user_id' => 0",
            "get_comment_meta",
            "delete_comment_meta",
        ):
            self.assertIn(token, php)
        self.assertIn("'number' => self::ERASER_BATCH", php)
        self.assertIn("get_comment( (int) $comment->comment_ID )", php)
        self.assertIn("self::TOMBSTONE_BODY === $updated->comment_content", php)
        erase_path = php[php.index("function erase_comment") : php.index("function purge_comment_metadata")]
        purge = erase_path.index("self::purge_comment_metadata( (int) $comment->comment_ID )")
        update = erase_path.index("wp_update_comment(")
        self.assertLess(purge, update)
        self.assertIn("if ( ! self::purge_comment_metadata( (int) $comment->comment_ID ) )", erase_path)
        self.assertIn("return false;", erase_path[purge:update])
        eraser_path = php[php.index("function personal_data_eraser") : php.index("function erase_comment")]
        self.assertIn("$failed = false;", eraser_path)
        self.assertIn("$failed = true;", eraser_path)
        self.assertIn("'items_retained' => $retained || $failed", eraser_path)
        self.assertIn("'done' => ! $failed && count( $comments ) < self::ERASER_BATCH", eraser_path)
        eraser_registration = php[
            php.index("function register_privacy_eraser") : php.index("function personal_data_eraser")
        ]
        self.assertLess(eraser_registration.index("return ["), eraser_registration.index("'seoflix-video-discussions'"))
        self.assertLess(eraser_registration.index("'seoflix-video-discussions'"), eraser_registration.index("] + $erasers;"))

    def test_custom_admin_rgpd_deletion_uses_discussion_erasure_helper(self) -> None:
        admin = source("plugin/seoflix-core/admin/class-admin-rgpd.php")
        self.assertIn("Video_Comments", admin)
        self.assertIn("Video_Comments::COMMENT_TYPE", admin)
        self.assertIn("Video_Comments::erase_comment", admin)

    def test_rgpd_document_covers_discussion_lifecycle_and_runtime_gate(self) -> None:
        doc = source("docs/RGPD_PROCEDURE.md").lower()
        for token in (
            "seoflix_video_discussions_enabled",
            "wp_comments",
            "wp_commentmeta",
            "exporteur natif",
            "tombstone",
            "modération",
            "suppression du compte",
            "sauvegardes",
            "cache",
            "qa runtime",
            "avant activation",
        ):
            self.assertIn(token, doc)

    def test_discussion_css_is_responsive_accessible_and_overflow_safe(self) -> None:
        css = source("theme/seoflix/assets/css/pages.css")
        for token in (
            ".sx-video-discussion",
            ".sx-video-discussion__reply",
            ".sx-video-discussion__form",
            "min-height: 44px",
            ":focus-visible",
            "overflow-wrap: anywhere",
            "min-width: 0",
            "max-width: 100%",
            "@media (max-width: 480px)",
        ):
            self.assertIn(token, css)

    def test_plain_text_validator_behavior_with_php_stubs(self) -> None:
        source_file = REPO_ROOT / VIDEO_COMMENTS
        harness = textwrap.dedent(
            f"""
            <?php
            define('ABSPATH', __DIR__);
            class WP_Error {{ public string $code; public function __construct($code) {{ $this->code = $code; }} public function get_error_code() {{ return $this->code; }} }}
            function sanitize_textarea_field($value) {{
                $value = preg_replace('/%[a-f0-9]{{2}}/i', '', $value);
                return trim(str_replace("\\0", '', $value));
            }}
            require {str(source_file)!r};
            $cases = [
                ['Bon point SEO', null],
                ['ab', 'content_short'],
                ['%41%42', 'content_short'],
                [str_repeat('é', 1501), 'content_long'],
                ['Voir example.com', 'content_link'],
                ['Voir example[.]com', 'content_link'],
                ['Voir example [dot] com', 'content_unsafe'],
                ['Voir example (dot) com', 'content_link'],
                ['Voir example . com', 'content_link'],
                ['Voir hxxp://example com', 'content_link'],
                ['mail nom@example com', 'content_link'],
                ['&lt;script&gt;x&lt;/script&gt;', 'content_unsafe'],
                ['[gallery id="1"]', 'content_unsafe'],
                ["Bonjour\\u{{200B}}example。com", 'content_link'],
            ];
            foreach ($cases as [$input, $expected]) {{
                $result = \\Seoflix\\Video_Comments::validate_plain_text($input);
                $actual = $result instanceof WP_Error ? $result->get_error_code() : null;
                if ($actual !== $expected) {{ fwrite(STDERR, json_encode([$input, $expected, $actual])); exit(1); }}
            }}
            echo "VALIDATOR_OK\\n";
            """
        )
        with tempfile.NamedTemporaryFile("w", suffix=".php", encoding="utf-8") as handle:
            handle.write(harness)
            handle.flush()
            result = subprocess.run(["php", handle.name], text=True, capture_output=True, check=False)
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertIn("VALIDATOR_OK", result.stdout)


if __name__ == "__main__":
    unittest.main()
