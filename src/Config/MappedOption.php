<?php
/*
 * This file is part of DgfipSI1\Application
 */
namespace DgfipSI1\Application\Config;

use Symfony\Component\Console\Input\InputOption as symfonyOption;
use Symfony\Component\Console\Input\InputOption;

/**
 * Mapped Option class
 */
class MappedOption
{
    public const OPT_SHORT               = 'short_option';
    public const OPT_DESCRIPTION         = 'description';
    public const OPT_DEFAULT_VALUE       = 'default';
    public const OPT_TYPE                = 'type';

    /** @var InputOption $inputOption */
    protected $inputOption;
    /** @var string $name */
    protected $name;
    /** @var string $description */
    protected $description;
    /** @var mixed $defaultValue */
    protected $defaultValue;
    /** @var OptionType $type */
    protected $type;

    /** @var string|null $commandName */
    protected $commandName;

    /**
     * @param string                                  $name
     * @param OptionType                              $type
     * @param string                                  $description
     * @param string|null                             $optShort
     * @param array<mixed>|bool|float|int|string|null $default
     *
     * @return void;
     */
    public function __construct($name, $type, $description = '', $optShort = null, $default = null)
    {
        $this->name         = $name;
        $this->defaultValue = $default;
        $this->type         = $type;
        $this->description  = $description;

        $name = str_replace('_', '-', $name);
        $mode = $type->mode();
        if (OptionType::Boolean === $type) {
            if (true === $default) {
                $description = $description." <comment>[default : true, use --no-$name to set to false]</comment>";
            } else {
                $description = $description." <comment>[default : false]</comment>";
            }
            $default = null;
        }
        $this->inputOption = new InputOption($name, $optShort, $mode, $description, $default);
    }

    /**
     *
     * @param string              $name
     * @param array<string,mixed> $options
     *
     * @return MappedOption
     */
    public static function createFromConfig($name, $options)
    {
        /** @var string|null $shortOpt */
        $shortOpt   = array_key_exists(self::OPT_SHORT, $options) ? $options[self::OPT_SHORT] : null;
        /** @var string $optDesc */
        $optDesc    = array_key_exists(self::OPT_DESCRIPTION, $options) ? $options[self::OPT_DESCRIPTION] : '';
        /** @var array<mixed>|bool|float|int|string|null $optDefault */
        $optDefault = array_key_exists(self::OPT_DEFAULT_VALUE, $options) ? $options[self::OPT_DEFAULT_VALUE] : null;
        if (!array_key_exists(self::OPT_TYPE, $options)) {
            throw new \Exception(sprintf('Missing option type for %s', $name));
        }
        /** @var string $optType */
        $optType = $options[self::OPT_TYPE];
        try {
            $type = OptionType::from($optType);
            $option = new MappedOption($name, $type, $optDesc, $shortOpt, $optDefault);
        } catch (\ValueError $e) {
            throw new \Exception(sprintf("Unknown option type '%s' for option '%s'", $optType, $name));
        } catch (\Exception $e) {
            throw new \Exception(sprintf('Error creating option %s : %s', $name, $e->getMessage()));
        }

        return $option;
    }

    /**
     * inputOption getter
     *
     * @return symfonyOption
     */
    public function getOption()
    {
        return $this->inputOption;
    }
    /**
     * name getter
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
    /**
     * description getter
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }
    /**
     * command getter
     *
     * @return string|null
     */
    public function getCommand()
    {
        return $this->commandName;
    }
    /**
     * command setter
     *
     * @param string $command
     *
     * @return self
     */
    public function setCommand($command)
    {
        $this->commandName = $command;

        return $this;
    }
    /**
     * default value getter
     *
     * @return mixed
     */
    public function getDefaultValue()
    {
        return $this->defaultValue;
    }
    /**  isBool
     * @return bool
    */
    public function isBool(): bool
    {
        return (OptionType::Boolean === $this->type);
    }
    /**  isArray
    * @return bool
    */
    public function isArray(): bool
    {
        return (OptionType::Array === $this->type);
    }
    /**  isScalar
     * @return bool
    */
    public function isScalar(): bool
    {
        return (OptionType::Scalar === $this->type);
    }
}
