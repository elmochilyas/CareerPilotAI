<?php

namespace App\Support\SafeWebFetch;

use Carbon\CarbonInterface;

final class SafeFetchResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $body,
        public readonly ?string $finalUrl,
        public readonly ?string $title,
        public readonly ?string $contentType,
        public readonly ?CarbonInterface $retrievedAt,
        public readonly ?string $errorCode,
        public readonly ?string $errorMessage,
    ) {}

    public static function success(
        string $body,
        string $finalUrl,
        ?string $title,
        ?string $contentType,
        CarbonInterface $retrievedAt,
    ): self {
        return new self(
            ok: true,
            body: $body,
            finalUrl: $finalUrl,
            title: $title,
            contentType: $contentType,
            retrievedAt: $retrievedAt,
            errorCode: null,
            errorMessage: null,
        );
    }

    public static function failure(string $code, string $message): self
    {
        return new self(
            ok: false,
            body: null,
            finalUrl: null,
            title: null,
            contentType: null,
            retrievedAt: null,
            errorCode: $code,
            errorMessage: $message,
        );
    }

    /**
     * @return array{ok: bool, body: ?string, final_url: ?string, title: ?string, content_type: ?string, retrieved_at: ?string, error_code: ?string, error_message: ?string}
     */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'body' => $this->body,
            'final_url' => $this->finalUrl,
            'title' => $this->title,
            'content_type' => $this->contentType,
            'retrieved_at' => $this->retrievedAt?->toISOString(),
            'error_code' => $this->errorCode,
            'error_message' => $this->errorMessage,
        ];
    }
}
