<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application\Contracts;

use DgfipSI1\Application\ApplicationInterface;

/**
 * Implements methods for AppAwareInterface
 */
trait AppAwareTrait
{
    /** @var ApplicationInterface $application  */
    protected $application;

    /**
     * Set the config management object.
     *
     * @param ApplicationInterface $application
     *
     * @return $this
     */
    public function setApplication(ApplicationInterface $application)
    {
        $this->application = $application;

        return $this;
    }

    /**
     * Get the config management object.
     *
     * @return ApplicationInterface
     */
    public function getApplication()
    {
        return $this->application;
    }
}
