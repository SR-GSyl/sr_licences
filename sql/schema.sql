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


-- Table des demandes de mise à jour des domaines de test
CREATE TABLE IF NOT EXISTS sr_licence_demande_domaines_test (
    id_demande_domaines_test INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_licence INT UNSIGNED NOT NULL,
    cle_licence VARCHAR(64) NOT NULL,
    code_module VARCHAR(191) NOT NULL,
    domaine_principal VARCHAR(191) NOT NULL,
    domaines_test_actuels TEXT DEFAULT NULL,
    domaines_test_demandes TEXT DEFAULT NULL,
    motif TEXT DEFAULT NULL,
    secret_suivi CHAR(64) NOT NULL,
    statut ENUM('en_attente','validee','refusee','terminee') NOT NULL DEFAULT 'en_attente',
    note_interne TEXT DEFAULT NULL,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_validation DATETIME DEFAULT NULL,
    date_refus DATETIME DEFAULT NULL,
    date_consommation DATETIME DEFAULT NULL,
    date_maj DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_demande_domaines_test),
    UNIQUE KEY uniq_demande_domaines_test_secret_suivi (secret_suivi),
    KEY idx_demande_domaines_test_statut (statut),
    KEY idx_demande_domaines_test_id_licence (id_licence),
    KEY idx_demande_domaines_test_cle_licence (cle_licence),
    KEY idx_demande_domaines_test_code_module (code_module),
    KEY idx_demande_domaines_test_domaine_principal (domaine_principal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des paramètres applicatifs non sensibles
CREATE TABLE IF NOT EXISTS sr_parametre_application (
    id_parametre INT UNSIGNED NOT NULL AUTO_INCREMENT,
    groupe_parametre VARCHAR(64) NOT NULL,
    cle_parametre VARCHAR(191) NOT NULL,
    valeur_parametre TEXT DEFAULT NULL,
    type_parametre ENUM('texte','booleen','entier','email','url','choix') NOT NULL DEFAULT 'texte',
    modifiable_interface TINYINT(1) NOT NULL DEFAULT 1,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_maj DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_parametre),
    UNIQUE KEY uniq_parametre_application_groupe_cle (groupe_parametre, cle_parametre),
    KEY idx_parametre_application_groupe (groupe_parametre),
    KEY idx_parametre_application_modifiable_interface (modifiable_interface)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table des secrets applicatifs chiffrés
CREATE TABLE IF NOT EXISTS sr_secret_application (
    id_secret INT UNSIGNED NOT NULL AUTO_INCREMENT,
    groupe_secret VARCHAR(64) NOT NULL,
    cle_secret VARCHAR(191) NOT NULL,
    valeur_chiffree LONGTEXT NOT NULL,
    nonce_chiffrement VARCHAR(255) NOT NULL,
    version_chiffrement VARCHAR(32) NOT NULL DEFAULT 'sodium-v1',
    modifiable_interface TINYINT(1) NOT NULL DEFAULT 0,
    date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_maj DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_secret),
    UNIQUE KEY uniq_secret_application_groupe_cle (groupe_secret, cle_secret),
    KEY idx_secret_application_groupe (groupe_secret),
    KEY idx_secret_application_modifiable_interface (modifiable_interface),
    KEY idx_secret_application_version_chiffrement (version_chiffrement)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Paramètres applicatifs par défaut pour les notifications e-mail
INSERT IGNORE INTO sr_parametre_application
    (groupe_parametre, cle_parametre, valeur_parametre, type_parametre, modifiable_interface)
VALUES
    ('notifications', 'activees', '1', 'booleen', 1),
    ('notifications', 'email_destinataire', '', 'email', 1),
    ('notifications', 'email_destinataire_activation', '', 'email', 1),
    ('notifications', 'email_destinataire_domaines_test', '', 'email', 1),
    ('notifications', 'prefixe_sujet', '[SR Licences]', 'texte', 1),

    ('email', 'transport', 'mail', 'choix', 1),
    ('email', 'expediteur_email', '', 'email', 1),
    ('email', 'expediteur_nom', 'SR Licences', 'texte', 1),
    ('email', 'repondre_a_email', '', 'email', 1),
    ('email', 'repondre_a_nom', '', 'texte', 1),

    ('email_smtp', 'hote', '', 'texte', 1),
    ('email_smtp', 'port', '587', 'entier', 1),
    ('email_smtp', 'chiffrement', 'tls', 'choix', 1),
    ('email_smtp', 'authentification', '1', 'booleen', 1),
    ('email_smtp', 'utilisateur', '', 'texte', 1),

    ('email_transactionnel', 'fournisseur', '', 'texte', 1),
    ('email_transactionnel', 'endpoint', '', 'url', 1);

