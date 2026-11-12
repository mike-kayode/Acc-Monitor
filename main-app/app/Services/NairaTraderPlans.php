<?php

namespace NAPM\App\Services;

class NairaTraderPlans
{
    public static function catalog(): array
    {
        return [
            20 => ['label' => 'Trade Currencies, Metals & Cryptos Only (₦1,500,000)', 'minimum' => 50],
            17 => ['label' => 'Trade Currencies, Metals & Cryptos Only (₦3,000,000)', 'minimum' => 30],
            19 => ['label' => 'Trade Currencies, Metals & Cryptos Only (₦6,000,000)', 'minimum' => 30],
            13 => ['label' => "1M Fund Manager's Challenge (₦1,000,000) · hidden from storefront", 'minimum' => 10],
            26 => ['label' => 'Trade Currencies, Metals & Cryptos Only (₦500,000)', 'minimum' => 20],
            27 => ['label' => 'Trade Currencies, Metals & Cryptos Only (₦1,000,000)', 'minimum' => 20],
            29 => ['label' => 'Trade Currencies, Metals & Cryptos Only (₦400,000)', 'minimum' => 20],
            30 => ['label' => 'Trade Currencies, Metals & Cryptos Only (₦600,000)', 'minimum' => 10],
        ];
    }

    public static function formatReport(array $counts): string
    {
        $lines = [];
        foreach (self::catalog() as $planId => $plan) {
            if (!array_key_exists($planId, $counts) || !is_numeric($counts[$planId])) {
                throw new \RuntimeException('A required NairaTrader plan count is missing.');
            }
            $lines[] = $plan['label'] . ' — ' . number_format((int) $counts[$planId]) . ' left';
        }

        return implode("\n", $lines);
    }
}
