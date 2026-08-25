from __future__ import annotations

import re
import unittest

from php_source import source


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


if __name__ == "__main__":
    unittest.main()
