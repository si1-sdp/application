<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application\Contracts;

use Psr\Log\LoggerInterface;

/**
 * Implements methods for loggerAwareInterface
 */
trait LoggerAwareTrait
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Set the config management object.
     *
     * @param LoggerInterface $logger
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Get the config management object.
     *
     * @return LoggerInterface
     */
    public function log()
    {
        return $this->logger;
    }
}
