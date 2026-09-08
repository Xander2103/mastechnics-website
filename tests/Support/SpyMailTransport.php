<?php

namespace Tests\Support;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * Stand-in for the SMTP transport that talks to Brevo in production. Every
 * call to send() is exactly one "external mail provider call"; the spy
 * counts them instead of opening a socket. Mail::fake() is NOT used in the
 * protection tests on purpose: it replaces the whole mailer, so it cannot
 * prove that the transport layer itself is never reached.
 */
class SpyMailTransport implements TransportInterface
{
    /** @var array<int, array{to: array<int, string>, subject: ?string}> */
    public array $sent = [];

    public ?\Throwable $failWith = null;

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $envelope ??= Envelope::create($message);

        $to = [];
        $subject = null;

        if ($message instanceof Email) {
            foreach ($message->getTo() as $address) {
                $to[] = $address->getAddress();
            }
            $subject = $message->getSubject();
        }

        $this->sent[] = ['to' => $to, 'subject' => $subject];

        if ($this->failWith !== null) {
            throw $this->failWith;
        }

        return new SentMessage($message, $envelope);
    }

    public function calls(): int
    {
        return count($this->sent);
    }

    /** @return array<int, string> every recipient address, in send order */
    public function recipients(): array
    {
        return array_merge(...array_map(fn (array $m) => $m['to'], $this->sent ?: [[]]));
    }

    public function __toString(): string
    {
        return 'spy://';
    }
}
