<?php

namespace App\Enums;

enum Posture: string
{
    case Standing = 'standing';
    case Sitting = 'sitting';
    case Lying = 'lying';
    case Reclining = 'reclining';
}
