-- Suggestievoorstel om de live database (mysql_c9lkxmp0f_service_one.sql) bij te werken
-- naar de huidige codebase-stand (database/xml/*.xml, gegenereerd 2026-08-06).
-- Gegenereerd door vergelijking van de aangeleverde dump met `php database/parse.php`.
-- NIET automatisch uitgevoerd — controleer en run handmatig (bv. phpMyAdmin SQL-tab op Hostnet).
-- Achteraf: `php database/parse.php` hoeft niet, alleen de Beheer-UI "Database toepassen" kan dit
-- ook automatisch (behalve de MODIFY-regels onderaan, die wijzigt die UI bewust nooit).

-- 1) Ontbrekende tabellen (bestaan al in de XML/codebase, nog niet live)
--    Zonder deze tabellen crashen: barcode-sjabloonbeheer, apparaatscan-herkenning (Uitgifte/
--    Voorraad), het Statushistorie-menu bij HardwareUitgave, de Installatie-opdrachten-tool
--    (Tools), en aanmelden met personal access tokens (/api/v1/auth/login).

CREATE TABLE IF NOT EXISTS barcode_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT,
    naam VARCHAR(150) NOT NULL,
    patroon VARCHAR(255) NOT NULL,
    voorraad_type_id BIGINT UNSIGNED,
    omschrijving VARCHAR(255),
    actief TINYINT(1) NOT NULL DEFAULT 1,
    volgorde INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (voorraad_type_id) REFERENCES voorraad_types(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS device_scans (
    id BIGINT UNSIGNED AUTO_INCREMENT,
    raw_scan_value VARCHAR(255) NOT NULL,
    serienummer VARCHAR(100),
    product_id VARCHAR(100),
    beschrijving VARCHAR(255),
    mac_address VARCHAR(20),
    extra_parts_json TEXT,
    device_candidate TINYINT(1) NOT NULL DEFAULT 0,
    suggested_asset_type VARCHAR(50),
    match_source VARCHAR(30),
    match_confidence VARCHAR(20),
    matched_voorraad_item_id BIGINT UNSIGNED,
    matched_schijfgebruik_device_id BIGINT UNSIGNED,
    matched_barcode_template_id BIGINT UNSIGNED,
    suggested_medewerker_id INT,
    last_logged_on_user VARCHAR(150),
    context VARCHAR(20),
    gebruikt_door_id INT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (matched_voorraad_item_id) REFERENCES voorraad_items(id) ON DELETE SET NULL,
    FOREIGN KEY (matched_schijfgebruik_device_id) REFERENCES schijfgebruik_devices(id) ON DELETE SET NULL,
    FOREIGN KEY (matched_barcode_template_id) REFERENCES barcode_templates(id) ON DELETE SET NULL,
    FOREIGN KEY (suggested_medewerker_id) REFERENCES medewerkers(id) ON DELETE SET NULL,
    FOREIGN KEY (gebruikt_door_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS hardware_uitgave_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT,
    hardware_uitgave_id INT NOT NULL,
    user_id INT,
    status_van VARCHAR(30),
    status_naar VARCHAR(30) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (hardware_uitgave_id) REFERENCES hardware_uitgaven(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS installatie_opdrachten (
    id BIGINT UNSIGNED AUTO_INCREMENT,
    device_id BIGINT UNSIGNED NOT NULL,
    opmerking TEXT,
    toegewezen_door_id INT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS installatie_opdracht_items (
    id BIGINT UNSIGNED AUTO_INCREMENT,
    opdracht_id BIGINT UNSIGNED NOT NULL,
    naam VARCHAR(150) NOT NULL,
    volgorde INT NOT NULL DEFAULT 0,
    afgevinkt TINYINT(1) NOT NULL DEFAULT 0,
    afgevinkt_op TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (opdracht_id) REFERENCES installatie_opdrachten(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS installatie_opdracht_profielen (
    id BIGINT UNSIGNED AUTO_INCREMENT,
    opdracht_id BIGINT UNSIGNED NOT NULL,
    profiel_naam VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    FOREIGN KEY (opdracht_id) REFERENCES installatie_opdrachten(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS personal_access_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT,
    user_id INT NOT NULL,
    naam VARCHAR(150) NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    token_prefix VARCHAR(8) NOT NULL,
    laatst_gebruikt_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

-- 2) Ontbrekende kolommen op bestaande tabellen

-- cyberrisicos.kans/impact: CyberRisicoController/Service rekenen prioriteit uit kans x impact
-- (zie CyberRisicoService::prioriteitVanMatrix) en CyberRisicoModel::$fillable bevat beide velden.
-- Zonder deze kolommen faalt elke create/update van een cyberrisico op de live database.
ALTER TABLE cyberrisicos
    ADD COLUMN kans TINYINT NOT NULL DEFAULT 3 AFTER categorie,
    ADD COLUMN impact TINYINT NOT NULL DEFAULT 3 AFTER kans;

-- voorraad_items.product_id: onderdeel van de apparaatscan-herkenning (device_scans/AssetScan),
-- opgeslagen naast het serienummer bij een 3D-barcode-scan (serienummer,product-ID[,omschrijving]).
ALTER TABLE voorraad_items
    ADD COLUMN product_id VARCHAR(60) NULL AFTER serienummer;

-- 3) Kolomtype-correctie (geen "Beheer UI"-automatisme voor, want die wijzigt nooit een bestaand
--    kolomtype — dit moet dus handmatig)

-- tickets.opdrachtgever_naam staat live als VARCHAR(150), maar TicketModel versleutelt dit veld
-- (App\Shared\Crypto\FieldEncryptor::encrypt, opslagformaat base64(iv(12)+tag(16)+ciphertext)).
-- Bij AES-256-GCM is de ciphertext even lang als de plaintext, dus het opgeslagen base64-resultaat
-- is altijd langer dan de oorspronkelijke naam (ruwweg plaintext-lengte + ~38 tekens, dan base64
-- ge-encode met ~33% overhead). Een opdrachtgeversnaam van meer dan ~80 tekens overschrijdt
-- VARCHAR(150) al en wordt stilzwijgend afgekapt door MySQL (geen strict-mode-fout gegarandeerd,
-- afhankelijk van sql_mode) -> onherstelbaar corrupte/onleesbare versleutelde waarde. De XML
-- (database/xml/tickets.xml) definieert dit veld als TEXT, precies om deze reden.
ALTER TABLE tickets
    MODIFY COLUMN opdrachtgever_naam TEXT NOT NULL;
