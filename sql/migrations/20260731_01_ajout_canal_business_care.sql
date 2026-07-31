-- SR Licences
-- Migration 20260731_01
--
-- Ajoute les métadonnées commerciales nécessaires à la distinction :
-- - du canal de vente ;
-- - du mode de validation distant ou autonome ;
-- - de Business Care ;
-- - du numéro de commande associé à la licence.
--
-- Cette migration ne modifie pas la validité fonctionnelle des licences
-- existantes.
--
-- Valeurs de continuité :
-- - mode_licence = distante ;
-- - canal_vente = non_renseigne ;
-- - business_care_inclus = NULL pour les licences historiques.
--
-- La migration est idempotente et peut être relancée.

SET NAMES utf8mb4;

SET @sr_schema = DATABASE();

-- -------------------------------------------------------------------------
-- mode_licence
-- -------------------------------------------------------------------------

SET @sr_sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @sr_schema
          AND TABLE_NAME = 'sr_licence'
          AND COLUMN_NAME = 'mode_licence'
    ),
    'SELECT ''COLONNE_mode_licence_DEJA_PRESENTE'' AS resultat',
    'ALTER TABLE `sr_licence`
        ADD COLUMN `mode_licence`
            VARCHAR(32)
            CHARACTER SET utf8mb4
            COLLATE utf8mb4_unicode_ci
            NOT NULL
            DEFAULT ''distante''
            AFTER `type_licence`'
);

PREPARE sr_migration_stmt FROM @sr_sql;
EXECUTE sr_migration_stmt;
DEALLOCATE PREPARE sr_migration_stmt;

-- -------------------------------------------------------------------------
-- canal_vente
-- -------------------------------------------------------------------------

SET @sr_sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @sr_schema
          AND TABLE_NAME = 'sr_licence'
          AND COLUMN_NAME = 'canal_vente'
    ),
    'SELECT ''COLONNE_canal_vente_DEJA_PRESENTE'' AS resultat',
    'ALTER TABLE `sr_licence`
        ADD COLUMN `canal_vente`
            VARCHAR(32)
            CHARACTER SET utf8mb4
            COLLATE utf8mb4_unicode_ci
            NOT NULL
            DEFAULT ''non_renseigne''
            AFTER `mode_licence`'
);

PREPARE sr_migration_stmt FROM @sr_sql;
EXECUTE sr_migration_stmt;
DEALLOCATE PREPARE sr_migration_stmt;

-- -------------------------------------------------------------------------
-- numero_commande
-- -------------------------------------------------------------------------

SET @sr_sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @sr_schema
          AND TABLE_NAME = 'sr_licence'
          AND COLUMN_NAME = 'numero_commande'
    ),
    'SELECT ''COLONNE_numero_commande_DEJA_PRESENTE'' AS resultat',
    'ALTER TABLE `sr_licence`
        ADD COLUMN `numero_commande`
            VARCHAR(191)
            CHARACTER SET utf8mb4
            COLLATE utf8mb4_unicode_ci
            DEFAULT NULL
            AFTER `canal_vente`'
);

PREPARE sr_migration_stmt FROM @sr_sql;
EXECUTE sr_migration_stmt;
DEALLOCATE PREPARE sr_migration_stmt;

-- -------------------------------------------------------------------------
-- business_care_inclus
-- NULL = non renseigné ; 0 = non ; 1 = oui.
-- -------------------------------------------------------------------------

SET @sr_sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @sr_schema
          AND TABLE_NAME = 'sr_licence'
          AND COLUMN_NAME = 'business_care_inclus'
    ),
    'SELECT ''COLONNE_business_care_inclus_DEJA_PRESENTE'' AS resultat',
    'ALTER TABLE `sr_licence`
        ADD COLUMN `business_care_inclus`
            TINYINT(1)
            DEFAULT NULL
            COMMENT ''NULL = non renseigne, 0 = non, 1 = oui''
            AFTER `version_max_autorisee`'
);

PREPARE sr_migration_stmt FROM @sr_sql;
EXECUTE sr_migration_stmt;
DEALLOCATE PREPARE sr_migration_stmt;

-- -------------------------------------------------------------------------
-- business_care_debut
-- -------------------------------------------------------------------------

SET @sr_sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @sr_schema
          AND TABLE_NAME = 'sr_licence'
          AND COLUMN_NAME = 'business_care_debut'
    ),
    'SELECT ''COLONNE_business_care_debut_DEJA_PRESENTE'' AS resultat',
    'ALTER TABLE `sr_licence`
        ADD COLUMN `business_care_debut`
            DATETIME
            DEFAULT NULL
            AFTER `business_care_inclus`'
);

PREPARE sr_migration_stmt FROM @sr_sql;
EXECUTE sr_migration_stmt;
DEALLOCATE PREPARE sr_migration_stmt;

-- -------------------------------------------------------------------------
-- business_care_fin
-- -------------------------------------------------------------------------

SET @sr_sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @sr_schema
          AND TABLE_NAME = 'sr_licence'
          AND COLUMN_NAME = 'business_care_fin'
    ),
    'SELECT ''COLONNE_business_care_fin_DEJA_PRESENTE'' AS resultat',
    'ALTER TABLE `sr_licence`
        ADD COLUMN `business_care_fin`
            DATETIME
            DEFAULT NULL
            AFTER `business_care_debut`'
);

PREPARE sr_migration_stmt FROM @sr_sql;
EXECUTE sr_migration_stmt;
DEALLOCATE PREPARE sr_migration_stmt;

-- -------------------------------------------------------------------------
-- Index du canal de vente
-- -------------------------------------------------------------------------

SET @sr_sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @sr_schema
          AND TABLE_NAME = 'sr_licence'
          AND INDEX_NAME = 'idx_sr_licence_canal_vente'
    ),
    'SELECT ''INDEX_canal_vente_DEJA_PRESENT'' AS resultat',
    'ALTER TABLE `sr_licence`
        ADD INDEX `idx_sr_licence_canal_vente` (`canal_vente`)'
);

PREPARE sr_migration_stmt FROM @sr_sql;
EXECUTE sr_migration_stmt;
DEALLOCATE PREPARE sr_migration_stmt;

-- -------------------------------------------------------------------------
-- Index du mode de licence
-- -------------------------------------------------------------------------

SET @sr_sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @sr_schema
          AND TABLE_NAME = 'sr_licence'
          AND INDEX_NAME = 'idx_sr_licence_mode_licence'
    ),
    'SELECT ''INDEX_mode_licence_DEJA_PRESENT'' AS resultat',
    'ALTER TABLE `sr_licence`
        ADD INDEX `idx_sr_licence_mode_licence` (`mode_licence`)'
);

PREPARE sr_migration_stmt FROM @sr_sql;
EXECUTE sr_migration_stmt;
DEALLOCATE PREPARE sr_migration_stmt;

-- -------------------------------------------------------------------------
-- Index du numéro de commande
-- -------------------------------------------------------------------------

SET @sr_sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @sr_schema
          AND TABLE_NAME = 'sr_licence'
          AND INDEX_NAME = 'idx_sr_licence_numero_commande'
    ),
    'SELECT ''INDEX_numero_commande_DEJA_PRESENT'' AS resultat',
    'ALTER TABLE `sr_licence`
        ADD INDEX `idx_sr_licence_numero_commande` (`numero_commande`)'
);

PREPARE sr_migration_stmt FROM @sr_sql;
EXECUTE sr_migration_stmt;
DEALLOCATE PREPARE sr_migration_stmt;

-- -------------------------------------------------------------------------
-- Index de fin de Business Care
-- -------------------------------------------------------------------------

SET @sr_sql = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @sr_schema
          AND TABLE_NAME = 'sr_licence'
          AND INDEX_NAME = 'idx_sr_licence_business_care_fin'
    ),
    'SELECT ''INDEX_business_care_fin_DEJA_PRESENT'' AS resultat',
    'ALTER TABLE `sr_licence`
        ADD INDEX `idx_sr_licence_business_care_fin`
            (`business_care_fin`)'
);

PREPARE sr_migration_stmt FROM @sr_sql;
EXECUTE sr_migration_stmt;
DEALLOCATE PREPARE sr_migration_stmt;

-- -------------------------------------------------------------------------
-- Contrôle final
-- -------------------------------------------------------------------------

SELECT
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @sr_schema
  AND TABLE_NAME = 'sr_licence'
  AND COLUMN_NAME IN (
      'mode_licence',
      'canal_vente',
      'numero_commande',
      'business_care_inclus',
      'business_care_debut',
      'business_care_fin'
  )
ORDER BY ORDINAL_POSITION;

SELECT
    INDEX_NAME,
    COLUMN_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = @sr_schema
  AND TABLE_NAME = 'sr_licence'
  AND INDEX_NAME IN (
      'idx_sr_licence_canal_vente',
      'idx_sr_licence_mode_licence',
      'idx_sr_licence_numero_commande',
      'idx_sr_licence_business_care_fin'
  )
ORDER BY INDEX_NAME, SEQ_IN_INDEX;

SELECT
    'MIGRATION_SR_LICENCES_CANAL_BUSINESS_CARE=OK'
        AS resultat;
