ALTER TABLE telegram_settings
    ADD COLUMN additional_chat_ids TEXT NULL DEFAULT NULL AFTER chat_id;
