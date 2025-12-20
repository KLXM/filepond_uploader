<?php

namespace FriendsOfRedaxo\FilePond;

use rex_extension_point;
use rex_logger;
use rex_sql;
use rex_yform_manager_table;

/**
 * Extension Point Handler für MEDIA_IS_IN_USE
 * Prüft ob Medien in YForm-Feldern vom Typ filepond verwendet werden
 */
class FilePondMediaCleanup
{
    /**
     * Prüft ob ein Medium in YForm-Tabellen verwendet wird
     *
     * @param rex_extension_point $ep
     * @return array Liste von Warnungen
     */
    public static function isMediaInUse(rex_extension_point $ep): array
    {
        $warnings = $ep->getSubject();
        if (!is_array($warnings)) {
            $warnings = [];
        }

        $filename = $ep->getParam('filename');
        if (!$filename) {
            return $warnings;
        }

        // Ignore-Parameter aus Extension Point oder $GLOBALS
        $ignoreTable = $ep->getParam('ignore_table');
        $ignoreId = $ep->getParam('ignore_id');
        $ignoreField = $ep->getParam('ignore_field');

        // Fallback auf $GLOBALS wenn EP-Parameter leer (für internen deleteMedia()-Aufruf)
        if (!$ignoreTable && isset($GLOBALS['filepond_cleanup_ignore'])) {
            if (rex::isDebugMode() && rex_config::get('filepond_uploader', 'enable_debug_logging', false)) {
                rex_logger::factory()->debug('FilePondMediaCleanup: Verwende globale ignore-Parameter für ' . $filename);
            }
            $ignoreTable = $GLOBALS['filepond_cleanup_ignore']['table'] ?? null;
            $ignoreId = $GLOBALS['filepond_cleanup_ignore']['id'] ?? null;
            $ignoreField = $GLOBALS['filepond_cleanup_ignore']['field'] ?? null;
        }

        $sql = rex_sql::factory();
        $yformTables = rex_yform_manager_table::getAll();

        foreach ($yformTables as $table) {
            foreach ($table->getFields() as $field) {
                if ($field->getType() === 'value' && $field->getTypeName() === 'filepond') {
                    $tableName = $table->getTableName();
                    $fieldName = $field->getName();

                    // Überspringe das Feld das gerade bearbeitet wird
                    if ($ignoreTable === $tableName && $ignoreField === $fieldName) {
                        if (rex::isDebugMode() && rex_config::get('filepond_uploader', 'enable_debug_logging', false)) {
                            rex_logger::factory()->debug(sprintf(
                                'FilePondMediaCleanup: Überspringe Feld %s in %s',
                                $fieldName,
                                $tableName
                            ));
                        }
                        continue;
                    }

                    // Prüfe ob Datei in diesem Feld verwendet wird
                    $query = "SELECT id, $fieldName FROM $tableName WHERE FIND_IN_SET(:filename, $fieldName)";
                    
                    // Wenn wir eine ID ignorieren sollen, schließe diese aus
                    if ($ignoreTable === $tableName && $ignoreId) {
                        $query .= " AND id != :id";
                        $result = $sql->getArray($query, [
                            ':filename' => $filename,
                            ':id' => $ignoreId
                        ]);
                    } else {
                        $result = $sql->getArray($query, [':filename' => $filename]);
                    }

                    if (count($result) > 0) {
                        $tableLabel = $table->getName() ?: $tableName;
                        $warnings[] = sprintf(
                            'FilePond Feld "%s" in Tabelle "%s" (ID: %s)',
                            $fieldName,
                            $tableLabel,
                            implode(', ', array_column($result, 'id'))
                        );
                    }
                }
            }
        }

        return $warnings;
    }
}
