<?php

namespace App\Enum;

enum MonitorStatus: string
{
    case New = 'new';
    case Up = 'up';
    case Late = 'late';
    case Down = 'down';
    case Paused = 'paused';
}
