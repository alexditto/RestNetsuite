<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient;

use Ditto\NetSuiteClient\Auth\AuthMethod;
use InvalidArgumentException;

final class NetSuiteConfig
{
    public const ENV_SANDBOX = 'sandbox';
    public const ENV_PRODUCTION = 'production';
    public const DEFAULT_MAX_ATTEMPTS = 3;

    private const TBA_HASH_ALGORITHMS = ['sha1', 'sha256'];

    /**
     * @param string[] $scopes
     */
    public function __construct(
        public readonly string $accountId,
        public readonly AuthMethod $authMethod,
        public readonly ?string $clientId = null,
        public readonly ?string $certificateId = null,
        public readonly ?string $privateKeyPath = null,
        public readonly ?string $consumerKey = null,
        public readonly ?string $consumerSecret = null,
        public readonly ?string $tokenId = null,
        public readonly ?string $tokenSecret = null,
        public readonly string $environment = self::ENV_SANDBOX,
        public readonly array $scopes = ['restlets', 'rest_webservices'],
        public readonly string $tbaHashAlgorithm = 'sha256',
        public readonly int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS,
    ) {
        if (!in_array($this->environment, [self::ENV_SANDBOX, self::ENV_PRODUCTION], true)) {
            throw new InvalidArgumentException(sprintf(
                "Environment must be '%s' or '%s', got '%s'.",
                self::ENV_SANDBOX,
                self::ENV_PRODUCTION,
                $this->environment,
            ));
        }

        if (!in_array($this->tbaHashAlgorithm, self::TBA_HASH_ALGORITHMS, true)) {
            throw new InvalidArgumentException(sprintf(
                "tbaHashAlgorithm must be one of: %s. Got '%s'.",
                implode(', ', self::TBA_HASH_ALGORITHMS),
                $this->tbaHashAlgorithm,
            ));
        }

        if ($this->maxAttempts < 1) {
            throw new InvalidArgumentException("maxAttempts must be at least 1, got {$this->maxAttempts}.");
        }

        match ($this->authMethod) {
            AuthMethod::OAuth2 => $this->assertOAuth2FieldsPresent(),
            AuthMethod::TokenBasedAuth => $this->assertTbaFieldsPresent(),
        };
    }

    public function isProduction(): bool
    {
        return $this->environment === self::ENV_PRODUCTION;
    }

    private function assertOAuth2FieldsPresent(): void
    {
        $this->assertFieldsPresent('oauth2', [
            'clientId' => $this->clientId,
            'certificateId' => $this->certificateId,
            'privateKeyPath' => $this->privateKeyPath,
        ]);

        if (!is_file($this->privateKeyPath)) {
            throw new InvalidArgumentException("Private key file not found at '{$this->privateKeyPath}'.");
        }
    }

    private function assertTbaFieldsPresent(): void
    {
        $this->assertFieldsPresent('tba', [
            'consumerKey' => $this->consumerKey,
            'consumerSecret' => $this->consumerSecret,
            'tokenId' => $this->tokenId,
            'tokenSecret' => $this->tokenSecret,
        ]);
    }

    /**
     * @param array<string, ?string> $fields
     */
    private function assertFieldsPresent(string $authMethodLabel, array $fields): void
    {
        foreach ($fields as $name => $value) {
            if ($value === null || $value === '') {
                throw new InvalidArgumentException("'{$name}' is required when authMethod is '{$authMethodLabel}'.");
            }
        }
    }
}
