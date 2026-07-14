<?php

namespace App\Enum;

enum AuditAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Uploaded = 'uploaded';
    case Parsed = 'parsed';
    case Confirmed = 'confirmed';
    case Failed = 'failed';
    case Login = 'login';
    case Logout = 'logout';
}
