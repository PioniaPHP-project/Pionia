-- Example schema for SampoloService (sample_table + company join demo)
-- Run: php bin/init-db.php  (from example/)

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
