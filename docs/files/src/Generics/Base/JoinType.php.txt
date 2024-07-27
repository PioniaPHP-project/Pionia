<?php

namespace Pionia\Generics\Base;

/**
 * Supported join types in Pionia
 */
enum JoinType
{
    case INNER;
    case LEFT;
    case RIGHT;
    case FULL;
}
