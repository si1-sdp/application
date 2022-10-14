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
     * get all services definitions or only services definitions which ids match specified regex
     *
     * @param string|null $regex
     * @param string|null $tag
     *
     * @return array<\League\Container\Definition\Definition>
     *
     * @throws \Exception
     */
    public function getDefinitions(string $regex = null, string $tag = null);


    /**
     * get all services or only services which ids match specified regex
     *
     * @param string|null $regex
     * @param string|null $tag
     *
     * @return array<object>
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function getServices(string $regex = null, string $tag = null);
}
