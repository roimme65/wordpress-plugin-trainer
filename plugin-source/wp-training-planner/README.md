# Training Planner — plugin-source

Developer copy of the Training Planner plugin. This directory is intended as the editable source (plugin-source) and includes a safer activation hook which uses properly formatted SQL statements for dbDelta and an optional uninstall routine to drop created tables.

How to use
- Copy this folder into your WordPress plugin directory (wp-content/plugins/) or use it in local development via the included docker-compose in the repository root.
- Activate the plugin in WordPress admin.

Notes
- The activation function uses backticked column and key names to improve compatibility with dbDelta.
- This source is intentionally a smaller, safer copy for development. The production-ready implementation is in `plugin-target/wp-training-planner`.

Export ZIP
- The admin UI provides a "Download plugin ZIP" action which creates a zip of the current plugin folder and streams it to your browser. The archive contains a single top-level folder (the plugin slug) so it is ready to drop into other WordPress instances or to extract for editing.

Security
Packaging & GitHub

- This repository contains an automatic packaging workflow (.github/workflows/package-plugin.yml) that will run on push to `main` and on manual dispatch. It creates a zip archive from `plugin-source/wp-training-planner` and uploads it as an Actions artifact; on manual triggers it also creates a release with the ZIP attached.

- You can also create a local zip quickly using the included script:

```bash
cd /path/to/repo
chmod +x scripts/package-plugin.sh
./scripts/package-plugin.sh

# the generated .zip and a 'wp-training-planner-latest.zip' will be in ./exports/
```

Using the modern Docker Compose plugin

The scripts in this repo now prefer the modern Docker Compose plugin (invoked as `docker compose`). If you have the plugin installed on your machine use that; the scripts will fall back to the legacy `docker-compose` binary if the plugin is not available.

If you need to install the Compose plugin, follow Docker's docs: https://docs.docker.com/compose/install/


- Export is only available to users with `manage_options` capability and uses a nonce check.
