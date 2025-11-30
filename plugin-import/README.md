# Plugin Import

Drop a plugin ZIP or extracted plugin folder into this directory (or use the helper script) to run it in a separate WordPress instance.

How to use

1. Add your plugin (zip or folder):

```
# copy a plugin zip into the repo and import it
scripts/import-plugin.sh /path/to/my-plugin.zip
```

2. The script will extract the plugin(s) into `plugin-import/` and spin up a separate WordPress instance using `docker-compose.import.yml`.

3. Access the separate instance at:

- WordPress: http://localhost:8082
- phpMyAdmin: http://localhost:8083 (root / rootpassword)

Notes
- The import instance uses independent volumes `db_import_data` and `wordpress_import_data`, so it doesn't interfere with the main `docker-compose.yml` setup.
- To stop the import-instance run:

```
scripts/stop-import-stack.sh
```
