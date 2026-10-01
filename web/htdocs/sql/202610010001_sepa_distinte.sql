-- Distinte SEPA (XML CBI 04.01) per i rimborsi venditori con bonifico.
-- Spec: docs/superpowers/specs/2026-10-01-distinte-sepa-cbi-design.md
-- DA APPLICARE A MANO su ogni ambiente (staging, produzione).

-- 1. Nuovo stato: rimborso incluso in una distinta generata, non ancora pagato.
ALTER TABLE `seller_refund`
  MODIFY `status` ENUM('pending','partial','xmlsaved','completed','cancelled')
  NOT NULL DEFAULT 'pending';

-- 2. Una riga per file XML generato (il file NON viene salvato).
CREATE TABLE IF NOT EXISTS `sepa_batch` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `year` SMALLINT(4) NOT NULL,
  `msg_id` VARCHAR(35) NOT NULL COMMENT 'MsgId/PmtInfId inviati alla banca',
  `execution_date` DATE NOT NULL,
  `remittance_template` VARCHAR(140) NOT NULL,
  `debtor_iban_masked` VARCHAR(20) NOT NULL,
  `tx_count` INT(11) NOT NULL DEFAULT 0,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('generated','paid','discarded') NOT NULL DEFAULT 'generated',
  `created_by` INT(11) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `paid_at` DATETIME NULL,
  `paid_by` INT(11) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_msg_id` (`msg_id`),
  INDEX `idx_year_status` (`year`, `status`),
  CONSTRAINT `fk_sepa_batch_created_by` FOREIGN KEY (`created_by`) REFERENCES `user`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sepa_batch_paid_by` FOREIGN KEY (`paid_by`) REFERENCES `user`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Una riga per bonifico. Solo IBAN mascherato: quello completo resta cifrato in user.iban.
CREATE TABLE IF NOT EXISTS `sepa_batch_item` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `batch_id` INT(11) NOT NULL,
  `seller_refund_id` INT(11) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `end_to_end_id` VARCHAR(35) NOT NULL,
  `remittance` VARCHAR(140) NOT NULL,
  `beneficiary_name` VARCHAR(70) NOT NULL,
  `iban_masked` VARCHAR(20) NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_batch` (`batch_id`),
  INDEX `idx_refund` (`seller_refund_id`),
  CONSTRAINT `fk_sbi_batch` FOREIGN KEY (`batch_id`) REFERENCES `sepa_batch`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sbi_refund` FOREIGN KEY (`seller_refund_id`) REFERENCES `seller_refund`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Dati ordinante e causale (si compilano dalla pagina Distinte SEPA).
INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`, `description`) VALUES
  ('sepa_debtor_name', '', 'Distinte SEPA: intestatario conto del Comitato'),
  ('sepa_debtor_iban', '', 'Distinte SEPA: IBAN del Comitato'),
  ('sepa_debtor_cuc', '', 'Distinte SEPA: Codice Univoco CBI (CUC) assegnato dalla banca'),
  ('sepa_debtor_country', 'IT', 'Distinte SEPA: paese ordinante'),
  ('sepa_remittance_template', 'Rimb. Mercatino Da Vinci {anno} - Pratica {pratiche}', 'Distinte SEPA: causale (max 140 caratteri)');
