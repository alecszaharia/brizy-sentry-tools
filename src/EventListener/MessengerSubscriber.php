<?php

declare(strict_types=1);

namespace Brizy\SentryToolsBundle\EventListener;

use Psr\Log\LoggerInterface;
use Sentry\Breadcrumb;
use Sentry\State\HubInterface;
use Sentry\Tracing\SpanContext;
use Sentry\Tracing\TransactionContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Stamp\BusNameStamp;

/**
 * Class MessengerSubscriber
 */
class MessengerSubscriber implements EventSubscriberInterface
{
    private LoggerInterface $logger;
    private HubInterface $hub;

    public function __construct(HubInterface $hub, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->hub    = $hub;
    }

    /**
     * @return array[]
     */
    public static function getSubscribedEvents()
    {
        return [
            WorkerMessageReceivedEvent::class => ['onWorkerMessageReceivedEvent', 10],
            WorkerMessageHandledEvent::class  => ['onWorkerMessageHandledEvent', 10],
            WorkerMessageFailedEvent::class   => ['onWorkerMessageFailedEvent', 10],
        ];
    }

    public function onWorkerMessageHandledEvent(WorkerMessageHandledEvent $eventArgs)
    {
        $span = $this->hub->getSpan();

        $this->hub->addBreadcrumb(
            new Breadcrumb(
                Breadcrumb::LEVEL_INFO,
                Breadcrumb::TYPE_DEFAULT,
                'php',
                null,
                [
                    'memory_get_peak_usage'=>memory_get_peak_usage(true),
                    'memory_get_usage'=>memory_get_usage(true)
                ]
            )
        );

        if (null !== $span) {
            $span->finish();
        }
    }

    public function onWorkerMessageFailedEvent(WorkerMessageFailedEvent $eventArgs)
    {
        $span = $this->hub->getSpan();

        if (null !== $span) {

            $span->setTags(
                [
                    'messenger.fail_message' => $eventArgs->getThrowable()->getMessage(),
                ]
            );

            $span->finish();
        }
    }

    public function onWorkerMessageReceivedEvent(WorkerMessageReceivedEvent $eventArgs)
    {
        $currentSpan = $this->hub->getSpan();

        if (null === $currentSpan) {
            $transactionContext = new TransactionContext();
            $refClass = new \ReflectionClass($eventArgs->getEnvelope()->getMessage());
            $transactionContext->setOp('messenger.handle');
            $transactionContext->setName($refClass->getShortName());

            $span = $this->hub->startTransaction($transactionContext);
        } else {
            $spanContext = new SpanContext();
            $spanContext->setOp('messenger.handle');
            $spanContext->setDescription('Message: '.get_class($eventArgs->getEnvelope()->getMessage()));

            $span = $currentSpan->startChild($spanContext);
        }

        $envelope = $eventArgs->getEnvelope();
        $span->setTags(
            [
                'messenger.receiver_name' => $eventArgs->getReceiverName(),
                'messenger.message_class' => \get_class($envelope->getMessage()),
            ]
        );

        /** @var BusNameStamp|null $messageBusStamp */
        $messageBusStamp = $envelope->last(BusNameStamp::class);

        if (null !== $messageBusStamp) {
            $span->setTags(['messenger.message_bus'=> $messageBusStamp->getBusName()]);
        }

        $this->hub->setSpan($span);
    }

}
