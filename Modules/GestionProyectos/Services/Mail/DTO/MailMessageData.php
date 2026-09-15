<?php

namespace Modules\GestionProyectos\Services\Mail\DTO;

use InvalidArgumentException;

final class MailMessageData
{
    /**
     * @param string[] $to
     * @param array<string,mixed> $meta
     */
    public function __construct(
        public readonly array $to,
        public readonly string $subject,
        public readonly string $html,
        public readonly ?string $text = null,
        public readonly array $meta = [],
    ) {
        if ($to === []) {
            throw new InvalidArgumentException('MailMessageData::to no puede estar vacío.');
        }

        foreach ($to as $email) {
            if (!is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException("Email inválido en MailMessageData::to: '{$email}'.");
            }
        }

        if (trim($subject) === '') {
            throw new InvalidArgumentException('MailMessageData::subject no puede estar vacío.');
        }

        if (trim($html) === '') {
            throw new InvalidArgumentException('MailMessageData::html no puede estar vacío.');
        }
    }

    public static function make(array|string $to, string $subject, string $html, ?string $text = null, array $meta = []): self
    {
        return new self(
            to: is_array($to) ? array_values(array_unique(array_filter($to))) : [$to],
            subject: $subject,
            html: $html,
            text: $text,
            meta: $meta,
        );
    }

    public function withMeta(array $extra): self
    {
        return new self(
            to: $this->to,
            subject: $this->subject,
            html: $this->html,
            text: $this->text,
            meta: array_merge($this->meta, $extra),
        );
    }

    public function withRecipients(array $to): self
    {
        return new self(
            to: array_values(array_unique(array_filter($to))),
            subject: $this->subject,
            html: $this->html,
            text: $this->text,
            meta: $this->meta,
        );
    }

    public function toArray(): array
    {
        return [
            'to'      => $this->to,
            'subject' => $this->subject,
            'html'    => $this->html,
            'text'    => $this->text,
            'meta'    => $this->meta,
        ];
    }

    public function triggerType(): ?string
    {
        return $this->meta['trigger_type'] ?? null;
    }

    public function modelType(): ?string
    {
        return $this->meta['model_type'] ?? null;
    }

    public function modelId(): ?int
    {
        return isset($this->meta['model_id']) ? (int) $this->meta['model_id'] : null;
    }
}
