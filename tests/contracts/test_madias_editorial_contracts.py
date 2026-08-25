from __future__ import annotations

import re
import unittest

from php_source import source


VIDEO_META = "plugin/seoflix-core/includes/class-video-meta.php"
IMPORTER = "plugin/seoflix-core/includes/class-importer.php"
IMPORT_DOC = "docs/IMPORT_FORMAT.md"


def compact(text: str) -> str:
    """Collapse insignificant whitespace without weakening token mappings."""
    return re.sub(r"\s+", " ", text)


class MadiasEditorialMetadataContracts(unittest.TestCase):
    def test_meta_keys_expose_editorial_video_timestamps_and_path_orders(self) -> None:
        php = source("plugin/seoflix-core/includes/class-meta-keys.php")

        expected = {
            "VIDEO_EDITORIAL_URL": "_seoflix_editorial_video_url",
            "VIDEO_TIMESTAMPS": "_seoflix_timestamps",
            "VIDEO_PATH_ORDERS": "_seoflix_path_orders",
        }
        for constant, value in expected.items():
            with self.subTest(constant=constant):
                declaration = re.compile(
                    rf"public\s+const\s+{re.escape(constant)}\s*=\s*'{re.escape(value)}';"
                )
                self.assertRegex(php, declaration)

    def test_registers_normal_high_madias_editorial_metabox(self) -> None:
        php = compact(source(VIDEO_META))
        self.assertRegex(
            php,
            re.compile(
                r"add_meta_box\(\s*'seoflix_video_editorial'\s*,\s*"
                r"'Contenu éditorial MADIAS'\s*,\s*"
                r"\[\s*self::class\s*,\s*'render_editorial_metabox'\s*\]\s*,\s*"
                r"CPT::VIDEO\s*,\s*'normal'\s*,\s*'high'\s*\)"
            ),
        )

    def test_editorial_metabox_uses_structured_repeaters_and_safe_dom_apis(self) -> None:
        php = source(VIDEO_META)
        for token in (
            "seoflix_editorial_submitted",
            "seoflix_editorial_video_url",
            "seoflix_timestamps_present",
            "seoflix_timestamps[",
            "[seconds]",
            "[label]",
            "[takeaway]",
            "seoflix_key_concepts_present",
            "seoflix_key_concepts[",
            "[text]",
            "document.createElement",
            "textContent",
        ):
            with self.subTest(token=token):
                self.assertIn(token, php)
        self.assertNotIn("innerHTML", php)
        self.assertNotIn("insertAdjacentHTML", php)
        self.assertNotIn("alert(", php)
        self.assertNotIn("seoflix_timestamps_blob", php)

    def test_save_path_rejects_unsafe_wordpress_requests(self) -> None:
        php = compact(source(VIDEO_META))
        required = (
            "wp_verify_nonce",
            "wp_unslash",
            "DOING_AUTOSAVE",
            "wp_is_post_revision",
            "current_user_can( 'edit_post', $post_id )",
            "$post->post_type !== CPT::VIDEO",
        )
        for token in required:
            with self.subTest(token=token):
                self.assertIn(token, php)

    def test_editorial_values_are_only_mutated_when_editorial_form_is_submitted(self) -> None:
        php = compact(source(VIDEO_META))
        self.assertRegex(
            php,
            re.compile(
                r"if \( ! isset\( \$_POST\['seoflix_editorial_submitted'\] \) \) \{ return; \}"
            ),
        )
        self.assertRegex(
            php,
            re.compile(
                r"update_post_meta\( \$post_id, Meta_Keys::VIDEO_EDITORIAL_URL, \$editorial_url \)"
            ),
        )
        self.assertRegex(
            php,
            re.compile(r"delete_post_meta\( \$post_id, Meta_Keys::VIDEO_EDITORIAL_URL \)"),
        )
        self.assertRegex(
            php,
            re.compile(
                r"update_post_meta\( \$post_id, Meta_Keys::VIDEO_TIMESTAMPS, wp_json_encode\( \$timestamps \) \)"
            ),
        )
        self.assertRegex(
            php,
            re.compile(
                r"update_post_meta\( \$post_id, Meta_Keys::VIDEO_KEY_CONCEPTS, wp_json_encode\( \$key_concepts \) \)"
            ),
        )

    def test_editorial_youtube_url_has_an_exact_host_allowlist_and_canonical_output(self) -> None:
        php = compact(source(VIDEO_META))
        expected_hosts = {
            "youtube.com",
            "www.youtube.com",
            "m.youtube.com",
            "youtu.be",
            "youtube-nocookie.com",
            "www.youtube-nocookie.com",
        }
        allowlist = re.search(r"\$allowed_hosts\s*=\s*\[(.*?)\];", php)
        self.assertIsNotNone(allowlist)
        actual_hosts = set(re.findall(r"'([^']+)'\s*=>\s*true", allowlist.group(1)))
        self.assertEqual(expected_hosts, actual_hosts)
        self.assertIn("wp_parse_url", php)
        self.assertIn("esc_url_raw", php)
        self.assertIn("https://www.youtube-nocookie.com/embed/", php)
        self.assertRegex(php, re.compile(r"\[A-Za-z0-9_-\]\{11\}"))

    def test_timestamp_normalizer_signals_ordered_bounded_structured_rows(self) -> None:
        php = compact(source(VIDEO_META))
        for mapping in (
            r"'id'\s*=>\s*\$id",
            r"'seconds'\s*=>\s*\$seconds",
            r"'label'\s*=>\s*\$label",
            r"'takeaway'\s*=>\s*\$takeaway",
        ):
            with self.subTest(mapping=mapping):
                self.assertRegex(php, re.compile(mapping))
        for token in (
            "FILTER_VALIDATE_INT",
            "$seconds < 0",
            "$duration > 0 && $seconds > $duration",
            "sanitize_text_field",
            "sanitize_textarea_field",
            "wp_is_uuid",
            "wp_generate_uuid4",
            "usort",
        ):
            with self.subTest(token=token):
                self.assertIn(token, php)
        self.assertRegex(
            php,
            re.compile(
                r"\$left\['seconds'\]\s*<=>\s*\$right\['seconds'\].*"
                r"\$left\['_order'\]\s*<=>\s*\$right\['_order'\]"
            ),
        )

    def test_key_concepts_read_legacy_strings_and_store_structured_points(self) -> None:
        php = compact(source(VIDEO_META))
        self.assertIn("is_string( $point )", php)
        self.assertIn("is_array( $point )", php)
        self.assertRegex(php, re.compile(r"'id'\s*=>\s*\$id\s*,\s*'text'\s*=>\s*\$text"))
        self.assertIn("Meta_Keys::VIDEO_KEY_CONCEPTS", php)
        self.assertIn("wp_generate_uuid4", php)
        self.assertIn("wp_is_uuid", php)

    def test_importer_maps_optional_editorial_fields_without_changing_pending_status(self) -> None:
        php = compact(source(IMPORTER))
        self.assertIn("'post_status' => 'pending'", php)
        expected_mappings = (
            r"\$v\['editorial_video_url'\].*Meta_Keys::VIDEO_EDITORIAL_URL",
            r"\$v\['timestamps'\].*Meta_Keys::VIDEO_TIMESTAMPS",
            r"\$v\['key_concepts'\].*Meta_Keys::VIDEO_KEY_CONCEPTS",
        )
        for mapping in expected_mappings:
            with self.subTest(mapping=mapping):
                self.assertRegex(php, re.compile(mapping))
        for helper in (
            "Video_Meta::normalize_editorial_youtube_url",
            "Video_Meta::sanitize_timestamps",
            "Video_Meta::sanitize_key_concepts",
        ):
            with self.subTest(helper=helper):
                self.assertIn(helper, php)
        self.assertIn("array_key_exists( 'key_concepts', $v )", php)

    def test_import_document_defines_exact_new_shapes_and_legacy_compatibility(self) -> None:
        doc = source(IMPORT_DOC)
        self.assertIn('"editorial_video_url": "https://youtu.be/MADIAS12345"', doc)
        self.assertIn(
            '"timestamps": [{"id": "UUID", "seconds": 95, "label": "Audit initial", "takeaway": "Prioriser les erreurs bloquantes."}]',
            doc,
        )
        self.assertIn(
            '"key_concepts": [{"id": "UUID", "text": "Commencer par les pages indexables"}]',
            doc,
        )
        self.assertRegex(doc, re.compile(r"key_concepts.*string\[\].*compatible", re.IGNORECASE))
        self.assertIn("timestamps pilotent toujours la vidéo source", doc)


if __name__ == "__main__":
    unittest.main()
