# Repository Guidelines

## Project Structure & Module Organization
- `src/`: core C++ server code, split by domain (`game/`, `server/`, `map/`, `lua/`, `io/`, `security/`, etc.).
- `tests/`: CMake-driven test suites (`unit/` and `integration/`) plus shared fixtures.
- `data/`, `data-crystal/`, `data-global/`: gameplay scripts, XML, map/world, NPC, and monster data.
- `cmake/` and `CMakePresets.json`: build configuration and reusable presets.
- `docker/`: containerized local stack and helper scripts.
- `schema.sql`: MySQL schema (validated in CI when changed).

## Build, Test, and Development Commands
- Configure + build (Linux release):
  ```bash
  cmake --preset linux-release
  cmake --build --preset linux-release
  ```
- Configure + build tests:
  ```bash
  cmake --preset linux-test
  cmake --build --preset linux-test
  ```
- Run unit tests:
  ```bash
  ctest --test-dir build/linux-test/tests/unit --verbose
  ```
- Windows build:
  ```powershell
  cmake --preset windows-release
  cmake --build --preset windows-release
  ```
- Optional guided Linux workflow: `./recompile.sh [vcpkg_base_path] [linux-release|linux-debug|linux-test]`.

## Coding Style & Naming Conventions
- Follow `.editorconfig`: UTF-8, LF, final newline; tabs are the default indentation.
- C/C++ style is enforced by `.clang-format` (CI uses clang-format 17 on `src/**`, excluding `src/protobuf`).
- Lua files are auto-formatted in CI using StyLua (`stylua .`).
- Naming patterns in C++: files and classes use snake_case (`networkmessage.cpp`, `account_repository.hpp`); keep new files consistent with nearby modules.

## Testing Guidelines
- Primary test framework: Boost.UT (`find_package(ut)` in `tests/CMakeLists.txt`).
- Add tests under the matching domain folder, e.g. `tests/unit/security/` for `src/security/` changes.
- Name test files with `_test.cpp` suffix.
- No explicit coverage gate is defined; contributors should add regression tests for bug fixes and new behavior in core systems.

## Commit & Pull Request Guidelines
- Commit style in history favors prefixes like `fix:`, `feat:`, `update:` and concise scope, often with PR reference (example: `fix: Lion's Rock Quest (#629)`).
- Keep each PR focused on one topic; avoid mixing unrelated refactors and content edits.
- Do not submit map-only changes (`*.otbm`) for review.
- PR descriptions should explain gameplay/behavior impact, rationale, and any schema or script migration steps.
