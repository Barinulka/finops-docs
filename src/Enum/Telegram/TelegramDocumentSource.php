<?php

namespace App\Enum\Telegram;

enum TelegramDocumentSource: string
{
    case DirectMessage = 'direct_message';
    case GroupChat = 'group_chat';
    case ForwardedMessage = 'forwarded_message';
    case AdminUpload = 'admin_upload';

    public function label(): string
    {
        return match ($this) {
            self::DirectMessage => 'Личное сообщение',
            self::GroupChat => 'Групповой чат',
            self::ForwardedMessage => 'Пересланное сообщение',
            self::AdminUpload => 'Загрузка из админки',
        };
    }
}
