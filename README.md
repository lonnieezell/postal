# Myth/Postal

A starter template for building CodeIgniter 4 packages. Replace `Myth`, `Postal`, and related placeholders throughout the codebase before publishing.

## Starting a New Project from This Template

1. Create a new empty repo on GitHub (no README, no .gitignore).
2. Clone this template and point it at your new repo:

```bash
git clone https://github.com/lonnieezell/codeigniter-package-skeleton.git your-package-name
cd your-package-name
git remote set-url origin https://github.com/YOUR_ORG/your-package-name.git
git push -u origin main
```

3. Find and replace all placeholder strings throughout the codebase:

| Placeholder | Replace with |
|---|---|
| `Myth` | Your Composer vendor name (e.g. `Acme`) |
| `Postal` | Your package name (e.g. `MyAddon`) |
| `myth/postal` | Your Composer package slug (e.g. `acme/my-addon`) |

4. Run `composer install` (or `docker compose up`) to install dependencies.

> **Note on GitHub Workflows:** The CI workflows in `.github/workflows/` are configured to trigger on PRs targeting `main` or pushes directly to `main`. If your project uses a different branching strategy (e.g., PRs go to `develop`, or you use a `release` branch), update the `branches:` values in each workflow file to match.

## Requirements

- PHP 8.2+
- CodeIgniter 4.7+

## Project Structure

```
src/
  Config/
    Registrar.php   # Hooks into CI4's auto-discovery (filters, etc.)
    Services.php    # Register package services
  Exceptions/
    PackageException.php
tests/
  ExampleTest.php
  _support/         # Test helpers and fixtures
docs/
  index.md          # Documentation home page
  installation.md   # Installation guide
  changelog.md      # Changelog
mkdocs.yml          # MkDocs configuration (Material theme)
```

## Getting Started with Docker

The repo includes a Docker setup using PHP 8.4 with all CI4-required extensions and Xdebug for coverage. Dependencies are installed automatically on first run.

Start the dev server (visits `http://localhost:8080` to see the CI4 welcome page):

```bash
docker compose up
```

Rebuild the image after changing the `Dockerfile`:

```bash
composer docker:build
```

## Running Tests

```bash
composer docker:test            # run phpunit in Docker
composer docker:test:coverage   # run with HTML coverage report (build/phpunit/html/)

# or locally
composer test
composer test:coverage
```

## Code Quality

```bash
composer docker:cs          # check coding style
composer docker:cs-fix      # fix coding style
composer docker:analyze     # PHPStan + Rector dry-run
composer docker:rector      # apply Rector changes
composer docker:ci          # run all checks (style, analysis, tests)

# or locally (same commands without the docker: prefix)
composer cs
composer cs-fix
composer analyze
composer ci
```

## Docker Shell

Open a bash shell inside the container:

```bash
composer docker:shell
```

## Documentation (MkDocs)

Docs live in `docs/` and are built with [Material for MkDocs](https://squidfunk.github.io/mkdocs-material/). Update `mkdocs.yml` with your `site_name`, `repo_url`, and `copyright` after cloning.

**Install MkDocs** (requires Python 3 + pip):

```bash
pip3 install mkdocs mkdocs-material
```

**Preview locally** (live-reload at `http://127.0.0.1:8000`):

```bash
mkdocs serve
```

**Build static output** to `site/`:

```bash
mkdocs build
```

**Deploy to GitHub Pages** (done automatically by CI, but can be run manually):

```bash
mkdocs gh-deploy
```

## How the Package Integrates with CI4

CI4 auto-discovers your package via `src/Config/Registrar.php`. Add filter aliases, routes, or other config there. Register services in `src/Config/Services.php`. No manual wiring needed in the host app — Composer autoload and CI4's discovery handle it automatically.

## License

MIT — see [LICENSE](LICENSE).
