<?php

namespace App\Enum;

enum MonitorType: string
{
    case Heartbeat = 'heartbeat';
    case Http = 'http';
    case Tcp = 'tcp';
}
