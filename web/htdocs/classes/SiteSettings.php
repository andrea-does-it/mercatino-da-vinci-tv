<?php

class SiteSettings {

    private static $cache = null;

    /**
     * Load all settings from database (once per request).
     */
    private static function load() {
        if (self::$cache !== null) {
            return;
        }
        self::$cache = [];
        try {
            $db = new DB();
            $rows = $db->prepare("SELECT setting_key, setting_value FROM site_settings");
            foreach ($rows as $row) {
                self::$cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            error_log('SiteSettings: failed to load - ' . $e->getMessage());
        }
    }

    /**
     * Get a setting value as string.
     */
    public static function get($key, $default = null) {
        self::load();
        return isset(self::$cache[$key]) ? self::$cache[$key] : $default;
    }

    /**
     * Get a setting value as float.
     */
    public static function getFloat($key, $default = 0.0) {
        self::load();
        return isset(self::$cache[$key]) ? (float)self::$cache[$key] : $default;
    }

    /**
     * Get all settings as array of rows (setting_key, setting_value, description).
     */
    public static function getAll() {
        $db = new DB();
        return $db->prepare("SELECT setting_key, setting_value, description FROM site_settings ORDER BY setting_key");
    }

    /**
     * Update a setting value and optionally its description.
     */
    public static function set($key, $value, $description = null) {
        $db = new DB();
        if ($description !== null) {
            $db->execute(
                "INSERT INTO site_settings (setting_key, setting_value, description) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), description = VALUES(description)",
                [$key, $value, $description]
            );
        } else {
            $db->execute(
                "INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                [$key, $value]
            );
        }
        // Invalidate cache
        self::$cache = null;
    }

    /**
     * Euro deducted from seller price (subtracted from 50% of cover price).
     */
    public static function sellerDeduction() {
        return self::getFloat('bookshop_seller_deduction', 1.00);
    }

    /**
     * Euro added to buyer price (added to 50% of cover price).
     */
    public static function buyerMarkup() {
        return self::getFloat('bookshop_buyer_markup', 1.00);
    }

    /**
     * Total markup = seller deduction + buyer markup.
     * This is the amount added to single_price to get the buyer's price.
     */
    public static function totalMarkup() {
        return self::sellerDeduction() + self::buyerMarkup();
    }
}
