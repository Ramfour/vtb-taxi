<?php

namespace App\Enums;

enum RequestStatus: int
{
    case Pending = 1;
    case Approved = 2;
    case Rejected = 3;
    case Cancelled = 4;
    case Expired = 5;
}
