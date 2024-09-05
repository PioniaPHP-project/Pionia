<?php

namespace Pionia\Pionia\Base;


use Pionia\Pionia\Utils\Arrayable;

include 'All.php';

trait BuiltInServices
{
    /**
     * Get the built-in services provided by the application.
     *
     * @return Arrayable
     */
    protected function builtInMiddlewares(): Arrayable
    {
        return Arrayable::toArrayable(allBuiltins()->get('middlewares'));
    }

    /**
     * Get the built-in services provided by the application.
     *
     * @return Arrayable
     */
    protected function builtInCommands(): Arrayable
    {
        return Arrayable::toArrayable(allBuiltins()->get('commands'));
    }

    /**
     * Get the built-in services provided by the application.
     *
     * @return Arrayable
     */
    protected function builtInAuthentications(): Arrayable
    {
        return Arrayable::toArrayable(allBuiltins()->get('authentications'));
    }

    /**
     * Builtin Aliases
     * @return Arrayable
     */
    protected function builtInAliases(): Arrayable
    {
        return Arrayable::toArrayable(allBuiltins()->get('aliases'));
    }

    /**
     * Builtin Aliases
     * @return Arrayable
     */
    protected function builtinDirectories(): Arrayable
    {
        return Arrayable::toArrayable(allBuiltins()->get('directories'));
    }
}

