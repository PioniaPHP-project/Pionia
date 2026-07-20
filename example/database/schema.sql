-- Legacy one-shot schema (kept for reference). Prefer:
--   php pionia migrate
-- See database/migrations/2024_01_01_000000_create_sample_tables.php

CREATE TABLE IF NOT EXISTS company (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS sample_table (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    file TEXT,
    company INTEGER REFERENCES company(id)
);

INSERT INTO company (name) VALUES ('Acme Corp'), ('Globex');
INSERT INTO sample_table (name, company) VALUES ('Widget A', 1), ('Widget B', 2);
