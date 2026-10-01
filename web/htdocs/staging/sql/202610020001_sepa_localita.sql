-- Distinte SEPA: località (TwnNm) richiesta da UniCredit per ordinante e beneficiari,
-- codice Category Purpose. DA APPLICARE A MANO su ogni ambiente, dopo 202610010001.
ALTER TABLE `user`
  ADD COLUMN `iban_town` VARCHAR(35) NULL COMMENT 'Località del beneficiario per i bonifici SEPA (facoltativa: se vuota si usa sepa_default_creditor_town)';

INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`, `description`) VALUES
  ('sepa_debtor_town', 'Treviso', 'Distinte SEPA: località ordinante (TwnNm)'),
  ('sepa_default_creditor_town', 'Treviso', 'Distinte SEPA: località beneficiario usata quando il venditore non ne ha una'),
  ('sepa_category_purpose', 'SUPP', 'Distinte SEPA: codice Category Purpose (CtgyPurp)');
