<?php

namespace RFBP;

use Amp\Loop;
use RFBP\Transport\FromTransportIdStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

class Supervisor
{
    /** @var array<Envelope> */
    protected $envelopes;

    public function __construct(
        private ReceiverInterface $producer,
        private SenderInterface $consumer,
        /** @var array<Rail> */
        private $rails,
        private ?Rail $error = null
    ) {
        $this->envelopes = [];
    }

    public function start() {
        Loop::run(function() {
            Loop::repeat(1, callback: function() {
                $envelopes = $this->producer->get();
                foreach ($envelopes as $envelope) {
                    $ip = $envelope->getMessage();
                    if(!isset($this->envelopes[$ip->getId()])) {
                        $this->envelopes[$ip->getId()] = $envelope;
                    }
                }

                foreach ($this->envelopes as $envelope) {
                    /** @var IP $ip */
                    $ip = $envelope->getMessage();
                    if($ip->getCurrentRail() < count($this->rails)) {
                        $this->rails[$ip->getCurrentRail()]->run($ip);
                    } else {
                        unset($this->envelopes[$ip->getId()]);
                        $this->producer->ack($envelope);
                        $this->consumer->send(Envelope::wrap($ip, [$envelope->last(FromTransportIdStamp::class)]));
                    }
                }

                //echo "******* Tick *******\n";
            });
        });
    }
}