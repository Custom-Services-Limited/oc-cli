<?php

namespace OpenCart\CLI\Support;

/**
 * Shared helpers for OpenCart extension table operations.
 */
class ExtensionHelper
{
    /**
     * Resolve an extension identifier into a type/code pair.
     *
     * Supports either "type:code" input or an existing enabled code lookup.
     *
     * @param object $db
     * @param string $table
     * @param string $identifier
     * @return array<string, string>|null
     */
    public static function resolveTypeAndCode($db, $table, $identifier)
    {
        $identifier = trim($identifier);

        if (strpos($identifier, ':') !== false) {
            [$type, $code] = explode(':', $identifier, 2);
            $type = trim($type);
            $code = trim($code);

            if ($type !== '' && $code !== '') {
                return [
                    'type' => $type,
                    'code' => $code,
                ];
            }
        }

        $escapedCode = $db->escape($identifier);
        $result = $db->query("SELECT type, code FROM {$table} WHERE code = '{$escapedCode}' LIMIT 1");

        if ($result && $result->num_rows > 0) {
            return [
                'type' => $result->row['type'],
                'code' => $result->row['code'],
            ];
        }

        return null;
    }
}
