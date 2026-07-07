<?php

declare(strict_types=1);

namespace TVSumare\Core;

final class Config
{
    /** @var array<string, mixed> */
    private array $items;

    /** @param array<string, mixed> $items */
    public function __construct(array $items = [])
    {
        $this->items = $items + [
            'app_name' => 'TV Sumaré Enterprise',
            'powered_by' => 'Vitrine IA Pro',
            'site_url' => 'https://tvsumare.com.br',
            'allowed_cities' => ['Sumaré', 'Hortolândia', 'Paulínia', 'Nova Odessa', 'Americana', 'Campinas'],
            'max_news_age_hours' => 72,
            'minimum_quality_score' => 80,
            'gemini_mode' => getenv('GEMINI_MODE') ?: 'own',
            'gemini_model' => getenv('GEMINI_MODEL') ?: 'gemini-2.5-flash',
            'gemini_api_key' => getenv('GEMINI_API_KEY') ?: '',
        ];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? $default;
    }

    /** @return string[] */
    public function allowedCities(): array
    {
        return array_values($this->items['allowed_cities']);
    }
}
