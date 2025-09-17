<?php

namespace OpenCart\CLI\Support;

/**
 * Language helper utilities shared across commands.
 */
class LanguageHelper
{
    /**
     * Resolve the default language id for an OpenCart installation.
     *
     * @param object $db     Database connection implementing OpenCart's query API.
     * @param array  $config OpenCart configuration array (must include db_prefix).
     *
     * @return int
     */
    public static function getDefaultLanguageId($db, array $config): int
    {
        $prefix = $config['db_prefix'];
        $languageId = null;

        $settingResult = $db->query(
            "SELECT `value` FROM {$prefix}setting " .
            "WHERE `code` = 'config' AND `key` = 'config_language_id' LIMIT 1"
        );

        if ($settingResult && $settingResult->num_rows && isset($settingResult->row['value'])) {
            $languageId = (int)$settingResult->row['value'];
        }

        if (!$languageId) {
            $languageResult = $db->query(
                "SELECT language_id FROM {$prefix}language " .
                "WHERE status = 1 ORDER BY sort_order ASC, name ASC LIMIT 1"
            );

            if (
                $languageResult &&
                $languageResult->num_rows &&
                isset($languageResult->row['language_id'])
            ) {
                $languageId = (int)$languageResult->row['language_id'];
            }
        }

        return $languageId > 0 ? $languageId : 1;
    }
}
