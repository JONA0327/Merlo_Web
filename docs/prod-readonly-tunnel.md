# Prod DB, read-only, via SSH tunnel

Ad-hoc read access to the real production MariaDB (`merlo_web`) for debugging — never the app's default connection.

## Server side (already set up)

- SSH account `merlotunnel` on `31.220.63.78`, no shell (`nologin`), `authorized_keys` restricted to `restrict,port-forwarding,permitopen="127.0.0.1:3306"` — it can only forward to the DB port, nothing else.
- DB user `merlo_ro`, `SELECT`-only on `merlo_web.*`.

## Open the tunnel

```
ssh -i ~/.ssh/merlo_prod_tunnel -N -L 3307:127.0.0.1:3306 merlotunnel@31.220.63.78
```

Leave it running in its own terminal (or background it). Local port 3307 then proxies to the server's MySQL.

## Use it

- `.env` has `PROD_RO_DB_*` filled in; `config/database.php` has a `prod_readonly` connection that uses them. Never the `default` connection — always address it explicitly:
  ```php
  DB::connection('prod_readonly')->table('landing_routes')->count();
  ```
- From Tinker: `php artisan tinker` then `DB::connection('prod_readonly')->select('SHOW TABLES');`
- A GUI client (DBeaver/TablePlus/Workbench): host `127.0.0.1`, port `3307`, user/password from `.env`.

Write attempts fail at the DB level (`SELECT`-only grant) — this is a second guard, not the only one; don't rely on it to skip checking which connection a query runs against.
