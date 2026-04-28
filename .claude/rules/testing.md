---
paths:
  - "tests/**/*.php"
---

# Testing Rules

- Tests use Pest v3 with `orchestra/testbench`. Never use PHPUnit directly.
- Unit tests in `tests/Unit/` do NOT need the TestCase base class — they are pure PHP with temp files.
- Feature tests in `tests/Feature/` extend `Shan\LaravelRefactor\Tests\TestCase` which bootstraps a real Laravel app via testbench.
- Always use `tempnam(sys_get_temp_dir(), ...)` for temporary PHP files in unit tests, and clean up in `afterEach`.
- Write PHP stubs as strings with `file_put_contents($path, "<?php\n\n" . $code)` — never rely on real project files.
- Test one behavior per `it()` block. No multi-assertion tests unless they test the same concept.
