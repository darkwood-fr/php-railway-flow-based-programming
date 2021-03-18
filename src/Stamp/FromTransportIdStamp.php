<?php

namespace RFBP\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

final class FromTransportIdStamp implements StampInterface
{
    private string $id;

    /**
     * @param string $id some "identifier" of the transport name
     */
    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public function getId(): string
    {
        return $this->id;
    }
}
