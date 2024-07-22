<?php

namespace Pionia\Generics\Base;

enum JoinType: string
{
    case INNER = 'INNER';
    case LEFT = 'LEFT';
    case RIGHT = 'RIGHT';
    case FULL = 'FULL';
}
