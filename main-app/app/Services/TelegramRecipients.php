<?php

namespace NAPM\App\Services;

class TelegramRecipients
{
    public static function parse(string $primary, string $additional): array
    {
        $entries = [];
        $primary = trim($primary);
        if ($primary !== '') {
            $entries[] = $primary;
        }

        $additional = trim($additional);
        if ($additional !== '') {
            $entries = array_merge($entries, preg_split('/[\s,]+/', $additional, -1, PREG_SPLIT_NO_EMPTY));
        }

        $recipients = [];
        foreach ($entries as $recipient) {
            if (!preg_match('/^-?[0-9]{1,20}$/', $recipient)
                && !preg_match('/^@[A-Za-z][A-Za-z0-9_]{4,31}$/', $recipient)) {
                throw new \InvalidArgumentException('Use numeric Telegram chat IDs or @usernames.');
            }
            $recipients[] = $recipient;
        }

        $recipients = array_values(array_unique($recipients));
        if (count($recipients) > 4) {
            throw new \InvalidArgumentException('Configure no more than four recipients in total.');
        }

        return $recipients;
    }
}
