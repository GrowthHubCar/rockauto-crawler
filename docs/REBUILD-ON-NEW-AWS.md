# Rebuild the crawl fleet on a fresh AWS account

Updated **2026-07-30** for the unit-supervisor / autoscale / keepalive architecture.
Previous accounts were blocked for **identity verification**, not abuse — complete verification
fully on the new account, that is what fails.

**S3 is `NotSignedUp` on the current account**, so **Bunny is both the bundle store and the only
offsite copy of crawled rows**. Everything needed to rebuild lives on Bunny under `boot/`.

Rows are safe: `offsite_backup.sh` mirrors every chunk to Bunny every 5 min, so total loss of an
AWS account costs **<=5 minutes** of crawl.

---

## 0. What does NOT survive an account change

| Thing | Why | Action |
|---|---|---|
| `gw_endpoints.live.txt` | API Gateway ids are account-scoped | **Regenerate** (step 3) |
| instance profile `rockauto-crawler` | account-scoped | **Recreate** (step 2) |
| EBS volumes, on-box `out/` | gone with the account | rows already on Bunny |
| `units_box*.txt`, `plan.tgz`, `code.tgz` | portable | reuse as-is |

Region: use **us-east-1** as the control region and always pass `--region` explicitly. CloudShell
defaults to `eu-north-1`, and `AWS_REGION` overrides `AWS_DEFAULT_REGION`.
Set a **Budget alarm** (~$80, 80% email) before launching anything.

## 1. Bundles on Bunny (`boot/`)

```
code.tgz            scraper/ + bin/ + ingest_ec2/ + requirements.txt   (~64 MB)
boot.tgz            the 5 daemons + .bunny.env + gw_endpoints.live.txt (~9 KB)
plan.tgz            plan/fr seeded frontiers + plan/targets.tsv        (~0.6 MB)
units_box<N>.txt    per-box disjoint unit slice
visited_global.gz   DB-derived leaf skip-set, ~355k hrefs              (~3.8 MB)
```

Fetch needs no AWS credentials:
```bash
curl -sf -H "AccessKey: $BUNNY_STORAGE_KEY" \
     "https://$BUNNY_STORAGE_HOST/$BUNNY_STORAGE_ZONE/boot/code.tgz" -o /tmp/code.tgz
```

**Extract `boot.tgz` LAST so it overrides `code.tgz`.** It carries `.bunny.env`, which is never in
`code.tgz` — a box without it has a *silently dead* backup, which happened to three boxes.

## 2. New-account prerequisites

```bash
aws iam create-role --role-name rockauto-crawler \
  --assume-role-policy-document '{"Version":"2012-10-17","Statement":[{"Effect":"Allow",
    "Principal":{"Service":"ec2.amazonaws.com"},"Action":"sts:AssumeRole"}]}'
aws iam attach-role-policy --role-name rockauto-crawler \
  --policy-arn arn:aws:iam::aws:policy/AmazonSSMManagedInstanceCore
aws iam attach-role-policy --role-name rockauto-crawler \
  --policy-arn arn:aws:iam::aws:policy/AmazonAPIGatewayAdministrator
aws iam create-instance-profile --instance-profile-name rockauto-crawler
aws iam add-role-to-instance-profile --instance-profile-name rockauto-crawler \
  --role-name rockauto-crawler
```

vCPU quota is **per region** (~5-8 each, no approval needed) — that is how to get past a single
region's cap. Check before sizing:
```bash
aws service-quotas get-service-quota --region <rg> --service-code ec2 --quota-code L-1216C47A
```

## 3. Regenerate the API gateways — REQUIRED, old endpoints are dead

```bash
python bin/gw_health.py provision      # one REST API per reachable region
aws apigateway get-rest-apis --region <rg>   # enumerate what ACTUALLY exists
```
Build the list as **LIVE-ONLY, BARE HOSTNAMES**: `id.execute-api.<rg>.amazonaws.com` — no
`https://`, no `/ProxyStage`.

Two scars, both expensive:
- 12 of 41 ids in a saved list were **deleted** gateways carrying **55% of all traffic**. Every draw
  403'd -> 4 retries -> session rebuild. Captcha read 1.1% -> 8.9% and throughput collapsed.
  **Never trust a saved endpoint list; verify liveness first.**
- Full URLs instead of bare hostnames give `host='https'` and a doubled `/ProxyStage/ProxyStage`,
  and **every lane dies instantly**.

## 4. Launch a box

**RAM is the binder, not vCPU** — settled lane RSS is ~179 MB. `m7i.xlarge` (4 vCPU/16 GB) costs the
same quota as `c7i.xlarge` (8 GB) and holds ~2x the lanes. Ubuntu 22.04, 100 GB gp3,
`InstanceInitiatedShutdownBehavior=stop`.

user-data, in this order:
1. `apt-get install -y python3-venv python3-pip cron curl`
2. **8 GB swap** — an OOM kill is SIGKILL and discards the in-memory frontier
3. venv + `pip install requests beautifulsoup4 lxml pymysql Pillow boto3 requests-ip-rotator`
4. pull `code.tgz`, `plan.tgz`, then `boot.tgz` **last**
5. `tr -d '\r' < units.mine > tmp && mv tmp units.mine` — Windows-authored files carry CRLF, and a
   trailing `\r` makes every ukey resolve to a missing seed file: the box launches **zero lanes**
6. `cp gw_endpoints.live.txt gw_endpoints.txt`; `echo <lanes> > target.txt`
7. `chown -R ubuntu:ubuntu /home/ubuntu/repo` — a root-owned log file leaves the `ubuntu` process
   unable to append, which silently killed the Bunny backup on three boxes
8. cron `* * * * *` **and** `@reboot` -> `bin/keepalive.sh`
9. run `keepalive.sh` once to start everything

## 5. What runs on each box

| Process | Job |
|---|---|
| `unit_supervisor.sh` | keeps `target.txt` lanes busy from `units.mine`; marks `fr/done_<ukey>` on rc **42** (frontier drained = finished, never relaunch); SIGTERMs the youngest lanes to scale down |
| `autoscale.sh` | hill-climbs lane count on measured req/s, captcha as veto rail; owns `target.txt` |
| `offsite_backup.sh` | `INTERVAL=900` (300 if the account is at risk) -> Bunny. **Only offsite copy.** |
| `keepalive.sh` | cron-driven; revives any of the above; survives reboot |
| `relay.sh` | S3 shipper — **leave dead while S3 is NotSignedUp**, it re-gzips every chunk into a dead endpoint and burns CPU for nothing |

## 6. Do not repeat these

- **One ingest worker.** Parallel workers deadlock on shared `brands`/`parts` rows (5 -> 2 died;
  3 -> 451 rows/s, *worse* than 1 at 649). Splitting *files* does not split *rows*.
- **More lanes != more throughput.** RockAuto tarpits with latency long before it captchas. Per-lane
  rows/s is flat vs lane count (A/B: 36 lanes = 0.549, 97 lanes = 0.569). Let autoscale decide;
  do not cap or cut lanes by hand.
- **Skip-set is roughly neutral.** A/B 2026-07-30: 19.2 vs 19.0 useful rows/s. It saves the leaf
  fetch but the lane still walks the nav tree to discover it. And `new_leaves` is **not comparable
  between arms** — with the skip-set off, `prior` is empty so re-crawls count as "new".
- `plan/skip/*.txt` is **unusable**: keys are `l:<vehicle>|<category>` but the crawler keys on
  `node["href"]`. Build it from the DB instead — that IS the right key space:
  ```sql
  SELECT DISTINCT REPLACE(source_url,'https://www.rockauto.com','') FROM parts
  WHERE source_url LIKE 'https://www.rockauto.com/en/catalog/%';
  ```
- Coverage bars must be **per MODEL**, never flat. A flat 2,010-fitments/vehicle bar invented a
  **47.8M phantom gap**: HINO maxes at 55 parts, UD 40, FUSO 44, while TOYOTA reaches 4,057.
- **SIGTERM is safe** (handlers break at a node boundary and save the frontier; plus a 60s
  checkpoint). SIGKILL costs <=60s of walking.
- `crawl_jsonl` opens `--out` in mode `"w"` — never reuse an `--out` name or banked rows are
  truncated. Same for `VISITED_OUT`: make it **per unit**, or ~90 lanes clobber each other.
- **Guard every remote install**: refuse <2000 bytes or missing markers, and `ast.parse` python
  before accepting. An unguarded base64 push wrote an **empty supervisor** and zeroed two boxes.
- Count lanes with `ps -eo comm= | grep -c '^python'`. `ps -eo args | grep python` also matches the
  sudo wrapper and reports double — that misread caused two outages.
- Never `pkill` a detached chain/daemon on Windows-side tooling; it does not match. Kill by PID, and
  match a **tight** pattern — a loose regex killed the operator's own shells.

## 7. Ingest (laptop side)

```bash
INGEST_OUT=ingest_ec2/solo INGEST_STATE=ingest_ec2/solostate MAXLINES=100000 \
  python ingest_ec2/drive.py
```
Resumable via `<state>/done_files.txt`. Measure the rate from the **batch log**, never from file
counts — files unlink only at the 100k-row batch boundary, so a short window reads a false 0.

After any MySQL restart re-apply these (dynamic, lost on restart):
```sql
SET GLOBAL innodb_flush_log_at_trx_commit=2, innodb_io_capacity=2000,
           innodb_io_capacity_max=4000, max_allowed_packet=67108864,
           innodb_lock_wait_timeout=180;
```
MySQL tuning was measured at **0.98x** — the ingest is CPU-bound in python, not IO-bound. Do not
chase MySQL config.
