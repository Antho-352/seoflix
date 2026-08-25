from __future__ import annotations

import os
from pathlib import Path


REPO_ROOT = Path(
    os.environ.get("SEOFLIX_FIXTURE_ROOT", Path(__file__).resolve().parents[2])
).resolve()


def source(relative_path: str) -> str:
    """Read a UTF-8 source file relative to the repository root."""
    return (REPO_ROOT / relative_path).read_text(encoding="utf-8")
