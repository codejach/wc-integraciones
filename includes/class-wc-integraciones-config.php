<?php

class WC_Integraciones_Config {

    private static $config = null;

    public static function load() {

        if (self::$config !== null) {
            return self::$config;
        }

        $env = self::get_env();

        $config_file = plugin_dir_path(dirname(__FILE__)) 
            . 'config/config.' . $env . '.php';

        if (!file_exists($config_file)) {
            $config_file = plugin_dir_path(dirname(__FILE__)) 
                . 'config/config.prod.php';
        }

        self::$config = require $config_file;

        return self::$config;
    }

    public static function get($key, $default = null) {
        $config = self::load();
        return $config[$key] ?? $default;
    }

    public static function get_env() {
        return defined('WC_INTEGRACIONES_ENV')
            ? WC_INTEGRACIONES_ENV
            : 'prod';
    }

    public static function is_prod() {
        return self::get_env() === 'prod';
    }
}
