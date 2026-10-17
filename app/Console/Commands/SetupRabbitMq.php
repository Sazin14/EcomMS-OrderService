<?php

namespace App\Console\Commands;

use App\Support\RabbitMq\RabbitMqConnection;
use Illuminate\Console\Command;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * Declares everything OrderService needs inside RabbitMQ. Safe to run again.
 *   exchanges: ecom.events (topic) and ecom.retry (direct)          - declared when connecting
 *   queue:     order.inventory  <- bound to inventory.preorder.allocated   (+ .retry and .dlq)
 * OrderService only PUBLISHES its own events (order.*, payment.*, return.*, refund.*) and owns no queue for them:
 * the services that listen (NotificationService, ...) create their own queues and bind them.
 */
class SetupRabbitMq extends Command
{
    protected $signature = 'rabbitmq:setup {--debug : also create a queue that keeps a copy of every event OrderService publishes, to look at in the management UI}';

    protected $description = 'Create the RabbitMQ exchanges, queues and bindings OrderService needs';

    public function handle(RabbitMqConnection $mq): int
    {
        $channel = $mq->channel();   // declares the exchanges ecom.events and ecom.retry

        $this->info('Exchanges ready: '.config('rabbitmq.exchange').', '.config('rabbitmq.retry_exchange'));

        // queue name => routing keys that should end up in it
        $queues = [
            'order.inventory' => ['inventory.preorder.allocated'],
        ];

        foreach ($queues as $queue => $bindingKeys) {
            $this->declareQueue($channel, $queue, $bindingKeys);
            $this->info("Queues ready: {$queue}, {$queue}.retry, {$queue}.dlq  (binding: ".implode(', ', $bindingKeys).')');
        }

        if ($this->option('debug')) {
            $channel->queue_declare('debug.order', false, true, false, false, false, new AMQPTable(['x-max-length' => 1000]));

            foreach (['order.#', 'payment.#', 'return.#', 'refund.#'] as $key) {
                $channel->queue_bind('debug.order', config('rabbitmq.exchange'), $key);
            }

            $this->info('Debug queue "debug.order" keeps the last 1000 order / payment / return / refund events.');
        }

        $mq->close();

        return self::SUCCESS;
    }

    /** main queue + retry queue (waits, then sends the message back) + dead-letter queue (parked for a human) */
    private function declareQueue(AMQPChannel $channel, string $queue, array $bindingKeys): void
    {
        $exchange = config('rabbitmq.exchange');
        $retryExchange = config('rabbitmq.retry_exchange');

        // A rejected message is dead-lettered to the retry exchange.
        $channel->queue_declare($queue, false, true, false, false, false, new AMQPTable([
            'x-dead-letter-exchange' => $retryExchange,
            'x-dead-letter-routing-key' => $queue,
        ]));

        foreach ($bindingKeys as $key) {
            $channel->queue_bind($queue, $exchange, $key);
        }

        // Holds a failed message for retry_delay_ms, then sends it back to the main queue.
        $channel->queue_declare("{$queue}.retry", false, true, false, false, false, new AMQPTable([
            'x-message-ttl' => (int) config('rabbitmq.retry_delay_ms'),
            'x-dead-letter-exchange' => '',               // "" = the default exchange, which routes by queue name
            'x-dead-letter-routing-key' => $queue,
        ]));
        $channel->queue_bind("{$queue}.retry", $retryExchange, $queue);

        // Messages that failed max_attempts times end up here.
        $channel->queue_declare("{$queue}.dlq", false, true, false, false, false);
    }
}
