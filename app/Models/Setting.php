<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['assistant_id', 'key', 'value'];

    // assistant_id = 0 é usado como "sem assistente": configurações globais do sistema (Ambiente).
    const GLOBAL_SCOPE = 0;

    public static function getGlobal(string $key, ?string $default = null): ?string
    {
        return static::where('assistant_id', self::GLOBAL_SCOPE)->where('key', $key)->value('value') ?? $default;
    }

    public static function setGlobal(string $key, ?string $value): void
    {
        static::updateOrCreate(
            ['assistant_id' => self::GLOBAL_SCOPE, 'key' => $key],
            ['value' => $value]
        );
    }

    /**
     * Configurações de marca/ambiente do sistema (logotipo, fundo do login, tema padrão, rodapé).
     */
    public static function branding(): array
    {
        $logoPath = self::getGlobal('app_logo_path');
        $loginBgPath = self::getGlobal('app_login_bg_path');

        return [
            'logo_url' => $logoPath ? '/files/' . $logoPath : null,
            'login_bg_url' => $loginBgPath ? '/files/' . $loginBgPath : null,
            'theme_default' => self::getGlobal('app_theme_default', 'light'),
            'footer_name' => self::getGlobal('app_footer_name', 'MultiAgents'),
            'footer_version' => self::getGlobal('app_footer_version', 'v1.0'),
            'footer_company' => self::getGlobal('app_footer_company', 'InHouse Tech'),
            'footer_year' => self::getGlobal('app_footer_year', date('Y')),
        ];
    }
}