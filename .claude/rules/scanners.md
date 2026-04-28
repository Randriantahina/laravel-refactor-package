---
paths:
  - "src/Scanners/**/*.php"
  - "src/Updaters/**/*.php"
---

# Scanner & Updater Rules

- `PhpFileScanner` uses a real AST via `nikic/php-parser`. Never replace AST logic with regex.
- The anonymous visitor class inside `PhpFileScanner::scan()` must stay stateless — `file` is always set to `''` inside the visitor and hydrated after via `array_map`.
- `extractNames()` must handle all four PHP type node variants: `Name`, `NullableType`, `UnionType`, `IntersectionType`.
- `PhpFileUpdater` uses regex intentionally — it preserves original formatting. Do not switch it to AST rewriting.
- Every new reference type added to the scanner needs a matching replacement in `PhpFileUpdater::replaceReferences()`.
- Always add a test in `tests/Unit/PhpFileScannerTest.php` or `tests/Unit/PhpFileScannerTypesTest.php` for new node types.
