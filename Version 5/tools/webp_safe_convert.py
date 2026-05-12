from __future__ import annotations

import argparse
import json
from datetime import datetime
from pathlib import Path

from PIL import Image


IMAGE_EXTENSIONS = {".png", ".jpg", ".jpeg"}
TEXT_EXTENSIONS = {".html", ".css", ".js", ".php", ".json"}
WEBP_QUALITY = 85

IGNORED_PARTS = {
    ".git",
    ".next",
    "__pycache__",
    "ai1wm-backups",
    "build",
    "dist",
    "node_modules",
    "upgrade",
}

SKIPPED_IMAGE_NAMES = {
    "favicon.png",
}

GENERATED_REPORT_NAMES = {
    "webp-conversion-manifest.json",
    "webp-conversion-report.json",
}


def normalize_path(path: Path) -> str:
    return path.as_posix()


def is_wordpress_root(root: Path) -> bool:
    return (root / "wp-config.php").exists() and (root / "wp-content").is_dir()


def is_inside_ignored_folder(path: Path, root: Path) -> bool:
    parts = set(path.relative_to(root).parts)
    if parts & IGNORED_PARTS:
        return True

    if is_wordpress_root(root):
        rel_parts = path.relative_to(root).parts
        if rel_parts[:1] in (("wp-admin",), ("wp-includes",)):
            return True
        if rel_parts[:2] == ("wp-content", "uploads"):
            return True
        if rel_parts[:2] == ("wp-content", "plugins") and len(rel_parts) > 2 and rel_parts[2] != "leadwerk_importer":
            return True
        if rel_parts[:2] == ("wp-content", "themes") and len(rel_parts) > 2 and rel_parts[2] != "leadwerk_theme":
            return True

    return False


def collect_images(root: Path):
    images = []
    for file_path in root.rglob("*"):
        if not file_path.is_file():
            continue
        if file_path.name in GENERATED_REPORT_NAMES:
            continue
        if is_inside_ignored_folder(file_path, root):
            continue
        if file_path.name in SKIPPED_IMAGE_NAMES:
            continue
        if file_path.suffix.lower() in IMAGE_EXTENSIONS:
            images.append(file_path)
    return images


def collect_text_files(root: Path):
    text_files = []
    for file_path in root.rglob("*"):
        if not file_path.is_file():
            continue
        if file_path.name in GENERATED_REPORT_NAMES:
            continue
        if is_inside_ignored_folder(file_path, root):
            continue
        if file_path.suffix.lower() in TEXT_EXTENSIONS:
            text_files.append(file_path)
    return text_files


def convert_image_to_webp(image_path: Path, quality: int):
    webp_path = image_path.with_suffix(".webp")
    with Image.open(image_path) as img:
        converted = img.convert("RGBA") if img.mode in ("RGBA", "LA", "P") else img.convert("RGB")
        converted.save(webp_path, "WEBP", quality=quality, method=6)
    if not webp_path.exists() or webp_path.stat().st_size == 0:
        raise RuntimeError(f"WebP conversion failed: {image_path}")
    return webp_path


def build_replacement_variants(root: Path, old_path: Path, new_path: Path):
    old_rel = normalize_path(old_path.relative_to(root))
    new_rel = normalize_path(new_path.relative_to(root))
    old_name = old_path.name
    new_name = new_path.name
    old_url = old_rel.replace(" ", "%20")
    new_url = new_rel.replace(" ", "%20")
    variants = [
        (old_rel, new_rel),
        (f"./{old_rel}", f"./{new_rel}"),
        (old_url, new_url),
        (f"./{old_url}", f"./{new_url}"),
        (old_rel.replace("/", "\\"), new_rel.replace("/", "\\")),
        (old_name.replace(" ", "%20"), new_name.replace(" ", "%20")),
        (old_name, new_name),
    ]
    return variants


def update_text_references(root: Path, mappings):
    text_files = collect_text_files(root)
    changed_files = []

    for text_file in text_files:
        try:
            content = text_file.read_text(encoding="utf-8")
            original_content = content
        except UnicodeDecodeError:
            try:
                content = text_file.read_text(encoding="latin-1")
                original_content = content
            except Exception:
                continue

        for item in mappings:
            old_path = Path(item["old_absolute_path"])
            new_path = Path(item["new_absolute_path"])
            variants = build_replacement_variants(root, old_path, new_path)
            variants.sort(key=lambda pair: len(pair[0]), reverse=True)
            for old_value, new_value in variants:
                content = content.replace(old_value, new_value)

        if content != original_content:
            text_file.write_text(content, encoding="utf-8")
            changed_files.append(normalize_path(text_file.relative_to(root)))

    return changed_files


def write_manifest(root: Path, images):
    manifest = {
        "created_at": datetime.now().isoformat(timespec="seconds"),
        "root": normalize_path(root),
        "total_images": len(images),
        "skipped_image_names": sorted(SKIPPED_IMAGE_NAMES),
        "images": [
            {
                "relative_path": normalize_path(image.relative_to(root)),
                "absolute_path": normalize_path(image.resolve()),
                "size_bytes": image.stat().st_size,
                "target_webp_relative_path": normalize_path(image.with_suffix(".webp").relative_to(root)),
            }
            for image in images
        ],
    }
    manifest_path = root / "webp-conversion-manifest.json"
    manifest_path.write_text(json.dumps(manifest, indent=2, ensure_ascii=False), encoding="utf-8")
    return manifest_path


def run_conversion(root: Path, quality: int, dry_run: bool, delete_originals: bool, only_if_smaller: bool):
    root = root.resolve()
    if not root.exists() or not root.is_dir():
        raise ValueError(f"Root folder not found: {root}")

    images = collect_images(root)
    manifest_path = write_manifest(root, images)

    print(f"Root: {root}")
    print(f"WordPress root: {is_wordpress_root(root)}")
    print(f"Found eligible images: {len(images)}")
    print(f"Manifest saved: {manifest_path}")

    if dry_run:
        for img in images:
            print(f"[DRY] {img.relative_to(root)} -> {img.with_suffix('.webp').relative_to(root)}")
        return

    mappings = []
    failed = []
    skipped_not_smaller = []

    for image_path in images:
        try:
            old_size = image_path.stat().st_size
            webp_path = convert_image_to_webp(image_path, quality)
            new_size = webp_path.stat().st_size

            if only_if_smaller and new_size >= old_size:
                webp_path.unlink(missing_ok=True)
                skipped_not_smaller.append(
                    {
                        "relative_path": normalize_path(image_path.relative_to(root)),
                        "old_size_bytes": old_size,
                        "webp_size_bytes": new_size,
                    }
                )
                print(f"[SKIP bigger] {image_path.relative_to(root)}")
                continue

            mappings.append(
                {
                    "old_filename": image_path.name,
                    "new_filename": webp_path.name,
                    "old_relative_path": normalize_path(image_path.relative_to(root)),
                    "new_relative_path": normalize_path(webp_path.relative_to(root)),
                    "old_absolute_path": normalize_path(image_path.resolve()),
                    "new_absolute_path": normalize_path(webp_path.resolve()),
                    "old_size_bytes": old_size,
                    "new_size_bytes": new_size,
                    "saved_size_bytes": old_size - new_size,
                }
            )
            print(f"[OK] {image_path.relative_to(root)} -> {webp_path.relative_to(root)}")
        except Exception as exc:
            failed.append({"file": normalize_path(image_path.relative_to(root)), "error": str(exc)})
            print(f"[FAILED] {image_path.relative_to(root)} | {exc}")

    changed_files = update_text_references(root, mappings)
    for file in changed_files:
        print(f"[UPDATED] {file}")

    deleted_files = []
    if delete_originals:
        for item in mappings:
            old_file = Path(item["old_absolute_path"])
            new_file = Path(item["new_absolute_path"])
            if new_file.exists() and new_file.stat().st_size > 0 and old_file.exists():
                old_file.unlink()
                deleted_files.append(item["old_relative_path"])
                print(f"[DELETED] {item['old_relative_path']}")

    report = {
        "created_at": datetime.now().isoformat(timespec="seconds"),
        "root": normalize_path(root),
        "quality": quality,
        "delete_originals": delete_originals,
        "only_if_smaller": only_if_smaller,
        "converted_count": len(mappings),
        "failed_count": len(failed),
        "skipped_not_smaller_count": len(skipped_not_smaller),
        "updated_text_files_count": len(changed_files),
        "deleted_files_count": len(deleted_files),
        "saved_size_bytes": sum(item["saved_size_bytes"] for item in mappings),
        "converted": mappings,
        "failed": failed,
        "skipped_not_smaller": skipped_not_smaller,
        "updated_text_files": changed_files,
        "deleted_files": deleted_files,
    }
    report_path = root / "webp-conversion-report.json"
    report_path.write_text(json.dumps(report, indent=2, ensure_ascii=False), encoding="utf-8")
    print(f"Report saved: {report_path}")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Safely convert PNG/JPG/JPEG assets to WebP and update references.")
    parser.add_argument("--path", default=".", help="Root folder. Default: current folder")
    parser.add_argument("--quality", type=int, default=WEBP_QUALITY, help="WebP quality from 1 to 100. Default: 85")
    parser.add_argument("--dry-run", action="store_true", help="List eligible files only")
    parser.add_argument("--delete-originals", action="store_true", help="Delete successfully converted originals")
    parser.add_argument("--keep-bigger", action="store_true", help="Keep WebP even if it is larger than the original")
    args = parser.parse_args()
    run_conversion(
        root=Path(args.path),
        quality=args.quality,
        dry_run=args.dry_run,
        delete_originals=args.delete_originals,
        only_if_smaller=not args.keep_bigger,
    )
