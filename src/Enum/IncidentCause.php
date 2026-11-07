<?php

namespace App\Enum;

enum IncidentCause: string
{
    case Missed = 'missed';
    case Failed = 'failed';
}
