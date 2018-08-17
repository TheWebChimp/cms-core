CREATE TABLE IF NOT EXISTS comment (
	id BIGINT NOT NULL AUTO_INCREMENT,
	id_user BIGINT NOT NULL,
	id_entity BIGINT NOT NULL,
	parent BIGINT NOT NULL,
	source VARCHAR(255) NOT NULL,
	content MEDIUMTEXT NOT NULL,
	karma INTEGER NOT NULL,
	approved INTEGER NOT NULL,
	type VARCHAR(255) NOT NULL,
	mime_type VARCHAR(100) NOT NULL,
	created DATETIME NOT NULL,
	modified DATETIME NOT NULL,
	KEY key_user (id_user),
	KEY key_entity (id_entity),
	KEY key_parent (parent),
	KEY key_type (type),
	PRIMARY KEY pk_id (id)
) CHARACTER SET = UTF8;

CREATE TABLE IF NOT EXISTS comment_meta (
	id BIGINT NOT NULL AUTO_INCREMENT,
	id_comment BIGINT NOT NULL,
	name VARCHAR(255) NOT NULL,
	value LONGTEXT NOT NULL,
	PRIMARY KEY pk_id (id),
	UNIQUE KEY uk_comment_name (id_comment, name),
	KEY idx_comment_name (id_comment, name)
) DEFAULT CHARACTER SET = UTF8;

CREATE TABLE IF NOT EXISTS entity (
	id BIGINT NOT NULL AUTO_INCREMENT,
	author BIGINT NOT NULL,
	parent BIGINT NOT NULL,
	title MEDIUMTEXT NOT NULL,
	excerpt MEDIUMTEXT NOT NULL,
	password VARCHAR(255) NOT NULL,
	content LONGTEXT NOT NULL,
	type VARCHAR(50) NOT NULL,
	status VARCHAR(50) NOT NULL,
	slug VARCHAR(255) NOT NULL,
	position INTEGER NOT NULL,
	views BIGINT NOT NULL,
	comments BIGINT NOT NULL,
	mime_type VARCHAR(100) NOT NULL,
	published DATETIME NOT NULL,
	created DATETIME NOT NULL,
	modified DATETIME NOT NULL,
	KEY key_author (author),
	KEY key_parent (parent),
	KEY key_slug (slug),
	KEY key_type (type),
	KEY key_status (status),
	KEY key_position (position),
	KEY key_mime_type (mime_type),
	KEY key_published (published),
	PRIMARY KEY pk_id (id)
) CHARACTER SET = UTF8;

CREATE TABLE IF NOT EXISTS entity_meta (
	id BIGINT NOT NULL AUTO_INCREMENT,
	id_entity BIGINT NOT NULL,
	name VARCHAR(255) NOT NULL,
	value LONGTEXT NOT NULL,
	PRIMARY KEY pk_id (id),
	UNIQUE KEY uk_entity_name (id_entity, name),
	KEY idx_entity_name (id_entity, name)
) DEFAULT CHARACTER SET = UTF8;

CREATE TABLE IF NOT EXISTS term (
	id BIGINT NOT NULL AUTO_INCREMENT,
	parent BIGINT NOT NULL,
	name VARCHAR(255) NOT NULL,
	slug VARCHAR(255) NOT NULL,
	taxonomy VARCHAR(255) NOT NULL,
	description MEDIUMTEXT NOT NULL,
	position INTEGER NOT NULL,
	entities BIGINT NOT NULL,
	status VARCHAR(50) NOT NULL,
	created DATETIME NOT NULL,
	modified DATETIME NOT NULL,
	KEY key_id (id),
	KEY key_parent (parent),
	KEY key_slug (slug),
	KEY key_taxonomy (taxonomy),
	KEY key_position (position),
	KEY key_status (status),
	PRIMARY KEY pk_id (id)
) CHARACTER SET = UTF8;

CREATE TABLE IF NOT EXISTS term_meta (
	id BIGINT NOT NULL AUTO_INCREMENT,
	id_term BIGINT NOT NULL,
	name VARCHAR(255) NOT NULL,
	value LONGTEXT NOT NULL,
	PRIMARY KEY pk_id (id),
	UNIQUE KEY uk_term_name (id_term, name),
	KEY idx_term_name (id_term, name)
) DEFAULT CHARACTER SET = UTF8;

CREATE TABLE IF NOT EXISTS term_entity (
	id_term BIGINT NOT NULL,
	id_entity BIGINT NOT NULL,
	position INTEGER NOT NULL,
	KEY key_position (position),
	UNIQUE KEY uk_term_entity (id_term, id_entity)
) CHARACTER SET = UTF8;

CREATE TABLE IF NOT EXISTS user (
	id BIGINT NOT NULL AUTO_INCREMENT,
	login VARCHAR(150) NOT NULL,
	email VARCHAR(150) NOT NULL,
	nicename VARCHAR(250) NOT NULL,
	password VARCHAR(255) NOT NULL,
	status VARCHAR(50) NOT NULL,
	type VARCHAR(50) NOT NULL,
	created DATETIME NOT NULL,
	modified DATETIME NOT NULL,
	KEY key_login (login),
	KEY key_email (email),
	KEY key_status (status),
	KEY key_type (type),
	PRIMARY KEY pk_id (id)
) CHARACTER SET = UTF8;

CREATE TABLE IF NOT EXISTS user_meta (
	id BIGINT NOT NULL AUTO_INCREMENT,
	id_user BIGINT NOT NULL,
	name VARCHAR(255) NOT NULL,
	value LONGTEXT NOT NULL,
	PRIMARY KEY pk_id (id),
	UNIQUE KEY uk_user_name (id_user, name),
	KEY idx_user_name (id_user, name)
) DEFAULT CHARACTER SET = UTF8;

CREATE TABLE IF NOT EXISTS `option` (
	id BIGINT NOT NULL AUTO_INCREMENT,
	name VARCHAR(255) NOT NULL,
	value LONGTEXT NOT NULL,
	UNIQUE KEY uk_name (name),
	PRIMARY KEY pk_id (id)
) CHARACTER SET = UTF8;