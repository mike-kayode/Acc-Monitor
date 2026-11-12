ALTER TABLE account_snapshots
    ADD COLUMN plan_counts_json LONGTEXT NULL AFTER previous_accounts;
