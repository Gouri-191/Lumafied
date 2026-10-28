# AICTE FDR Portal — Cryptographic Envelope Encryption Specification

## 1. Overview
The platform treats financial Fixed Deposit Receipts as highly confidential regulatory assets. Rather than relying solely on full-disk encryption, the application implements **Envelope Encryption (AES-256-GCM)** at the application layer.

```
       Plaintext Document File
                 │
                 ▼
 ┌────────────────────────────────┐
 │ Generate Ephemeral 256-bit DEK │
 └────────────────────────────────┘
                 │
   ┌─────────────┴─────────────┐
   ▼                           ▼
[AES-256-GCM]           [AES-256-GCM]
with DEK & 12-byte IV   with Master KEK & Nonce
   │                           │
   ▼                           ▼
Ciphertext File          Wrapped DEK Payload
+ 16-byte Auth Tag       (Stored in MySQL)
(Stored in Vault)
```

## 2. Key Hierarchy & Envelope Lifecycle
1. **Master Key Encryption Key (KEK)**:
   - 256-bit AES master key configured via `FDR_KEK_MASTER_KEY` environment variable (compatible with AWS KMS, Azure Key Vault, or local HSM).
   - Never stored in the database.
2. **Data Encryption Key (DEK)**:
   - A unique 256-bit cryptographic key generated per document version using `random_bytes(32)`.
   - Used exactly once to encrypt the specific document file.
3. **Initialization Vector (Nonce)**:
   - 96-bit (12-byte) random nonce generated per encryption using `random_bytes(12)`.
   - Never reused across keys.
4. **Authentication Tag**:
   - 128-bit (16-byte) GCM authentication tag generated during authenticated encryption.
   - Decryption strictly requires this tag. Any byte alteration in ciphertext or tag immediately triggers an authentication failure and halts processing.

## 3. Storage and Delivery Architecture
- Encrypted files are stored on disk in `storage/app/vault/{institution_id}/{uuid}.enc`.
- The storage directory is strictly outside the public web root (`public/`).
- Downloads stream decrypted bytes in-memory directly to authenticated HTTP response streams; decrypted plaintext is never written to disk or temporary cache files.

## 4. Tamper Detection & Bitwise Verification
- Before encryption, the raw SHA-256 digest is computed and recorded in `documents.file_hash_sha256`.
- During download, the system verifies:
  1. GCM Authentication Tag validation via OpenSSL.
  2. Plaintext SHA-256 digest recomputation against the recorded hash.
- Failure of either check logs a `DOCUMENT_TAMPER_DETECTED` audit alert and aborts the download.

---

## 5. Defense-in-Depth Hashing: The Salt vs. Pepper Cryptographic Reality

### Why a Salt Alone is Not "Unhackable"
A cryptographic **salt** is an unencrypted, public, pseudo-random nonce stored directly in the database row alongside the password hash (e.g., `$argon2id$v=19$m=65536,t=4,p=1$<salt>$<hash>`).
- **Salt's actual purpose**: Prevents rainbow tables and cross-user precomputation attacks.
- **The vulnerability**: If an attacker compromises the SQL database (via SQL injection, database snapshot leak, or rogue DBA), **the attacker has both the hash AND the salt**. They can run high-speed GPU or ASIC dictionary attacks offline without interacting with the server.

### The Bulletproof Solution: KMS-Isolated Secret Pepper + Argon2id
To make offline cracking mathematically impossible even if 100% of the database is stolen, the AICTE FDR Portal implements **Two-Stage Peppered Argon2id**:

```
                       Plaintext Password
                              │
                              ▼
        ┌───────────────────────────────────────────┐
        │ Stage 1: HMAC-SHA512 with Isolated Pepper │  <── Secret Pepper Key
        │ (Key stored in external HSM / KMS / Env)   │      (NEVER exists in Database)
        └───────────────────────────────────────────┘
                              │
                              ▼
                     64-byte HMAC Digest
                              │
                              ▼
        ┌───────────────────────────────────────────┐
        │ Stage 2: Argon2id Memory-Hard Hashing     │  <── Unique Per-User Salt
        │ (Memory Cost: 64MB RAM, Time Cost: 4)     │
        └───────────────────────────────────────────┘
                              │
                              ▼
                   Stored Database Hash
```

1. **Physical Isolation**: The `SECURITY_PEPPER` key (256+ bits) is managed strictly in runtime environment variables or a Cloud Hardware Security Module (KMS/HSM). It never touches the database.
2. **Offline Impossibility**: Without the pepper key, an attacker holding the SQL dump has mathematically zero capability to verify any password guess, rendering GPU hashcat clusters completely useless.
3. **Memory Hardness**: Argon2id requires 64MB of physical RAM per single hash calculation, shutting down parallel ASIC/FPGA hardware attacks.

---

## 6. Cryptographic Merkle Hash Chaining (Tamper-Evident Audit Trail)

To prevent internal database tampering (such as an insider modifying an audit record to conceal an unauthorized FDR approval):
Each audit log entry forms a link in a cryptographic chain:

$$\text{ChainHash}_n = \text{HMAC-SHA256}\left(\text{PrevHash}_{n-1} \parallel \text{UUID} \parallel \text{Action} \parallel \text{Payload} \parallel \text{Timestamp}, K_{\text{audit}}\right)$$

- **Genesis Block**: Event #1 links to `0000000000000000000000000000000000000000000000000000000000000000`.
- **Row Continuity**: Every subsequent event links to the `chain_hash` of its immediate predecessor.
- **Verification API**: Designated auditors can trigger `/api/v1/audit-events/verify-chain`. If an attacker alters a single character or deletes any audit record, the recalculation will immediately fail, flagging the exact row ID and tampering vector (`PAYLOAD_TAMPERED` or `BROKEN_CHAIN_LINK`).

---

## 7. Kerckhoffs's Principle & Why We Do Not Use "Homemade Ciphers"

> *"A cryptosystem should be secure even if everything about the system, except the key, is public knowledge."* — Auguste Kerckhoffs (1883)
> *"Anyone, from the most clueless amateur to the best cryptographer, can create an algorithm that he himself cannot break."* — Bruce Schneier (Schneier's Law)

Homemade ciphers (e.g. custom XOR shufflers or unvetted substitution algorithms) invariably suffer from subtle mathematical weaknesses (such as frequency bias, slide attacks, and timing side channels). 

The AICTE FDR Portal achieves **true provable bulletproof security** through:
1. NIST-standardized **AES-256-GCM** (Galois/Counter Mode for authenticated encryption).
2. Password Hashing Competition winner **Argon2id** (memory-hard, resistant to side-channel and GPU attacks).
3. **HMAC-SHA512** secret pepper with strict key isolation.
4. **Merkle hash chaining** on all system activity.
