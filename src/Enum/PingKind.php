<?php

namespace App\Enum;

enum PingKind: string
{
    case Start = 'start';
    case Success = 'success';
    case Fail = 'fail';
}
