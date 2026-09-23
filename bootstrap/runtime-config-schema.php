<?php

// Keep this file parseable by PHP 7.4: preflight loads it before the PHP
// version gate and before Composer or CodeIgniter are available.
if (! function_exists('support_k_runtime_config_is_valid')) {
    function support_k_runtime_config_is_valid($config, $schemaVersion)
    {
        if (! is_array($config)
            || ! is_int($schemaVersion)
            || ! array_key_exists('format', $config)
            || $config['format'] !== 1
            || ! array_key_exists('installed', $config)
            || ! is_bool($config['installed'])
            || ! array_key_exists('schema_version', $config)
            || ! is_int($config['schema_version'])
            || $config['schema_version'] !== $schemaVersion
            || ! isset($config['installation_id'])
            || ! is_string($config['installation_id'])
            || preg_match('/^[a-f0-9]{32}$/D', $config['installation_id']) !== 1
            || ! isset($config['key'])
            || ! is_string($config['key'])
            || preg_match('/^[a-f0-9]{64}$/D', $config['key']) !== 1
            || ! isset($config['base_url'])
            || ! is_string($config['base_url'])
            || ! support_k_runtime_base_url_is_valid($config['base_url'])
            || ! isset($config['site_name'])
            || ! support_k_runtime_site_name_is_valid($config['site_name'])
            || ! isset($config['database'])
            || ! support_k_runtime_database_is_valid($config['database'])
        ) {
            return false;
        }

        return true;
    }
}

if (! function_exists('support_k_runtime_site_name_is_valid')) {
    function support_k_runtime_site_name_is_valid($siteName)
    {
        if (! is_string($siteName) || trim($siteName) === '') {
            return false;
        }

        // preg_match_all with the Unicode modifier counts code points without
        // requiring mbstring, which may itself be the reason preflight stops.
        $length = preg_match_all('/./us', $siteName, $characters);

        return $length !== false && $length <= 100;
    }
}

if (! function_exists('support_k_runtime_base_url_is_valid')) {
    function support_k_runtime_base_url_is_valid($baseUrl)
    {
        if (! is_string($baseUrl)
            || $baseUrl === ''
            || substr($baseUrl, -1) !== '/'
            || filter_var($baseUrl, FILTER_VALIDATE_URL) === false
        ) {
            return false;
        }

        $url = parse_url($baseUrl);
        if (! is_array($url)
            || ! isset($url['scheme'], $url['host'])
            || isset($url['user'])
            || isset($url['pass'])
            || isset($url['query'])
            || isset($url['fragment'])
        ) {
            return false;
        }

        $scheme = strtolower($url['scheme']);
        $host = strtolower($url['host']);
        if ($scheme === 'https') {
            return true;
        }

        return $scheme === 'http' && ($host === 'localhost' || $host === '127.0.0.1');
    }
}

if (! function_exists('support_k_runtime_database_is_valid')) {
    function support_k_runtime_database_is_valid($database)
    {
        if (! is_array($database)) {
            return false;
        }

        foreach (array('hostname', 'database', 'username', 'password', 'DBDriver', 'DBPrefix') as $key) {
            if (! array_key_exists($key, $database) || ! is_string($database[$key])) {
                return false;
            }
        }

        return $database['hostname'] !== ''
            && $database['database'] !== ''
            && $database['username'] !== ''
            && $database['DBDriver'] === 'MySQLi'
            && preg_match('/^[a-z][a-z0-9_]{0,19}_$/D', $database['DBPrefix']) === 1
            && array_key_exists('port', $database)
            && is_int($database['port'])
            && $database['port'] >= 1
            && $database['port'] <= 65535;
    }
}
