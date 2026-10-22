CREATE TABLE IF NOT EXISTS app_module_settings (
    module_key VARCHAR(40) PRIMARY KEY,
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    updated_by BIGINT CHECK (updated_by IS NULL OR updated_by > 0),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS app_user_preferences (
    user_id BIGINT PRIMARY KEY CHECK (user_id > 0),
    sidebar_collapsed BOOLEAN NOT NULL DEFAULT FALSE,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO app_module_settings (module_key, enabled)
VALUES ('atletas', TRUE), ('eventos', TRUE)
ON CONFLICT (module_key) DO NOTHING;
