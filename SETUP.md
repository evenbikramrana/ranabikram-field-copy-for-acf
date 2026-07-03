# CI/CD setup — Ranabikram Field Copy for ACF

Git is the source of truth; WordPress.org SVN is just a release target. You push a
Git tag, and GitHub Actions commits to SVN `trunk`, creates `tags/<version>`, and
syncs the artwork. You never run `svn` by hand again.

## Repo layout

```
ranabikram-field-copy-for-acf/        <- GitHub repo root (== SVN trunk contents)
├─ ranabikram-field-copy-for-acf.php
├─ readme.txt
├─ assets/                            <- SHIPS: the plugin's own JS/CSS
│  ├─ copy-field.js
│  └─ copy-field.css
├─ .wordpress-org/                    <- DOES NOT SHIP: WordPress.org listing art
│  ├─ icon-256x256.png
│  ├─ icon-128x128.png
│  ├─ banner-772x250.png
│  ├─ banner-1544x500.png
│  ├─ screenshot-1.png   (add when captured)
│  └─ screenshot-2.png   (add when captured)
├─ .distignore                        <- what NOT to ship to users
├─ .gitignore
└─ .github/workflows/
   ├─ plugin-check.yml                <- CI gate on PRs / pushes to main
   ├─ deploy.yml                      <- deploy on version tag
   └─ asset-update.yml               <- (optional) listing updates w/o a release
```

The same "two assets folders" rule you already met applies here: `assets/` is the
plugin's own runtime code and ships to users; `.wordpress-org/` is the store
artwork and is synced to the SVN `assets/` directory, never into the download.

## One-time setup

1. **Create the GitHub repo from the plugin files** — i.e. what currently lives in
   your SVN `trunk/` (the `.php`, `readme.txt`, and `assets/`). Do NOT copy the SVN
   `trunk/ tags/ assets/` structure into Git; in Git the plugin files sit at the
   repo root. Then add this `.github/`, `.wordpress-org/`, `.distignore`, `.gitignore`.

   ```bash
   cd path/to/plugin-files
   git init -b main
   git add .
   git commit -m "Initial import 1.0.0"
   git remote add origin git@github.com:<you>/ranabikram-field-copy-for-acf.git
   git push -u origin main
   ```

2. **Add two repository secrets** in GitHub → Settings → Secrets and variables →
   Actions → *New repository secret*:
   - `SVN_USERNAME` = `ranabikram`
   - `SVN_PASSWORD` = the WordPress.org SVN password you already generated
     (the same one your manual `svn commit` used — NOT your login password).

   Secrets are encrypted and never printed in logs, so a public repo is fine.

That's it. `SLUG` is hard-coded to `ranabikram-field-copy-for-acf` in the workflows,
so it works even if you name the GitHub repo something else.

## Cutting a release (the whole future workflow)

1. Make your changes on a branch, open a PR → **Plugin Check** runs automatically.
2. When ready to ship, bump the version in **both** places (they must match):
   - `ranabikram-field-copy-for-acf.php` header: `Version: 1.0.1`
   - `readme.txt`: `Stable tag: 1.0.1`  (and add a `= 1.0.1 =` changelog entry)
3. Merge to `main`.
4. Tag and push:
   ```bash
   git tag 1.0.1
   git push origin 1.0.1
   ```
5. `deploy.yml` fires: pushes to SVN `trunk`, creates `tags/1.0.1`, updates the
   artwork, and publishes a matching GitHub Release with the built ZIP.

Notes:
- A version can only be deployed once — always increment. Re-pushing the same
  version is skipped by the action.
- The leading `v` on a tag is stripped automatically, so `1.0.1` and `v1.0.1`
  both produce SVN tag `1.0.1`. Pick one style and stay consistent.
- WordPress.org delays **updates by ~24 hours** for security review before they
  reach users — same delay you saw on first release. The deploy itself is instant;
  the rollout is what's delayed.

## Updating just the listing (optional)

To change the description, banner, icon, or screenshots without a new release,
edit `readme.txt` or files in `.wordpress-org/`, push to `main`, and
`asset-update.yml` syncs only those to WordPress.org.

## Nice-to-haves (not included, easy to add later)

- **WPCS / PHPCS** with `WordPress` standard for coding-standards linting on PRs.
- **Dependabot** for keeping the GitHub Actions themselves up to date.
