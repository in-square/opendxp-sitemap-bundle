<?php

namespace InSquare\OpendxpSitemapBundle\Sync;

use OpenDxp\Model\Tool\SettingsStore;

final class SitemapSyncStateStore
{
    private const SETTINGS_SCOPE = 'in_square_opendxp_sitemap';
    private const ACTIVE_RUN_TOKEN_KEY = 'active_run_token';

    public function setActiveRunToken(string $runToken): void
    {
        SettingsStore::set(
            self::ACTIVE_RUN_TOKEN_KEY,
            $runToken,
            SettingsStore::TYPE_STRING,
            self::SETTINGS_SCOPE
        );
    }

    public function getActiveRunToken(): ?string
    {
        $setting = SettingsStore::get(self::ACTIVE_RUN_TOKEN_KEY, self::SETTINGS_SCOPE);
        if (!$setting instanceof SettingsStore) {
            return null;
        }

        $token = trim((string) $setting->getData());

        return $token !== '' ? $token : null;
    }
}
