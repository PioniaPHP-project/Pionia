<?php

namespace Pionia\Http\Services;

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
