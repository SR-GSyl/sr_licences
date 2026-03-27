-- Table des demandes d'activation du module
CREATE TABLE IF NOT EXISTS sr_licence_demande_activation (
    id_demande_activation INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code_module VARCHAR(191) NOT NULL,
    version_module VARCHAR(64) DEFAULT NULL,
    nom_client VARCHAR(191) DEFAULT NULL,
    email_client VARCHAR(191) DEFAULT NULL,
    numero_commande VARCHAR(191) DEFAULT NULL,
    domaine_principal VARCHAR(191) NOT NULL,
    domaines_test TEXT DEFAULT NULL,
    secret_suivi CHAR(64) NOT NULL,
    statut ENUM('en_attente','validee','refusee','terminee') NOT NULL DEFAULT 'en_attente',
    id_licence INT UNSIGNED DEFAULT NULL,
    note_interne TEXT DEFAULT NULL,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_validation DATETIME DEFAULT NULL,
    date_refus DATETIME DEFAULT NULL,
    date_consommation DATETIME DEFAULT NULL,
    date_maj DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_demande_activation),
    UNIQUE KEY uniq_secret_suivi (secret_suivi),
    KEY idx_statut (statut),
    KEY idx_code_module (code_module),
    KEY idx_domaine_principal (domaine_principal),
    KEY idx_id_licence (id_licence)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

