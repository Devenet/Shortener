/* v1.0.0 */

CREATE TABLE "shtnr_link" (
  "id"	INTEGER NOT NULL UNIQUE,
  "created"	INTEGER NOT NULL DEFAULT current_timestamp,
  "code"	TEXT NOT NULL UNIQUE,
  "url"	TEXT NOT NULL,
  "disable"	INTEGER NOT NULL DEFAULT 0,
  "comment"	TEXT,
  PRIMARY KEY("id" AUTOINCREMENT)
);

INSERT INTO `shtnr_link` (`id`, `created`, `code`, `url`, `disable`, `comment`) VALUES (1,	current_timestamp,	'default',	'https://github.com/Devenet/Shortener',	1,	'This specific “default” alias, when enabled, redirects the Shortener homepage to the specified URL.');

CREATE TABLE "shtnr_view" (
  "id"	INTEGER NOT NULL UNIQUE,
  "created"	INTEGER NOT NULL DEFAULT current_timestamp,
  "link_id"	INTEGER NOT NULL,
  "ip_hash"	TEXT DEFAULT NULL,
  "referer"	TEXT DEFAULT NULL,
  "referer_host"	TEXT DEFAULT NULL,
  "user_agent"	TEXT DEFAULT NULL,
  PRIMARY KEY("id" AUTOINCREMENT)
  FOREIGN KEY("link_id") REFERENCES "shtnr_link"("id") ON DELETE CASCADE 
);