/* v1.0.0 */

CREATE TABLE "link" (
  "id"	INTEGER NOT NULL UNIQUE,
  "created"	INTEGER NOT NULL DEFAULT current_timestamp,
  "code"	TEXT NOT NULL UNIQUE,
  "url"	TEXT NOT NULL,
  "disable"	INTEGER NOT NULL DEFAULT 0,
  "comment"	TEXT,
  PRIMARY KEY("id" AUTOINCREMENT)
);

INSERT INTO `link` (`id`, `created`, `code`, `url`, `disable`, `comment`) VALUES (1,	current_timestamp,	'default',	'https://github.com/Devenet/Shortener',	1,	'');

CREATE TABLE "view" (
  "id"	INTEGER NOT NULL UNIQUE,
  "created"	INTEGER NOT NULL DEFAULT current_timestamp,
  "link_id"	INTEGER NOT NULL,
  "ip_hash"	TEXT DEFAULT NULL,
  "referer"	TEXT DEFAULT NULL,
  "referer_host"	TEXT DEFAULT NULL,
  "user_agent"	TEXT DEFAULT NULL,
  PRIMARY KEY("id" AUTOINCREMENT)
);