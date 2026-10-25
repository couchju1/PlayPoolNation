import os

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
REPO = os.path.dirname(os.path.dirname(ROOT))
OUT = os.path.join(ROOT, "out")
PROGRESS = os.path.join(ROOT, "progress.md")


def city_dir(slug: str) -> str:
    path = os.path.join(OUT, slug)
    os.makedirs(path, exist_ok=True)
    return path
