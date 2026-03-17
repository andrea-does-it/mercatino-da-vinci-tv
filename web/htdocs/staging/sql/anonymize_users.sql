-- ============================================================================
-- Anonymize PII in `user` table
-- Replaces first_name, last_name, and email with realistic Italian names
-- Preserves id, user_type, and all other non-PII fields
--
-- EXCEPTIONS: Add user IDs to skip anonymization (e.g. admin accounts)
-- ============================================================================

START TRANSACTION;

-- >>> CONFIGURE EXCEPTIONS HERE <<<
-- Comma-separated list of user IDs to EXCLUDE from anonymization
-- Example: SET @exclude_ids = '1,14,21';
SET @exclude_ids = '1,2,16,17,18,19,430';

-- Anonymize first_name, last_name, email using rotating realistic Italian names
UPDATE `user` SET
  first_name = ELT(1 + (id % 20),
    'Marco', 'Giulia', 'Alessandro', 'Francesca', 'Luca',
    'Chiara', 'Matteo', 'Sara', 'Andrea', 'Elena',
    'Davide', 'Anna', 'Lorenzo', 'Valentina', 'Simone',
    'Federica', 'Giorgio', 'Silvia', 'Roberto', 'Martina'),
  last_name = ELT(1 + (id % 18),
    'Rossi', 'Bianchi', 'Colombo', 'Ferrari', 'Esposito',
    'Romano', 'Moretti', 'Ricci', 'Marino', 'Greco',
    'Conti', 'Gallo', 'Mancini', 'Lombardi', 'Barbieri',
    'Fontana', 'Santoro', 'Pellegrini'),
  email = CONCAT(
    LOWER(ELT(1 + (id % 20),
      'marco', 'giulia', 'alessandro', 'francesca', 'luca',
      'chiara', 'matteo', 'sara', 'andrea', 'elena',
      'davide', 'anna', 'lorenzo', 'valentina', 'simone',
      'federica', 'giorgio', 'silvia', 'roberto', 'martina')),
    '.',
    LOWER(ELT(1 + (id % 18),
      'rossi', 'bianchi', 'colombo', 'ferrari', 'esposito',
      'romano', 'moretti', 'ricci', 'marino', 'greco',
      'conti', 'gallo', 'mancini', 'lombardi', 'barbieri',
      'fontana', 'santoro', 'pellegrini')),
    '_', id, '@example.com')
WHERE NOT FIND_IN_SET(id, @exclude_ids);

-- Also anonymize related PII fields if populated
UPDATE `user` SET
  iban            = NULL,
  iban_owner_name = NULL,
  iban_updated_at = NULL
WHERE iban IS NOT NULL
  AND NOT FIND_IN_SET(id, @exclude_ids);

UPDATE `user` SET
  student_first_name = ELT(1 + (id % 15),
    'Sofia', 'Leonardo', 'Aurora', 'Tommaso', 'Ginevra',
    'Edoardo', 'Beatrice', 'Mattia', 'Alice', 'Riccardo',
    'Emma', 'Filippo', 'Vittoria', 'Gabriele', 'Camilla'),
  student_last_name = last_name
WHERE (student_first_name IS NOT NULL OR student_last_name IS NOT NULL)
  AND NOT FIND_IN_SET(id, @exclude_ids);

-- Anonymize address table (contains user-linked PII)
UPDATE `address` SET
  street = CONCAT('Via ',
    ELT(1 + (user_id % 10),
      'Roma', 'Milano', 'Garibaldi', 'Mazzini', 'Dante',
      'Verdi', 'Marconi', 'Leopardi', 'Europa', 'Colombo'),
    ' ', 1 + (user_id % 50)),
  city = ELT(1 + (user_id % 8),
    'Roma', 'Milano', 'Torino', 'Firenze',
    'Bologna', 'Napoli', 'Venezia', 'Padova'),
  cap = LPAD(10100 + (user_id * 137 % 80000), 5, '0')
WHERE NOT FIND_IN_SET(user_id, @exclude_ids);

-- Reset password reset links (contain tokens)
UPDATE `user` SET reset_link = NULL
WHERE reset_link IS NOT NULL
  AND NOT FIND_IN_SET(id, @exclude_ids);

-- Verify results before committing
SELECT id, first_name, last_name, email FROM `user` ORDER BY id LIMIT 20;

-- Change to COMMIT once satisfied with the results
ROLLBACK;
