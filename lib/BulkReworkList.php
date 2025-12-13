<?php

namespace FriendsOfRedaxo\FilePondUploader;

use rex_list;

/**
 * Erweiterte rex_list für Bulk Resize
 *
 * @package filepond_uploader
 */
class BulkReworkList extends rex_list
{
    /**
     * Gibt aktuelle SQL Query zurück
     */
    public function getSql(): \rex_sql
    {
        return $this->sql;
    }

    /**
     * Setzt custom SQL Query
     *
     * @throws \rex_sql_exception
     */
    public function setCustomQuery(string $query): void
    {
        $this->sql->setQuery($query);
    }
}
