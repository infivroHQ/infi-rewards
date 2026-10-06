# Build the plugin ZIP

From the plugin repository root, run:

```bash
bash scripts/build-release.sh
```

Requires `bash`, `xgettext`, `rsync`, and `zip`. The script checks that release
versions agree and generates the translation template.

The ZIP is written to `dist/infivro-loyalty-rewards-VERSION.zip`, using the
version in `readme.txt`. Running the command again replaces that ZIP.

To choose another output path:

```bash
bash scripts/build-release.sh /tmp/infivro-loyalty-rewards.zip
```

The ZIP contains the `infivro-loyalty-rewards/` plugin folder. Markdown files
are excluded, including those inside source or asset folders and files with
uppercase extensions. Development files and this documentation are excluded.
