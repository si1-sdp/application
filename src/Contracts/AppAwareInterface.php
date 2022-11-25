<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application\Contracts;

use DgfipSI1\Application\ApplicationInterface;

/**
 * ConfigAwareInterface - get access to config
 */
interface AppAwareInterface
{
   /**
     * Set the config reference.
     *
     * @param ApplicationInterface $application
     *
     * @return $this
     */
    public function setApplication(ApplicationInterface $application);

    /**
     * Get the config reference.
     *
     * @return ApplicationInterface
     */
    public function getApplication();
}
