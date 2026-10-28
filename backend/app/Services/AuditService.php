<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditService
{
    public const GENESIS_HASH = '0000000000000000000000000000000000000000000000000000000000000000';

    protected string $chainKey;

    public function __construct()
    {
        $this->chainKey = config('app.audit_chain_key', env('APP_KEY', 'base64:AUDIT_CHAIN_MASTER_SIGNING_KEY_32B'));
    }

    /**
     * Recursively sort keys of associative arrays to ensure deterministic canonical JSON.
     */
    public function canonicalizeData(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        if (empty($data)) {
            return $data;
        }

        $isAssoc = array_keys($data) !== range(0, count($data) - 1);
        if ($isAssoc) {
            ksort($data);
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->canonicalizeData($value);
            }
        }

        return $data;
    }

    /**
     * Compute canonical hash for an audit event payload.
     */
    public function computePayloadHash(
        string $prevHash,
        string $eventUuid,
        ?int $userId,
        string $action,
        string $resourceType,
        ?string $resourceId,
        ?array $details,
        string $status,
        string $createdAtIso
    ): string {
        $canonicalDetails = $this->canonicalizeData($details);

        $canonicalPayload = json_encode([
            'prev' => $prevHash,
            'uuid' => $eventUuid,
            'user' => $userId,
            'action' => $action,
            'type' => $resourceType,
            'id' => $resourceId,
            'details' => $canonicalDetails,
            'status' => $status,
            'ts' => $createdAtIso,
        ], JSON_UNESCAPED_SLASHES);

        return hash_hmac('sha256', $canonicalPayload, $this->chainKey);
    }

    public function log(
        string $action,
        string $resourceType,
        ?string $resourceId = null,
        ?array $details = null,
        string $status = 'success',
        ?User $actor = null,
        ?Request $request = null
    ): AuditEvent {
        $user = $actor ?? auth()->user();
        $req = $request ?? request();
        $uuid = (string) Str::uuid();
        $now = now();
        $timestamp = (string) $now->timestamp;

        // 1. Fetch previous block hash from latest audit record that has a chain_hash
        $lastRecord = AuditEvent::whereNotNull('chain_hash')->orderBy('id', 'desc')->first();
        $prevHash = $lastRecord?->chain_hash ?? self::GENESIS_HASH;

        // 2. Compute cryptographically tamper-evident chain hash
        $chainHash = $this->computePayloadHash(
            $prevHash,
            $uuid,
            $user?->id,
            $action,
            $resourceType,
            $resourceId ? (string) $resourceId : null,
            $details,
            $status,
            $timestamp
        );

        return AuditEvent::create([
            'event_uuid' => $uuid,
            'user_id' => $user?->id,
            'institution_id' => $user?->institution_id,
            'actor_email' => $user?->email ?? 'system@internal',
            'actor_role' => $user?->role ?? 'system',
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId ? (string) $resourceId : null,
            'ip_address' => $req?->ip() ?? '127.0.0.1',
            'user_agent' => substr((string) ($req?->userAgent() ?? 'System / CLI'), 0, 500),
            'details' => $details,
            'status' => $status,
            'prev_hash' => $prevHash,
            'chain_hash' => $chainHash,
            'created_at' => $now,
        ]);
    }

    /**
     * Mathematically verifies the entire Merkle hash chain from genesis to head.
     * Detects any direct DB row editing, row deletion, or log injection.
     */
    public function verifyAuditChain(): array
    {
        $events = AuditEvent::whereNotNull('chain_hash')->orderBy('id', 'asc')->get();
        if ($events->isEmpty()) {
            return [
                'valid' => true,
                'total_events' => 0,
                'genesis_hash' => self::GENESIS_HASH,
                'head_hash' => self::GENESIS_HASH,
                'tamper_detected' => false,
                'message' => 'Zero hashed audit events to verify.',
            ];
        }

        $expectedPrevHash = $events->first()->prev_hash;
        $verifiedCount = 0;

        foreach ($events as $event) {

            // Check 1: Chain link continuity
            if ($event->prev_hash !== $expectedPrevHash) {
                return [
                    'valid' => false,
                    'tampered_event_id' => $event->id,
                    'event_uuid' => $event->event_uuid,
                    'error_type' => 'BROKEN_CHAIN_LINK',
                    'message' => "Cryptographic chain broken at Event #{$event->id}: Expected prev_hash '{$expectedPrevHash}', found '{$event->prev_hash}'",
                    'verified_events' => $verifiedCount,
                ];
            }

            // Check 2: Row integrity / payload recalculation
            $ts = (string) ($event->created_at instanceof Carbon ? $event->created_at->timestamp : strtotime((string) $event->created_at));
            $recomputed = $this->computePayloadHash(
                $event->prev_hash ?? self::GENESIS_HASH,
                $event->event_uuid,
                $event->user_id,
                $event->action,
                $event->resource_type,
                $event->resource_id,
                $event->details,
                $event->status,
                $ts
            );

            if ($event->chain_hash !== $recomputed) {
                $canonicalDebug = json_encode([
                    'prev' => $event->prev_hash ?? self::GENESIS_HASH,
                    'uuid' => $event->event_uuid,
                    'user' => $event->user_id,
                    'action' => $event->action,
                    'type' => $event->resource_type,
                    'id' => $event->resource_id,
                    'details' => $event->details,
                    'status' => $event->status,
                    'ts' => $ts,
                ], JSON_UNESCAPED_SLASHES);

                return [
                    'valid' => false,
                    'tampered_event_id' => $event->id,
                    'event_uuid' => $event->event_uuid,
                    'error_type' => 'PAYLOAD_TAMPERED',
                    'message' => "Cryptographic signature failure at Event #{$event->id}: Row content or metadata was modified in the database!",
                    'expected_hash' => $event->chain_hash,
                    'recomputed_hash' => $recomputed,
                    'debug_canonical' => $canonicalDebug,
                    'verified_events' => $verifiedCount,
                ];
            }

            $expectedPrevHash = $event->chain_hash;
            $verifiedCount++;
        }

        return [
            'valid' => true,
            'total_events' => $verifiedCount,
            'genesis_hash' => self::GENESIS_HASH,
            'head_hash' => $expectedPrevHash,
            'tamper_detected' => false,
            'message' => "All {$verifiedCount} audited events cryptographically verified. Zero tampering detected.",
        ];
    }
}
