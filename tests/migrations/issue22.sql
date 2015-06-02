/**
 * SQLite
 */

DROP TABLE IF EXISTS "catalogs_types_lang";
DROP TABLE IF EXISTS "catalogs_types";

CREATE TABLE catalogs_types (
 id INTEGER NOT NULL PRIMARY KEY,
 sort int(10)  NOT NULL DEFAULT '100',
 workflow_id int(10) DEFAULT NULL
);

CREATE TABLE catalogs_types_lang (
id INTEGER NOT NULL PRIMARY KEY,
type_id int(10) NOT NULL,
lang_id varchar(6) NOT NULL,
title varchar(255) NOT NULL,
CONSTRAINT catalogs_types_lang_ibfk_1 FOREIGN KEY (type_id) REFERENCES catalogs_types (id) ON DELETE CASCADE ON UPDATE CASCADE
);
