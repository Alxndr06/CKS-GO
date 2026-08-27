-- Ajoute la hierarchie CKS GO sans invalider les anciennes valeurs helper/mod.
ALTER TABLE users
    MODIFY role ENUM(
        'user',
        'helper',
        'mod',
        'assistant',
        'gestionnaire',
        'responsable',
        'admin'
    ) NOT NULL DEFAULT 'user';

UPDATE users SET role = 'assistant' WHERE role = 'helper';
UPDATE users SET role = 'gestionnaire' WHERE role = 'mod';

ALTER TABLE users
    MODIFY role ENUM(
        'user',
        'assistant',
        'gestionnaire',
        'responsable',
        'admin'
    ) NOT NULL DEFAULT 'user';
