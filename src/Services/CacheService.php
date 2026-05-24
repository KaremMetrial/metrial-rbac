<?php

namespace Metrial\RBAC\Services;

use Illuminate\Support\Facades\Cache;

class CacheService
{
    protected string $prefix;

    protected string $store;

    protected int $ttl;

    protected string $versionKey;

    protected bool $enabled;

    public function __construct()
    {
        $this->prefix = config('rbac.cache.prefix', 'rbac:');
        $this->store = config('rbac.cache.store', config('cache.default'));
        $this->ttl = (int) config('rbac.cache.ttl', 300);
        $this->versionKey = config('rbac.cache.version_key', 'rbac:schema_version');
        $this->enabled = (bool) config('rbac.cache.enabled', true);
    }

    public function remember(string $key, callable $callback, ?int $customTtl = null): mixed
    {
        if (! $this->enabled) {
            return $callback();
        }

        $version = Cache::store($this->store)->get($this->versionKey, 0);
        $namespacedKey = $this->prefix . $version . ':' . $key;

        return Cache::store($this->store)->remember(
            $namespacedKey,
            $customTtl ?? $this->ttl,
            $callback
        );
    }

    public function forget(string $key): void
    {
        if (! $this->enabled) {
            return;
        }

        $version = Cache::store($this->store)->get($this->versionKey, 0);
        Cache::store($this->store)->forget($this->prefix . $version . ':' . $key);
    }

    public function forgetByPattern(string $pattern): void
    {
        if (! $this->enabled) {
            return;
        }

        $version = Cache::store($this->store)->get($this->versionKey, 0);
        $fullPattern = $this->prefix . $version . ':' . $pattern;

        Cache::store($this->store)->forget($fullPattern);
    }

    public function flush(): void
    {
        if (! $this->enabled) {
            return;
        }

        Cache::store($this->store)->increment($this->versionKey);
    }

    public function resolveTtl(?string $expiresAt = null): int
    {
        if ($expiresAt === null) {
            return $this->ttl;
        }

        $expires = \Carbon\Carbon::parse($expiresAt);
        $now = now();

        if ($expires->isPast()) {
            return 0;
        }

        $diff = (int) $now->diffInSeconds($expires, false);

        return min($diff, $this->ttl);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getTtl(): int
    {
        return $this->ttl;
    }
}
