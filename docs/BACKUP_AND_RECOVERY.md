# AICTE FDR Portal — Backup & Disaster Recovery Runbook

## 1. Scope of Backups
Full recovery requires synchronized restoration of:
1. **Relational Database**: MySQL database containing FDR records, institutions, users, verification cases, audit logs, and wrapped DEKs.
2. **Encrypted Vault**: Physical `.enc` files stored in `storage/app/vault/`.
3. **Master Key Encryption Key (KEK)**: `FDR_KEK_MASTER_KEY` environment variable. Without this key, stored documents cannot be unwrapped or decrypted!

## 2. Automated Daily Backup Script
```bash
#!/usr/bin/env bash
set -euo pipefail

BACKUP_DIR="/var/backups/aicte-fdr/$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

# 1. Backup MySQL Database
mysqldump --opt --single-transaction --routines --triggers \
  -h 127.0.0.1 -u root -p aicte_fdr_portal | gzip > "$BACKUP_DIR/db_dump.sql.gz"

# 2. Backup Encrypted Vault Storage
tar -czf "$BACKUP_DIR/vault_storage.tar.gz" -C /var/www/aicte-portal/backend/storage/app vault

# 3. Encrypt the backup bundle using GPG with offsite recipient key
tar -cf "$BACKUP_DIR.tar" -C "$(dirname "$BACKUP_DIR")" "$(basename "$BACKUP_DIR")"
gpg --encrypt --recipient ops-backup@aicte-india.org "$BACKUP_DIR.tar"

rm -rf "$BACKUP_DIR" "$BACKUP_DIR.tar"
echo "Encrypted backup created: $BACKUP_DIR.tar.gpg"
```

## 3. Disaster Recovery Restoration Procedure
1. Provision new host with required PHP, MySQL, and Nginx runtimes.
2. Decrypt backup archive using authorized recovery key:
   `gpg --decrypt aicte_backup.tar.gpg | tar -xf -`
3. Restore MySQL database:
   `gunzip -c db_dump.sql.gz | mysql -u root aicte_fdr_portal`
4. Restore vault directory:
   `tar -xzf vault_storage.tar.gz -C /var/www/aicte-portal/backend/storage/app/`
5. Inject `FDR_KEK_MASTER_KEY` from secure vault into `.env`.
6. Run `php artisan test --filter=AicteFdrCoreSecurityTest` to verify that document decryption and database integrity are verified.
