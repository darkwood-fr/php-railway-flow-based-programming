<?php

namespace RFBP;

/**
 * Information Packet
 *
 * Class IP
 * @package RFBP
 */
class IP
{
    private string $id; // internal IP unique identifier

    // internal state for supervisor
    private int $railIndex;
    private ?\Throwable $exception;

    public function __construct(
        private object $data // Information Packet data representing any object
    ) {
        $this->id = uniqid('ip_', true);

        $this->railIndex = 0;
        $this->exception = null;
    }

    public function getId(): string {
        return $this->id;
    }

    public function getData(): object {
        return $this->data;
    }

    public function getRailIndex(): int
    {
        return $this->railIndex;
    }

    public function setRailIndex(int $railIndex): void
    {
        $this->railIndex = $railIndex;
    }

    public function getException(): ?\Throwable
    {
        return $this->exception;
    }

    public function setException(?\Throwable $exception): void
    {
        $this->exception = $exception;
    }
}