<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application;

use League\Container\DefinitionContainerInterface;

/**
 * Interface of ApplicationContainer
 */
interface ApplicationContainerInterface extends DefinitionContainerInterface
{
    /**
     * get all services or only services which ids match specified regex
     *
     * @param string|null $regex
     * @param string|null $tag
     * @param string|null $baseInstance
     *
     * @return array<object>
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function getServices(string $regex = null, string $tag = null, string $baseInstance = null);
}
