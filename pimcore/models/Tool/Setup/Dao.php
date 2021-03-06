<?php
/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @category   Pimcore
 * @package    Tool
 * @copyright  Copyright (c) 2009-2016 pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Model\Tool\Setup;

use Pimcore\Model;

class Dao extends Model\Dao\AbstractDao
{

    /**
     *
     */
    public function database()
    {
        $mysqlInstallScript = file_get_contents(PIMCORE_PATH . "/modules/install/mysql/install.sql");

        // remove comments in SQL script
        $mysqlInstallScript = preg_replace("/\s*(?!<\")\/\*[^\*]+\*\/(?!\")\s*/", "", $mysqlInstallScript);

        // get every command as single part
        $mysqlInstallScripts = explode(";", $mysqlInstallScript);

        // execute every script with a separate call, otherwise this will end in a PDO_Exception "unbufferd queries, ..." seems to be a PDO bug after some googling
        foreach ($mysqlInstallScripts as $m) {
            $sql = trim($m);
            if (strlen($sql) > 0) {
                $sql .= ";";
                $this->db->query($m);
            }
        }

        // set table search_backend_data to InnoDB if MySQL Version is > 5.6
        $this->db->query("ALTER TABLE search_backend_data /*!50600 ENGINE=InnoDB */;");

        // reset the database connection
        \Pimcore\Db::reset();
    }

    /**
     * @param $file
     * @throws \Zend_Db_Adapter_Exception
     */
    public function insertDump($file)
    {
        $sql = file_get_contents($file);

        //replace document root placeholder with current document root
        $docRoot = str_replace("\\", "/", PIMCORE_DOCUMENT_ROOT); // Windows fix
        $sql = str_replace("~~DOCUMENTROOT~~", $docRoot, $sql);

        // we have to use the raw connection here otherwise \Zend_Db uses prepared statements, which causes problems with inserts (: placeholders)
        // and mysqli causes troubles because it doesn't support multiple queries
        if ($this->db->getResource() instanceof \Zend_Db_Adapter_Mysqli) {
            $mysqli = $this->db->getConnection();
            $mysqli->multi_query($sql);

            // loop through results, because ->multi_query() is asynchronous
            do {
                if ($result = $mysqli->store_result()) {
                    $mysqli->free_result();
                }
            } while ($mysqli->next_result());
        } elseif ($this->db->getResource() instanceof \Zend_Db_Adapter_Pdo_Mysql) {
            $this->db->getConnection()->exec($sql);
        }

        \Pimcore\Db::reset();

        // set the id of the system user to 0
        $this->db->update("users", ["id" => 0], $this->db->quoteInto("name = ?", "system"));
    }

    /**
     * @throws \Zend_Db_Adapter_Exception
     */
    public function contents()
    {
        $this->db->insert("assets", [
            "id" => 1,
            "parentId" => 0,
            "type" => "folder",
            "filename" => "",
            "path" => "/",
            "creationDate" => time(),
            "modificationDate" => time(),
            "userOwner" => 1,
            "userModification" => 1
        ]);
        $this->db->insert("documents", [
            "id" => 1,
            "parentId" => 0,
            "type" => "page",
            "key" => "",
            "path" => "/",
            "index" => 999999,
            "published" => 1,
            "creationDate" => time(),
            "modificationDate" => time(),
            "userOwner" => 1,
            "userModification" => 1
        ]);
        $this->db->insert("documents_page", [
            "id" => 1,
            "controller" => "",
            "action" => "",
            "template" => "",
            "title" => "",
            "description" => ""
        ]);
        $this->db->insert("objects", [
            "o_id" => 1,
            "o_parentId" => 0,
            "o_type" => "folder",
            "o_key" => "",
            "o_path" => "/",
            "o_index" => 999999,
            "o_published" => 1,
            "o_creationDate" => time(),
            "o_modificationDate" => time(),
            "o_userOwner" => 1,
            "o_userModification" => 1
        ]);


        $this->db->insert("users", [
            "parentId" => 0,
            "name" => "system",
            "admin" => 1,
            "active" => 1
        ]);
        $this->db->update("users", ["id" => 0], $this->db->quoteInto("name = ?", "system"));


        $userPermissions = [
            ["key" => "application_logging"],
            ["key" => "assets"],
            ["key" => "classes"],
            ["key" => "clear_cache"],
            ["key" => "clear_temp_files"],
            ["key" => "document_types"],
            ["key" => "documents"],
            ["key" => "objects"],
            ["key" => "plugins"],
            ["key" => "predefined_properties"],
            ["key" => "routes"],
            ["key" => "seemode"],
            ["key" => "system_settings"],
            ["key" => "thumbnails"],
            ["key" => "translations"],
            ["key" => "redirects"],
            ["key" => "glossary" ],
            ["key" => "reports"],
            ["key" => "recyclebin"],
            ["key" => "seo_document_editor"],
            ["key" => "tags_config"],
            ["key" => "tags_assignment"],
            ["key" => "tags_search"],
            ["key" => "robots.txt"],
            ["key" => "http_errors"],
            ["key" => "tag_snippet_management"],
            ["key" => "qr_codes"],
            ["key" => "targeting"],
            ["key" => "notes_events"],
            ["key" => "backup"],
            ["key" => "emails"],
            ["key" => "website_settings"],
            ["key" => "newsletter"],
            ["key" => "dashboards"],
            ["key" => "users"],
        ];
        foreach ($userPermissions as $up) {
            $this->db->insert("users_permission_definitions", $up);
        }
    }
}
