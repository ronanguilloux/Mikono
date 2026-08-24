#!/usr/bin/env python3
"""Strip vendor frontmatter extensions from imported SKILL.md files.

Keeps only `name` and `description`, and rewrites a colon-namespaced
`name` (e.g. `symfony:symfony-voters`) to the bare folder name, so every
skill matches this repo's .agents/skills/README.md convention. Re-run
after any future re-import of external skill packages.
"""
import sys
import pathlib

def normalize(path: pathlib.Path) -> bool:
    folder_name = path.parent.name
    text = path.read_text()
    if not text.startswith("---\n"):
        print(f"SKIP (no frontmatter): {path}")
        return False
    end = text.index("\n---\n", 4)
    fm_raw = text[4:end]
    body = text[end + len("\n---\n"):]

    # Minimal YAML top-level key scan (frontmatter here is flat + block scalars).
    lines = fm_raw.split("\n")
    keys = {}
    i = 0
    while i < len(lines):
        line = lines[i]
        if line.startswith(("name:", "description:")):
            key, _, rest = line.partition(":")
            value = rest.strip()
            i += 1
            # Pull in any indented continuation lines for this key.
            while i < len(lines) and lines[i].startswith(("  ", "\t")):
                value += " " + lines[i].strip()
                i += 1
            keys[key.strip()] = value.strip().strip('"').strip("'")
        else:
            i += 1

    name = keys.get("name", folder_name)
    if ":" in name:
        name = name.split(":")[-1]
    if name != folder_name:
        print(f"NAME MISMATCH after strip: {path} -> {name!r} vs folder {folder_name!r}")
    description = keys.get("description", "")

    new_fm = f"name: {name}\ndescription: {description}\n"
    path.write_text(f"---\n{new_fm}---\n{body}")
    return True

def main():
    root = pathlib.Path(sys.argv[1] if len(sys.argv) > 1 else ".agents/skills")
    changed = 0
    for skill_md in sorted(root.glob("*/SKILL.md")):
        if normalize(skill_md):
            changed += 1
    print(f"Normalized {changed} SKILL.md files under {root}")

if __name__ == "__main__":
    main()
