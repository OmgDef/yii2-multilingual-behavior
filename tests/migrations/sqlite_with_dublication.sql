/**
 * SQLite
 */

DROP TABLE IF EXISTS "post";

CREATE TABLE "post" (
  "id"    INTEGER NOT NULL PRIMARY KEY,
  "title"      TEXT,
  "body"      TEXT
);

DROP TABLE IF EXISTS "postLang";

CREATE TABLE "postLang" (
  "id"        INTEGER NOT NULL PRIMARY KEY,
  "post_id" INTEGER NOT NULL,
  "language" varchar(6) NOT NULL,
  "title"      TEXT,
  "body"      TEXT
);
