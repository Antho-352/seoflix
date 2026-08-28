from __future__ import annotations

import hashlib
import re
import unittest
from pathlib import Path

from php_source import REPO_ROOT, source


class WeasHomeHeroCinematicContracts(unittest.TestCase):
    def test_cinematic_video_is_local_silent_looping_and_controllable(self) -> None:
        front = source("theme/seoflix/front-page.php")
        video = REPO_ROOT / "theme/seoflix/assets/video/weas-cerveau-neural-loop.mp4"
        self.assertTrue(video.is_file())
        self.assertEqual(
            "c063a84e134bf951dc37db502b4029af3f1417d5b44bb1edeee048783084166a",
            hashlib.sha256(video.read_bytes()).hexdigest(),
        )
        for token in (
            "sx-home-hero__video",
            "get_theme_file_uri( 'assets/video/weas-cerveau-neural-loop.mp4' )",
            "autoplay muted loop playsinline",
            'preload="metadata"',
            'aria-hidden="true"',
            'class="sx-home-hero__motion"',
            'aria-pressed="false"',
            "Pause animation",
        ):
            self.assertIn(token, front)

    def test_title_uses_stable_three_line_rotation_and_complete_accessible_copy(self) -> None:
        front = source("theme/seoflix/front-page.php")
        for token in (
            "sx-home-hero__lead",
            "sx-home-hero__rotator",
            "sx-home-hero__term",
            "sx-home-hero__tail",
            ">Apprends<",
            ">gratuitement.<",
            "Apprends gratuitement l’affiliation SEO, YouTube, la vente de liens, l’IA et l’automatisation, la vente de leads et le freelancing.",
            "foreach ( $hero_labels as $hero_label_index => $hero_label )",
        ):
            self.assertIn(token, front)
        self.assertNotIn("sans perdre des heures sur YouTube.", front[front.index('id="madias-home-title"'):front.index('</h1>', front.index('id="madias-home-title"'))])
        self.assertNotIn("aria-live=", front)

    def test_layout_is_left_aligned_with_stable_rotation_and_mobile_fit(self) -> None:
        css = source("theme/seoflix/style.css")
        required = (
            ".sx-home-hero__media",
            ".sx-home-hero__video",
            ".sx-home-hero__rotator",
            ".sx-home-hero__term",
            ".sx-home-hero__term--medium",
            ".sx-home-hero__term--long",
            ".sx-home-hero__term.is-active",
            "width: calc(100% - 40px)",
            "width: calc(100% - 32px)",
            "max-width: none",
            "text-align: left",
            "height: 1.05em",
            "overflow: hidden",
            "white-space: nowrap",
            "@media (prefers-reduced-motion: reduce)",
        )
        for token in required:
            self.assertIn(token, css)
        reduced = css[css.index("@media (prefers-reduced-motion: reduce)"):]
        self.assertIn("display: none", reduced)
        self.assertIn("animation: none", reduced)

    def test_pause_control_pauses_video_and_rotation_without_announcing_loop(self) -> None:
        front = source("theme/seoflix/front-page.php")
        script = front[front.index("<script>", front.index("sx-home-hero__motion")):front.index("</script>", front.index("sx-home-hero__motion"))]
        for token in (
            "classList.toggle('is-paused', paused)",
            "classList.toggle('is-active', current === termIndex)",
            "window.setInterval(function()",
            "window.clearInterval(rotationId)",
            "video.pause()",
            "video.play().then(function()",
            "Relancer l’animation",
            "setAttribute('aria-pressed'",
        ):
            self.assertIn(token, script)
        self.assertIn("stopRotation();\n\t\tsetPaused(true);", script)
        self.assertNotIn("catch(function() {})", script)
        self.assertNotIn("aria-live", script)

    def test_theme_version_is_bumped_for_cache_invalidation(self) -> None:
        style = source("theme/seoflix/style.css")
        self.assertRegex(style[:300], re.compile(r"Version:\s+0\.14\.6\b"))


if __name__ == "__main__":
    unittest.main(verbosity=2)
