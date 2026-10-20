-- PostgreSQL / Neon
CREATE TABLE IF NOT EXISTS datasets (
    id BIGSERIAL PRIMARY KEY,
    type VARCHAR(20) NOT NULL CHECK (type IN ('titulados','empleadores')),
    filename VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    rows_count INTEGER NOT NULL DEFAULT 0,
    columns_count INTEGER NOT NULL DEFAULT 0,
    headers_json TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS responses (
    id BIGSERIAL PRIMARY KEY,
    dataset_id BIGINT NOT NULL REFERENCES datasets(id) ON DELETE CASCADE,
    row_number INTEGER NOT NULL,
    data_json TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_responses_dataset ON responses(dataset_id);
