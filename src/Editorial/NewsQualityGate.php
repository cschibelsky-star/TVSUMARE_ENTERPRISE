<?php

declare(strict_types=1);

namespace TVSumare\Editorial;

use DateTimeImmutable;
use TVSumare\Core\Config;

final class NewsQualityGate
{
    public function __construct(private Config $config)
    {
    }

    /**
     * @param array<string, mixed> $news
     * @param array<int, string> $knownHashes
     * @return array{approved: bool, score: int, reasons: string[]}
     */
    public function evaluate(array $news, array $knownHashes = []): array
    {
        $score = 100;
        $reasons = [];

        $title = trim((string)($news['title'] ?? ''));
        $city = trim((string)($news['city'] ?? ''));
        $url = trim((string)($news['url'] ?? ''));
        $image = trim((string)($news['image'] ?? ''));
        $publishedAt = trim((string)($news['published_at'] ?? ''));
        $hash = $this->hash($title, $url);

        if ($title === '' || mb_strlen($title) < 35) {
            $score -= 20;
            $reasons[] = 'Título ausente ou fraco.';
        }

        if (!$this->isAllowedCity($city)) {
            $score -= 35;
            $reasons[] = 'Cidade fora da região monitorada.';
        }

        if ($url === '') {
            $score -= 15;
            $reasons[] = 'URL de origem ausente.';
        }

        if (in_array($hash, $knownHashes, true)) {
            $score -= 45;
            $reasons[] = 'Notícia duplicada ou já processada.';
        }

        if ($image === '') {
            $score -= 25;
            $reasons[] = 'Imagem ausente.';
        }

        if ($this->isOld($publishedAt)) {
            $score -= 30;
            $reasons[] = 'Notícia antiga.';
        }

        $score = max(0, min(100, $score));
        $approved = $score >= (int)$this->config->get('minimum_quality_score', 80) && $reasons === [];

        return [
            'approved' => $approved,
            'score' => $score,
            'reasons' => $reasons,
        ];
    }

    public function hash(string $title, string $url): string
    {
        return sha1(mb_strtolower(trim($title)) . '|' . trim($url));
    }

    private function isAllowedCity(string $city): bool
    {
        if ($city === '') {
            return false;
        }

        foreach ($this->config->allowedCities() as $allowed) {
            if (mb_strtolower($city) === mb_strtolower($allowed)) {
                return true;
            }
        }

        return false;
    }

    private function isOld(string $publishedAt): bool
    {
        if ($publishedAt === '') {
            return true;
        }

        try {
            $published = new DateTimeImmutable($publishedAt);
        } catch (\Throwable) {
            return true;
        }

        $ageSeconds = time() - $published->getTimestamp();
        return $ageSeconds > ((int)$this->config->get('max_news_age_hours', 72) * 3600);
    }
}
