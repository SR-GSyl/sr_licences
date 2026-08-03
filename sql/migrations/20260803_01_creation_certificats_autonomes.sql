-- SR Licences
-- Migration 20260803_01
--
-- Crée la table d'historique des certificats de licence autonomes signés.
--
-- Principes :
-- - le payload canonique exact est conservé ;
-- - la signature Base64 et le certificat JSON distribué sont conservés ;
-- - chaque émission reçoit un identifiant unique ;
-- - les certificats remplacés ou révoqués restent historisés ;
-- - l'état `actif` décrit le certificat actuellement distribuable par
--   SR Licences. Il ne constitue pas un mécanisme de révocation distante
--   d'un certificat déjà installé et utilisé hors ligne.
--
-- Cette migration ne crée aucun certificat et ne modifie aucune licence.
-- Elle est relançable grâce à CREATE TABLE IF NOT EXISTS.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `sr_licence_certificat_autonome` (
    `id_certificat_autonome` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_licence` INT UNSIGNED NOT NULL,
    `identifiant_certificat` VARCHAR(128) NOT NULL,
    `version_format` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `emetteur` VARCHAR(191) NOT NULL,
    `code_module` VARCHAR(100) NOT NULL,
    `methode_signature` VARCHAR(32) NOT NULL DEFAULT 'RSA-SHA256',
    `payload_canonique` LONGTEXT NOT NULL,
    `empreinte_payload_sha256` CHAR(64) NOT NULL,
    `signature_base64` TEXT NOT NULL,
    `certificat_json` LONGTEXT NOT NULL,
    `actif` TINYINT(1) NOT NULL DEFAULT 1,
    `id_certificat_remplacement` BIGINT UNSIGNED DEFAULT NULL,
    `date_emission` DATETIME NOT NULL,
    `date_revocation` DATETIME DEFAULT NULL,
    `motif_revocation` VARCHAR(255) DEFAULT NULL,
    `cree_par` INT UNSIGNED DEFAULT NULL,
    `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_maj` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id_certificat_autonome`),

    UNIQUE KEY `ux_sr_licence_certificat_autonome_identifiant`
        (`identifiant_certificat`),

    KEY `idx_sr_licence_certificat_autonome_id_licence`
        (`id_licence`),

    KEY `idx_sr_licence_certificat_autonome_licence_actif`
        (`id_licence`, `actif`),

    KEY `idx_sr_licence_certificat_autonome_actif`
        (`actif`),

    KEY `idx_sr_licence_certificat_autonome_date_emission`
        (`date_emission`),

    KEY `idx_sr_licence_certificat_autonome_remplacement`
        (`id_certificat_remplacement`),

    KEY `idx_sr_licence_certificat_autonome_empreinte`
        (`empreinte_payload_sha256`),

    CONSTRAINT `fk_sr_licence_certificat_autonome_licence`
        FOREIGN KEY (`id_licence`)
        REFERENCES `sr_licence` (`id_licence`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT `fk_sr_licence_certificat_autonome_remplacement`
        FOREIGN KEY (`id_certificat_remplacement`)
        REFERENCES `sr_licence_certificat_autonome`
            (`id_certificat_autonome`)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

SELECT
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    EXTRA
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'sr_licence_certificat_autonome'
ORDER BY ORDINAL_POSITION;

SELECT
    INDEX_NAME,
    NON_UNIQUE,
    SEQ_IN_INDEX,
    COLUMN_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'sr_licence_certificat_autonome'
ORDER BY INDEX_NAME, SEQ_IN_INDEX;

SELECT
    kcu.CONSTRAINT_NAME,
    kcu.COLUMN_NAME,
    kcu.REFERENCED_TABLE_NAME,
    kcu.REFERENCED_COLUMN_NAME,
    rc.UPDATE_RULE,
    rc.DELETE_RULE
FROM information_schema.KEY_COLUMN_USAGE AS kcu
INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS AS rc
    ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
   AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
   AND rc.TABLE_NAME = kcu.TABLE_NAME
WHERE kcu.TABLE_SCHEMA = DATABASE()
  AND kcu.TABLE_NAME = 'sr_licence_certificat_autonome'
  AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY kcu.CONSTRAINT_NAME, kcu.ORDINAL_POSITION;

SELECT
    'MIGRATION_SR_LICENCES_CERTIFICATS_AUTONOMES=OK'
        AS resultat;
