<?php

namespace Pionia\Runtime;

enum RuntimeMode: string
{
    case Fpm = 'fpm';
    case Cli = 'cli';
    case Worker = 'worker';
    case Testing = 'testing';
}
