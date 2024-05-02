<?php

namespace JetPhp\Command;

use JetPhp\Core\Base;
use Symfony\Component\Console\Application;

/***
 * This is the main console application class, it extends the Symfony console application class
 *
 * It is the entry point for all console commands and should be called to register all commands
 */
class ConsoleApplication extends Application
{
    public function __construct()
    {
        parent::__construct(Base::$name.' Console', Base::$version);
    }
}
