<?php

namespace App\Service\Messenger;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\RuntimeException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;

final readonly class FailedMessageService
{
    public function __construct(
        #[Autowire(service: 'messenger.transport.failed')]
        private TransportInterface $failedTransport,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @return list<FailedMessageView>
     */
    public function list(int $limit = 50): array
    {
        $receiver = $this->getListableFailedTransport();
        $messages = [];

        foreach ($receiver->all($limit) as $envelope) {
            $messages[] = $this->createView($envelope);
        }

        return $messages;
    }

    public function retry(string $id): void
    {
        $receiver = $this->getListableFailedTransport();
        $envelope = $this->findEnvelope($id);

        $this->messageBus->dispatch($this->prepareForRetry($envelope));
        $receiver->reject($envelope);
    }

    public function remove(string $id): void
    {
        $receiver = $this->getListableFailedTransport();
        $receiver->reject($this->findEnvelope($id));
    }

    private function createView(Envelope $envelope): FailedMessageView
    {
        /** @var TransportMessageIdStamp|null $idStamp */
        $idStamp = $envelope->last(TransportMessageIdStamp::class);
        /** @var RedeliveryStamp|null $redeliveryStamp */
        $redeliveryStamp = $envelope->last(RedeliveryStamp::class);
        /** @var ErrorDetailsStamp|null $errorStamp */
        $errorStamp = $envelope->last(ErrorDetailsStamp::class);
        /** @var SentToFailureTransportStamp|null $failureStamp */
        $failureStamp = $envelope->last(SentToFailureTransportStamp::class);

        return new FailedMessageView(
            id: (string) ($idStamp?->getId() ?? ''),
            messageClass: $envelope->getMessage()::class,
            failedAt: $redeliveryStamp?->getRedeliveredAt()->format('d.m.Y H:i:s'),
            retryCount: $redeliveryStamp?->getRetryCount() ?? 0,
            originalTransport: $failureStamp?->getOriginalReceiverName(),
            exceptionClass: $errorStamp?->getExceptionClass(),
            exceptionMessage: $errorStamp?->getExceptionMessage(),
            exceptionCode: null === $errorStamp ? null : (string) $errorStamp->getExceptionCode(),
        );
    }

    private function findEnvelope(string $id): Envelope
    {
        $envelope = $this->getListableFailedTransport()->find($id);

        if (!$envelope instanceof Envelope) {
            throw new RuntimeException(sprintf('Failed message "%s" was not found.', $id));
        }

        return $envelope;
    }

    private function prepareForRetry(Envelope $envelope): Envelope
    {
        return $envelope
            ->withoutAll(ErrorDetailsStamp::class)
            ->withoutAll(RedeliveryStamp::class)
            ->withoutAll(SentToFailureTransportStamp::class)
            ->withoutAll(TransportMessageIdStamp::class)
            ->withoutAll(ReceivedStamp::class);
    }

    private function getListableFailedTransport(): ListableReceiverInterface
    {
        if (!$this->failedTransport instanceof ListableReceiverInterface) {
            throw new RuntimeException('Configured failed transport does not support listing messages.');
        }

        return $this->failedTransport;
    }
}
