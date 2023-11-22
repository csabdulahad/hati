<?php

namespace hati\config;

class ConfigWriter {

    //Add extra line break before these json keys
    private static array $group = [
        'project_dir_as_include_path', 'as_JSON_output', 'time_zone',
		'global_php', 'mailer_host', 'doc_config','img_config',
		'video_config', 'audio_config', 'jquery',
    ];

    private static function writeConfig(array $config, string $path): bool {
        $json = self::beautifyAsJSON($config);
        $hl = fopen("{$path}hati.json", 'w');

        $res = fwrite($hl, $json);
        if (!$res) return false;

        fflush($hl);
        fclose($hl);
        return true;
    }

    public static function write(string $rootPath, bool $createNew = false): array { 

        /*
         * Get both the configuration files & decode it
         */
        $newConfig = require_once 'HatiConfig.php';

        $filePath = "{$rootPath}hati.json";
        if ($createNew || !file_exists($filePath)) {
            $result = self::writeConfig($newConfig, $rootPath);
            $msg = $result ? 'hati.json file was created successfully' : 'Failed to write configuration as hati.json';

            return [
                'msg' => $msg,
                'success' => $result
            ];
        }

        $existingConfig = json_decode(file_get_contents($filePath), true);
        if (json_last_error() != JSON_ERROR_NONE) {
            return [
                'msg' => 'Existing hati.json file is corrupted',
                'success' => false
            ];
        }


        /*
         * Copy existing configuration
         */
        $newConfig = array_merge($newConfig, $existingConfig);


        /*
         * Write merged configuration to the hati.json file in the root directory
         */
        $result = self::writeConfig($newConfig, $rootPath);
        $msg = $result ? 'New configuration has been merged with existing hati.json'
            : 'Failed to merge the new configuration with existing hati.json';

        return [
            'msg' => $msg,
            'success' => $result
        ];
    }

    // Beautify the json output
    private static function beautifyAsJSON(array $json): string {
        $output = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $data = json_decode($output, true);
        $jsonOutput = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        foreach (self::$group as $item) {
            $pattern = '/^(\s*)"(' . preg_quote($item, '/') . '"):/m';
            $replacement = "$1\n    \"$2:";
            $jsonOutput = preg_replace($pattern, $replacement, $jsonOutput);
        }

        return $jsonOutput;
    }

}
