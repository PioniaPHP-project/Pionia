<?php

namespace Pionia\Generics\Base;

enum JoinType
{
    case INNER;
    case LEFT;
    case RIGHT;
    case FULL;
}
