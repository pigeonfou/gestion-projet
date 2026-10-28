# OddWorks standalone on Ubuntu 26.04

Cloudflare and OneForAll are deliberately outside this deployment.

The target runs on **127.0.0.1:18081** through the existing standard Nginx service and the existing PHP 8.5 FPM service with an isolated `oddworks` pool. No extra `oddworks-web.service` is required. This avoids changing ports 80, 8443 and 18080.

## Server preparation (manual, once)

Review the local Nginx configuration and existing FPM pools first. Install required PHP extensions (`pdo_sqlite`, `mbstring`, `curl`, `xml`, `zip`) and `python3-venv` if absent. From a checkout of this branch, run `sudo bash deploy/install-standalone.sh`. It fails if the dedicated configuration already exists or the port is occupied.

Initialize the database and admin credentials **before deploying**:

```bash
sudo -u www-data env PROJECTFLOW_DB_PATH=/var/lib/oddworks/database.sqlite PROJECTFLOW_ADMIN_PASSWORD='REPLACE_WITH_STRONG_PASSWORD' php install/init_database.php
```

Do not put a real password in shell history: prefer an interactive secure prompt or a temporary environment file with restrictive permissions. Never commit credentials.

For Outlook .msg support, provision `/opt/oddworks/shared/cdc-msg-venv` using Python venv and `requirements-cdc-msg.txt` independently of OneForAll.

## Deployment

The script `deploy/deploy-standalone.sh` accepts a full commit SHA reachable from `main`, stages a release, runs PHP syntax checks, applies schema migrations, atomically switches `/opt/oddworks/current`, and checks the local login page. The deployment must be initiated only after the server preparation and the database initialization. Do not run it on the existing OneForAll instance without completing isolation checks.

The application uses `PROJECTFLOW_BASE_PATH=` (empty) and `PROJECTFLOW_DB_PATH=/var/lib/oddworks/database.sqlite`. Public exposure and external routing are managed separately.
